<?php
session_start();
header('Content-Type: application/json');

use App\Services\GuestOrderService;

try {
    include 'config.php';

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

    // ---- Return order details for demo page redirect ----
    unset($_SESSION['guest_cart']);
    unset($_SESSION['guest_verified']);
    unset($_SESSION['guest_id']);
    unset($_SESSION['guest_name']);
    unset($_SESSION['guest_phone']);
    
    echo json_encode([
        'success'      => true,
        'message'      => 'Order created successfully',
        'order_id'     => $orderId,
        'total'        => $totalAmount,
        'guest_id'     => $guestId,
        'total_stocks' => $result['total_stocks'],
        'session_id'   => $sessionId
    ]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Order failed: ' . $e->getMessage(),
        'error_class' => get_class($e)
    ]);
}