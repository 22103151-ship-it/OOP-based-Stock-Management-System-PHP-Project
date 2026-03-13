<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\NotificationService;
Auth::requireRole('staff');

$notificationService = new NotificationService($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    $notification_id = (int)$_POST['notification_id'];

    if ($notificationService->deactivateDotByIdForUser($notification_id, 'staff')) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to acknowledge notification']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>