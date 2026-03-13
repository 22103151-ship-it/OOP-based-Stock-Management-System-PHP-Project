<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\CustomerOrderWorkflowService;
Auth::requireRole('customer');

$workflowService = new CustomerOrderWorkflowService($conn);

$customer_id = (int)($_SESSION['customer_id'] ?? 0);
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

if ($order_id <= 0) {
    header('Location: pending_orders.php');
    exit;
}

if ($workflowService->confirmPendingByCustomer($order_id, $customer_id) !== 'ok') {
    header('Location: pending_orders.php');
    exit;
}

header('Location: my_orders.php');
exit;
