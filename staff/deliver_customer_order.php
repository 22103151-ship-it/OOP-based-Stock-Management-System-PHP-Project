<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\CustomerOrderWorkflowService;
Auth::requireRole('staff');

$workflowService = new CustomerOrderWorkflowService($conn);

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
if ($order_id <= 0) {
    header('Location: processing_orders.php');
    exit;
}

if ($workflowService->deliverShippedByStaff($order_id) !== 'ok') {
    header('Location: processing_orders.php');
    exit;
}

header('Location: delivery_status.php?order_id=' . $order_id . '&notified=1');
exit;
