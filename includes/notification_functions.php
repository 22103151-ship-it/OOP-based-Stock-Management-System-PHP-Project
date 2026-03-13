<?php
/**
 * notification_functions.php â€” backward-compatible wrapper.
 *
 * All real logic now lives in App\Services\NotificationService.
 * These thin wrapper functions delegate to that class so that every
 * existing include('notification_functions.php') still works without
 * any changes to the calling files.
 *
 * To use the OOP API directly:
 *   $notif = new \App\Services\NotificationService($conn);
 *   $notif->sendOrderNotification($customerId, $orderId);
 */

use App\Services\NotificationService;

class NotificationFunctionsBridge
{
    /** @var array<int,NotificationService> */
    private static array $instances = [];

    public static function service(\mysqli $conn): NotificationService
    {
        $key = spl_object_id($conn);
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new NotificationService($conn);
        }
        return self::$instances[$key];
    }
}

// --------------------------------------------------------------------------
// Helper: get (or create) a NotificationService for the given connection.
// --------------------------------------------------------------------------
function _notifSvc(\mysqli $conn): NotificationService
{
    return NotificationFunctionsBridge::service($conn);
}

// --------------------------------------------------------------------------
// Customer notifications
// --------------------------------------------------------------------------

function sendOrderNotification($customer_id, $order_id, $conn): bool
{
    return _notifSvc($conn)->sendOrderNotification((int)$customer_id, (int)$order_id);
}

function sendOrderStatusUpdateNotification($customer_id, $order_id, $status, $conn): bool
{
    return _notifSvc($conn)->sendOrderStatusUpdateNotification(
        (int)$customer_id, (int)$order_id, (string)$status
    );
}

function sendLowStockAlert($customer_id, $product_name, $remaining_stock, $conn): bool
{
    return _notifSvc($conn)->sendLowStockAlert(
        (int)$customer_id, (string)$product_name, (int)$remaining_stock
    );
}

function sendWelcomeNotification($customer_id, $conn): bool
{
    return _notifSvc($conn)->sendWelcomeNotification((int)$customer_id);
}

function getCustomerNotifications($customer_id, $limit = 10, $conn = null): array
{
    if ($conn === null) return [];
    return _notifSvc($conn)->getCustomerNotifications((int)$customer_id, (int)$limit);
}

function markNotificationAsRead($notification_id, $conn): bool
{
    return _notifSvc($conn)->markAsRead((int)$notification_id);
}

function getUnreadNotificationCount($customer_id, $conn): int
{
    return _notifSvc($conn)->getUnreadCount((int)$customer_id);
}

// --------------------------------------------------------------------------
// Notification dots
// --------------------------------------------------------------------------

function createNotificationDot(
    $notification_type, $from_user_type, $to_user_type,
    $reference_id, $dot_color, $message, $conn
): bool {
    return _notifSvc($conn)->createDot(
        (string)$notification_type,
        (string)$from_user_type,
        (string)$to_user_type,
        (int)$reference_id,
        (string)$dot_color,
        (string)$message
    );
}

function getActiveNotificationDots($user_type, $conn): array
{
    return _notifSvc($conn)->getActiveDots((string)$user_type);
}

function deactivateNotificationDots($notification_type, $reference_id, $conn): bool
{
    return _notifSvc($conn)->deactivateDots((string)$notification_type, (int)$reference_id);
}

function getNotificationDotCounts($user_type, $conn): array
{
    return _notifSvc($conn)->getDotCounts((string)$user_type);
}

// --------------------------------------------------------------------------
// Workflow triggers
// --------------------------------------------------------------------------

function handleCustomerProductRequest($customer_id, $product_id, $request_type = 'inquiry', $conn = null): void
{
    if ($conn === null) return;
    _notifSvc($conn)->onCustomerRequest((int)$customer_id, (int)$product_id, (string)$request_type);
}

function handleAdminApproval($customer_request_id, $admin_id, $conn): void
{
    _notifSvc($conn)->onAdminApproval((int)$customer_request_id);
}

function handleStaffProductNeed($product_id, $staff_id, $quantity_needed, $urgency, $conn): int
{
    return _notifSvc($conn)->onStaffProductNeed(
        (int)$product_id, (int)$staff_id, (int)$quantity_needed, (string)$urgency
    );
}

function handleAdminSupplierOrder($product_request_id, $supplier_id, $product_id, $quantity, $conn): void
{
    _notifSvc($conn)->onAdminSupplierOrder(
        (int)$product_request_id, (int)$supplier_id, (int)$product_id, (int)$quantity
    );
}

function handleSupplierResponse($supplier_order_id, $response, $conn): void
{
    _notifSvc($conn)->onSupplierResponse((int)$supplier_order_id, (string)$response);
}
