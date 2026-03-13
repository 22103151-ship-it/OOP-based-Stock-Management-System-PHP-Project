<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\SupplierOrderService;
Auth::requireRole('supplier');
include '../includes/supplier_helpers.php';

$supplierOrderService = new SupplierOrderService($conn);

$supplier_id = getResolvedSupplierId($conn);

// Check if order_id is provided
if (isset($_GET['order_id'])) {
    $order_id = (int)$_GET['order_id'];

    $supplierOrderService->updateStatuses(
        $supplier_id > 0 ? $supplier_id : null,
        [(string)$order_id => 'delivered']
    );
}

// Redirect back to supplier dashboard
header("Location: dashboard.php");
exit;
