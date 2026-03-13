<?php
/**
 * SSL Commerz Payment Gateway Handler
 * Processes payment initiation and redirects to gateway
 */

session_start();
require_once 'config.php';
require_once 'includes/sslcommerz_config.php';

use App\Services\SSLCommerzService;
use App\Services\GuestOrderService;

header('Content-Type: application/json');

try {
    // Get form data
    $customer_name = $_POST['customer_name'] ?? 'Test User';
    $customer_email = $_POST['customer_email'] ?? 'test@example.com';
    $customer_phone = $_POST['customer_phone'] ?? '01700000000';
    $product_name = $_POST['product_name'] ?? 'Test Product';
    $amount = (float)($_POST['amount'] ?? 100);
    $order_id = $_POST['order_id'] ?? 'ORD' . time();
    $description = $_POST['product_description'] ?? 'Test transaction';
    $session_id = $_POST['session_id'] ?? '';
    $from_guest_checkout = $_POST['from_guest_checkout'] ?? '0';

    // Validate inputs
    if ($amount <= 0 || $amount > 99999999) {
        throw new Exception('Invalid amount. Must be between 1 and 99999999.');
    }

    if (strlen($order_id) > 20) {
        throw new Exception('Order ID too long (max 20 characters).');
    }

    // Get order info from guest checkout form
    $guest_id = $_POST['guest_id'] ?? null;
    $db_order_id = $_POST['order_id_db'] ?? null;

    // Generate transaction ID
    $tranId = 'TXN' . time() . str_pad(random_int(0, 99), 2, '0', STR_PAD_LEFT);

    // Determine callback URLs based on domain
    $host = 'www.nayeem.com';  // Use registered domain
    $base_url = 'http://' . $host . '/stock';

    // Prepare SSL Commerz payload
    $payload = [
        'store_id'           => $SSLCOMMERZ_STORE_ID,
        'store_passwd'       => $SSLCOMMERZ_STORE_PASS,
        'total_amount'       => $amount,
        'currency'           => 'BDT',
        'tran_id'            => $tranId,
        'success_url'        => $base_url . '/guest_payment_success.php',
        'fail_url'           => $base_url . '/guest_payment_fail.php',
        'cancel_url'         => $base_url . '/guest_payment_cancel.php',
        'shipping_method'    => 'NO',
        'product_name'       => substr($product_name, 0, 200),
        'product_category'   => 'General',
        'product_profile'    => 'general',
        'cus_name'           => substr($customer_name, 0, 50),
        'cus_email'          => substr($customer_email, 0, 100),
        'cus_add1'           => 'Dhaka',
        'cus_city'           => 'Dhaka',
        'cus_postcode'       => '1200',
        'cus_country'        => 'Bangladesh',
        'cus_phone'          => substr($customer_phone, 0, 20),
        'value_a'            => (string)($db_order_id ?? $order_id),
        'value_b'            => (string)($guest_id ?? 'test_user'),
        'value_c'            => $from_guest_checkout == '1' ? 'guest' : 'test_transaction',
        'format'             => 'json',
    ];

    // Initialize SSL Commerz service
    $ssl = new SSLCommerzService(
        $SSLCOMMERZ_STORE_ID,
        $SSLCOMMERZ_STORE_PASS,
        (bool)$SSLCOMMERZ_SANDBOX,
        false,
        $base_url
    );

    // Call the gateway
    $result = $ssl->initPayment($payload);

    if (!empty($result['ok']) && !empty($result['gateway_url'])) {
        // Redirect to gateway
        header('Content-Type: text/html');
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Redirecting to Payment Gateway...</title>
        </head>
        <body style="font-family: Arial; text-align: center; padding: 50px;">
            <h1>Processing Payment...</h1>
            <p>You will be redirected to SSL Commerz gateway in a moment.</p>
            <p>Transaction ID: <code>' . htmlspecialchars($tranId) . '</code></p>
            <p>If not redirected automatically, <a href="' . htmlspecialchars($result['gateway_url']) . '">click here</a>.</p>
            <script>
                window.location.href = "' . addslashes($result['gateway_url']) . '";
            </script>
        </body>
        </html>';
    } else {
        // Return error
        header('HTTP/1.1 400 Bad Request');
        echo json_encode([
            'success' => false,
            'message' => $result['error'] ?? 'Payment gateway error',
            'details' => $result,
        ]);
    }

} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
}
?>
