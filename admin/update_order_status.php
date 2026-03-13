<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\PurchaseOrderManagementService;
Auth::requireRole('admin');

$purchaseOrderService = new PurchaseOrderManagementService($conn);

if(isset($_POST['status']) && is_array($_POST['status'])) {
    $purchaseOrderService->updateStatuses($_POST['status']);
    echo "success";
} else {
    echo "No data to update";
}
?>
