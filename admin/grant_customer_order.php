<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\CustomerOrderWorkflowService;
Auth::requireRole('admin');

$workflowService = new CustomerOrderWorkflowService($conn);

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
if ($order_id <= 0 || !in_array($action, ['direct','staff'])) {
    header('Location: customer_orders_confirmed.php');
    exit;
}
$status = $workflowService->grantConfirmedByAdmin($order_id, $action);
if ($status === 'insufficient_stock') {
    header('Location: customer_orders_confirmed.php?msg=insufficient_stock');
    exit;
}
if ($status !== 'ok') {
    header('Location: customer_orders_confirmed.php');
    exit;
}

header('Location: grant_status.php?order_id=' . $order_id . '&action=' . $action);
exit;
