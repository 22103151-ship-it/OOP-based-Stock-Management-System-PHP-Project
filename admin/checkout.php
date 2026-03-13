<?php
session_start();
include '../config.php';
require_once '../includes/sslcommerz_config.php';

use App\Core\Auth;
use App\Core\Request;
use App\Services\PurchaseOrderManagementService;
use App\Services\SSLCommerzService;

Auth::requireRole('admin');

$purchaseOrderService = new PurchaseOrderManagementService($conn);

$order_id    = Request::getInt('order_id');
$total_price = isset($_GET['total_price']) ? (float)$_GET['total_price'] : 0.0;

if ($order_id <= 0 || $total_price <= 0) {
    die("Invalid order ID or total price.");
}

if (!$purchaseOrderService->findOrder($order_id)) {
    die("Invalid order ID.");
}

$ssl = new SSLCommerzService($SSLCOMMERZ_STORE_ID, $SSLCOMMERZ_STORE_PASS, (bool)$SSLCOMMERZ_SANDBOX, (bool)($SSLCOMMERZ_DEMO_MODE ?? false), $SSLCOMMERZ_CALLBACK_URL ?? '');
$https    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script   = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = (strpos($script, '/stock/') !== false) ? '/stock' : rtrim(dirname($script), '/\\');
$base     = $https . '://' . $host . $basePath;

$payload = [
    'store_id'        => $SSLCOMMERZ_STORE_ID,
    'store_passwd'    => $SSLCOMMERZ_STORE_PASS,
    'total_amount'    => $total_price,
    'currency'        => 'BDT',
    'tran_id'         => 'PO_' . $order_id . '_' . time(),
    'success_url'     => $base . '/admin/success.php?order_id=' . $order_id,
    'fail_url'        => $base . '/admin/purchase_orders.php?error=payment_failed',
    'cancel_url'      => $base . '/admin/purchase_orders.php?error=payment_cancelled',
    'cus_name'        => 'Admin Purchase Order',
    'cus_email'       => 'admin@stock.local',
    'cus_add1'        => 'Dhaka',
    'cus_city'        => 'Dhaka',
    'cus_country'     => 'Bangladesh',
    'cus_phone'       => '01711111111',
    'shipping_method' => 'NO',
    'product_name'    => 'Purchase Order #' . $order_id,
    'product_category' => 'Stock Purchase',
    'product_profile'  => 'general',
];

$init = $ssl->initPayment($payload);

if (!empty($init['ok']) && !empty($init['gateway_url'])) {
    header('Location: ' . $init['gateway_url']);
    exit;
} else {
    die("Payment Error: " . htmlspecialchars($init['error'] ?? 'Could not connect to SSLCommerz.'));
}
