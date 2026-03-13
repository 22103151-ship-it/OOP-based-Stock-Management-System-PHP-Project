<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\CustomerOrderWorkflowService;
use App\Services\CustomerPaymentService;
Auth::requireRole('customer');

$workflowService = new CustomerOrderWorkflowService($conn);
$paymentService = new CustomerPaymentService($conn);

$customer_id = (int)($_SESSION['customer_id'] ?? 0);
if ($customer_id <= 0 && isset($_SESSION['user_id'])) {
    $resolved = $paymentService->resolveCustomerIdByUserId((int)$_SESSION['user_id']);
    if ($resolved) {
        $customer_id = $resolved;
        $_SESSION['customer_id'] = $customer_id;
    }
}
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$gateway = isset($_GET['gateway']) ? $_GET['gateway'] : '';

$status = $workflowService->confirmPendingByCustomer($order_id, $customer_id);
if ($status !== 'ok') {
    header('Location: my_orders.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>পেমেন্ট সফল</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family:'Poppins', sans-serif; background:#f5f7fb; margin:0; padding:20px; }
        .wrap { max-width:480px; margin:0 auto; background:#fff; border-radius:12px; padding:20px; box-shadow:0 12px 28px rgba(0,0,0,0.1); text-align:center; }
        h1 { margin:0 0 10px 0; color:#16a34a; }
        .icon { font-size:48px; color:#16a34a; }
        a { text-decoration:none; color:#2563eb; font-weight:600; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="icon"><i class="fa-solid fa-circle-check"></i></div>
    <h1>পেমেন্ট সফল</h1>
    <p>গেটওয়ে: <?php echo htmlspecialchars($gateway ?: ''); ?></p>
    <p>আপনার পেমেন্ট সফল, অর্ডার নিশ্চিত হয়েছে। ডেলিভারি প্রসেসিং শুরু করতে অ্যাডমিন অনুমোদন দেবেন।</p>
    <a href="my_orders.php">আমার অর্ডার দেখুন</a>
</div>
</body>
</html>
