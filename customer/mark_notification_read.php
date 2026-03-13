<?php
session_start();
include '../config.php';

use App\Core\Auth;
use App\Services\NotificationService;

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$customer_id = Auth::customerId();
$notificationService = new NotificationService($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    $notification_id = (int)$_POST['notification_id'];
    if ($notificationService->markAsReadForCustomer($notification_id, (int)$customer_id)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Notification not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
