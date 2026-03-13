<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\OrderReturnService;
Auth::requireRole('admin');

$orderReturnService = new OrderReturnService($conn);

$type = isset($_GET['type']) ? $_GET['type'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0 || !in_array($type, ['supplier','customer'])) {
    header('Location: delivered_orders.php');
    exit;
}

if ($type === 'supplier') {
    $orderReturnService->returnDeliveredSupplierOrder($id);
} else {
    $orderReturnService->returnDeliveredCustomerOrder($id);
}

header('Location: delivered_orders.php');
exit;
