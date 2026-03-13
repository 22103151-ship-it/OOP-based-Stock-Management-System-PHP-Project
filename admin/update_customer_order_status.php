<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\CustomerOrderWorkflowService;
Auth::requireRole('admin');

$workflowService = new CustomerOrderWorkflowService($conn);

if(isset($_POST['status']) && is_array($_POST['status'])) {
    foreach($_POST['status'] as $order_id => $status) {
        $order_id = intval($order_id);
        $workflowService->updateStatusByAdmin($order_id, strtolower((string)$status));
    }
    echo "success";
} else {
    echo "No data to update";
}
?>