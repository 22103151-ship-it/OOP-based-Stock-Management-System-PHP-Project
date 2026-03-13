<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\CustomerOrderWorkflowService;
Auth::requireRole('admin');

$workflowService = new CustomerOrderWorkflowService($conn);

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
if ($order_id <= 0) {
    header('Location: customer_orders_confirmed.php?msg=approve_invalid');
    exit;
}

if ($workflowService->approvePendingByAdmin($order_id) !== 'ok') {
    header('Location: customer_orders_confirmed.php?msg=approve_invalid');
    exit;
}

header('Location: customer_orders_confirmed.php?msg=approved');
exit;
