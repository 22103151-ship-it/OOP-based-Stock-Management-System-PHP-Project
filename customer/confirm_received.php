<?php
session_start();
include '../config.php';

use App\Core\Auth;
use App\Core\Request;
use App\Services\CustomerOrderWorkflowService;

Auth::requireRole('customer');

$customer_id = Auth::customerId();
$order_id    = Request::postInt('order_id') ?: Request::getInt('order_id');

if ($order_id <= 0 || !$customer_id) {
    header('Location: my_orders.php?msg=invalid');
    exit;
}

$workflowService = new CustomerOrderWorkflowService($conn);
$status = $workflowService->customerConfirmReceived($order_id, $customer_id);

if ($status !== 'ok') {
    header('Location: my_orders.php?msg=invalid');
    exit;
}
header('Location: my_orders.php?msg=received');
exit;
