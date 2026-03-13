<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\CustomerOrderWorkflowService;
Auth::requireRole('staff');

$workflowService = new CustomerOrderWorkflowService($conn);

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
if ($order_id <= 0) {
    header('Location: customer_orders.php?msg=error');
    exit;
}

$status = $workflowService->approvePendingByStaff($order_id);
if ($status === 'approved') {
    header('Location: customer_orders.php?msg=approved');
    exit;
}

if ($status === 'insufficient_stock') {
    header('Location: customer_orders.php?msg=error&reason=insufficient_stock');
    exit;
}

header('Location: customer_orders.php?msg=error');
exit;
