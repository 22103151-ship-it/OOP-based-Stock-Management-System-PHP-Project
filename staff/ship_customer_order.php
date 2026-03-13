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

if ($workflowService->shipConfirmedByStaff($order_id) !== 'ok') {
    header('Location: customer_orders.php?msg=error');
    exit;
}

header('Location: customer_orders.php?msg=shipped');
exit;
