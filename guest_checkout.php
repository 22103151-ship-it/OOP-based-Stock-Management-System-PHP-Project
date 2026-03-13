<?php
session_start();
header('Content-Type: application/json');

use App\Services\GuestOrderService;
use App\Services\SSLCommerzService;

try {
    include 'config.php';
    require_once 'includes/sslcommerz_config.php';

    $input     = json_decode(file_get_contents('php://input'), true) ?? [];
    $sessionId = $input['session_id'] ?? ($_SESSION['guest_session_id'] ?? '');

    // ---- Resolve guest identity ----
    if (!empty($input['session_id'])) {
        $service = new GuestOrderService($conn);
        $row = $service->findVerifiedGuestBySession($sessionId);
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Session expired or not verified']);
            exit;
        }
        $guestId    = (int)$row['id'];
        $guestName  = $row['name'];
        $guestPhone = $row['phone'];
        $rawItems   = $input['items'] ?? [];
        $cartItems  = array_map(fn($i) => ['product_id' => (int)$i['product_id'], 'quantity' => (int)$i['quantity']], $rawItems);
    } else {
        if (empty($_SESSION['guest_verified'])) {
            echo json_encode(['success' => false, 'message' => 'Not verified']);
            exit;
        }
        $guestId    = (int)($_SESSION['guest_id'] ?? 0);
        $guestName  = $_SESSION['guest_name'] ?? '';
        $guestPhone = $_SESSION['guest_phone'] ?? '';
        $rawCart    = $_SESSION['guest_cart'] ?? [];
        $cartItems  = array_values(array_map(fn($i) => ['product_id' => (int)$i['product_id'], 'quantity' => (int)$i['quantity']], $rawCart));
    }

    if (!$guestId || empty($cartItems)) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        exit;
    }

    // ---- Process order ----
    $service = $service ?? new GuestOrderService($conn);
    $result  = $service->checkout($guestId, $cartItems);

    if (!$result['success']) {
        echo json_encode($result);
        exit;
    }

    $orderId     = $result['order_id'];
    $totalAmount = $result['total'];
    $tranId      = 'GUEST' . $guestId . 'O' . $orderId . 'T' . time();

    $service->attachTranId($orderId, $tranId);

    // ---- Initiate SSLCommerz payment ----
    $ssl      = new SSLCommerzService($SSLCOMMERZ_STORE_ID, $SSLCOMMERZ_STORE_PASS, (bool)$SSLCOMMERZ_SANDBOX, (bool)($SSLCOMMERZ_DEMO_MODE ?? false));

    // Get correct base URL with path (e.g., http://localhost/stock)
    $https    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script   = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = (strpos($script, '/stock/') !== false) ? '/stock' : rtrim(dirname($script), '/\\');
    $base     = $https . '://' . $host . $basePath;

    $payload = [
        'store_id'        => $SSLCOMMERZ_STORE_ID,
        'store_passwd'    => $SSLCOMMERZ_STORE_PASS,
        'total_amount'    => $totalAmount,
        'currency'        => 'BDT',
        'tran_id'         => $tranId,
        'success_url'     => $base . '/guest_payment_success.php',
        'fail_url'        => $base . '/guest_payment_fail.php',
        'cancel_url'      => $base . '/guest_payment_cancel.php',
        'shipping_method' => 'NO',
        'product_name'    => 'Bulk Order - ' . $result['total_stocks'] . ' items',
        'product_category'  => 'General',
        'product_profile'   => 'general',
        'cus_name'        => $guestName,
        'cus_email'       => $guestPhone . '@guest.local',
        'cus_add1'        => 'Dhaka',
        'cus_city'        => 'Dhaka',
        'cus_postcode'    => '1200',
        'cus_country'     => 'Bangladesh',
        'cus_phone'       => $guestPhone,
        'value_a'         => (string)$orderId,
        'value_b'         => (string)$guestId,
        'value_c'         => 'guest',
        'multi_card_name' => 'bkash',
    ];

    $init = $ssl->initPayment($payload);

if (!empty($init['ok']) && !empty($init['gateway_url'])) {
    unset($_SESSION['guest_cart']);
    echo json_encode([
        'success'      => true,
        'message'      => 'Order created successfully',
        'order_id'     => $orderId,
        'redirect_url' => $init['gateway_url'],
    ]);
} else {
    echo json_encode(['success' => false, 'message' => $init['error'] ?? 'Payment gateway error']);
}
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Order failed: ' . $e->getMessage(),
        'error_class' => get_class($e)
    ]);
}