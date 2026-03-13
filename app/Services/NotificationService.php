<?php

namespace App\Services;

/**
 * NotificationService — OOP wrapper for all customer and role-based notifications.
 *
 * Every function from includes/notification_functions.php is now a method here,
 * keeping the same behaviour but removing the need for global function includes.
 *
 * Usage:
 *   $notif = new NotificationService($conn);
 *   $notif->sendOrderNotification($customerId, $orderId);
 *   $dots  = $notif->getDotCounts('admin');
 */
class NotificationService
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    // ================================================================== customer notifications

    /**
     * Send an "order delivered" notification to a customer and save it
     * as an AI chat message so they see it in the chat pane.
     */
    public function sendOrderNotification(int $customerId, int $orderId): bool
    {
        $customer = $this->_getCustomer($customerId);
        $order    = $this->_getOrderWithProduct($orderId);

        if (!$customer || !$order) {
            return false;
        }

        $message = "🎉 Great news! Your order for {$order['product_name']} has been delivered successfully!\n\n"
            . "📦 Order Details:\n"
            . "• Product: {$order['product_name']}\n"
            . "• Quantity: {$order['quantity']}\n"
            . "• Total Amount: ৳" . number_format($order['total_amount'], 2) . "\n"
            . "• Delivery Date: " . date('F d, Y', strtotime($order['updated_at'])) . "\n\n"
            . "Thank you for shopping with us!";

        return $this->_saveNotification($customerId, $orderId, 'order_delivered', $message);
    }

    /**
     * Notify a customer when their order status changes.
     *
     * @param  string  $status  One of: pending, processing, shipped, delivered, cancelled
     */
    public function sendOrderStatusUpdateNotification(int $customerId, int $orderId, string $status): bool
    {
        $order = $this->_getOrderWithProduct($orderId);
        if (!$order) {
            return false;
        }

        $messages = [
            'pending'    => "📋 Your order for {$order['product_name']} is now being processed.",
            'processing' => "⚙️ Your order for {$order['product_name']} is now being prepared for shipment.",
            'shipped'    => "🚚 Your order for {$order['product_name']} has been shipped and is on its way!",
            'delivered'  => "🎉 Your order for {$order['product_name']} has been delivered successfully!",
            'cancelled'  => "❌ Your order for {$order['product_name']} has been cancelled.",
        ];

        $message = $messages[$status] ?? "Your order status has been updated to: " . ucfirst($status);
        return $this->_saveNotification($customerId, $orderId, 'order_status_update', $message);
    }

    /** Warn a customer that a product they may want is running low. */
    public function sendLowStockAlert(int $customerId, string $productName, int $remainingStock): bool
    {
        $message = "⚠️ Product Alert: {$productName} is running low! Only {$remainingStock} units remaining.\n\n"
            . "Don't miss out — place your order now before it's gone!";

        return $this->_saveNotification($customerId, null, 'low_stock_alert', $message);
    }

    /** Send a welcome message when a customer account is first created. */
    public function sendWelcomeNotification(int $customerId): bool
    {
        $customer = $this->_getCustomer($customerId);
        if (!$customer) {
            return false;
        }

        $message = "👋 Welcome to our Stock Management System, {$customer['name']}!\n\n"
            . "🎉 Your account has been successfully created. You can now:\n\n"
            . "• Browse and purchase products\n"
            . "• Chat with our AI assistant for instant help\n"
            . "• Track your orders and delivery status\n"
            . "• Update your profile and preferences\n"
            . "• Contact support for any assistance\n\n"
            . "Happy shopping! 🛒";

        return $this->_saveNotification($customerId, null, 'welcome_message', $message);
    }

    // ================================================================== customer notification list

    /**
     * Retrieve the most recent notifications for a customer.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCustomerNotifications(int $customerId, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM automated_notifications
            WHERE  customer_id = ?
            ORDER  BY created_at DESC
            LIMIT  ?
        ");
        $stmt->bind_param('ii', $customerId, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /** Mark a single notification as read. */
    public function markAsRead(int $notificationId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE automated_notifications SET is_read = 1, read_at = NOW() WHERE id = ?"
        );
        $stmt->bind_param('i', $notificationId);
        return $stmt->execute();
    }

    public function markAsReadForCustomer(int $notificationId, int $customerId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE automated_notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND customer_id = ?'
        );
        $stmt->bind_param('ii', $notificationId, $customerId);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    /** Count unread notifications for a customer. */
    public function getUnreadCount(int $customerId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total FROM automated_notifications WHERE customer_id = ? AND is_read = 0"
        );
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_assoc()['total'];
    }

    // ================================================================== notification dots

    /**
     * Create a coloured notification dot visible in the top UI bar.
     *
     * @param  string  $color  'blue' | 'green' | 'yellow' | 'red'
     */
    public function createDot(
        string $type,
        string $fromUserType,
        string $toUserType,
        int    $referenceId,
        string $color,
        string $message
    ): bool {
        $stmt = $this->db->prepare("
            INSERT INTO notification_dots
                (notification_type, from_user_type, to_user_type, reference_id, dot_color, message)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssiss', $type, $fromUserType, $toUserType, $referenceId, $color, $message);
        return $stmt->execute();
    }

    /**
     * Fetch all active notification dots for one user type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveDots(string $userType): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM notification_dots
            WHERE  to_user_type = ? AND is_active = 1
            ORDER  BY created_at DESC
        ");
        $stmt->bind_param('s', $userType);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /** Deactivate dots matching a notification type and reference. */
    public function deactivateDots(string $type, int $referenceId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE notification_dots
            SET    is_active = 0, updated_at = NOW()
            WHERE  notification_type = ? AND reference_id = ?
        ");
        $stmt->bind_param('si', $type, $referenceId);
        return $stmt->execute();
    }

    public function deactivateDotByIdForUser(int $id, string $toUserType): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE notification_dots SET is_active = 0 WHERE id = ? AND to_user_type = ?'
        );
        $stmt->bind_param('is', $id, $toUserType);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    /**
     * Return counts of active dots by colour for a user type.
     *
     * @return array{blue: int, green: int, yellow: int, red: int}
     */
    public function getDotCounts(string $userType): array
    {
        $stmt = $this->db->prepare("
            SELECT dot_color, COUNT(*) AS count
            FROM   notification_dots
            WHERE  to_user_type = ? AND is_active = 1
            GROUP  BY dot_color
        ");
        $stmt->bind_param('s', $userType);
        $stmt->execute();
        $result = $stmt->get_result();

        $counts = ['blue' => 0, 'green' => 0, 'yellow' => 0, 'red' => 0];
        while ($row = $result->fetch_assoc()) {
            $counts[$row['dot_color']] = (int)$row['count'];
        }
        return $counts;
    }

    // ================================================================== workflow triggers

    /** Customer placed/inquired about an order → notify staff (blue dot). */
    public function onCustomerRequest(int $customerId, int $productId, string $requestType = 'inquiry'): void
    {
        $verb    = ($requestType === 'order') ? 'placed an order' : 'inquired about';
        $message = "Customer has {$verb} a product.";
        $this->createDot('customer_request', 'customer', 'staff', $productId, 'blue', $message);
    }

    /** Admin approved a customer request → notify staff (green dot). */
    public function onAdminApproval(int $customerRequestId): void
    {
        $this->createDot(
            'admin_approval', 'admin', 'staff', $customerRequestId,
            'green', 'Admin has approved a customer request. Please check product availability.'
        );
    }

    /**
     * Staff flagged a product need → notify admin (yellow dot) and log the request.
     * Returns the product_requests row ID.
     */
    public function onStaffProductNeed(int $productId, int $staffId, int $quantityNeeded, string $urgency): int
    {
        $message = "Staff has requested product replenishment (Urgency: {$urgency}).";
        $this->createDot('staff_product_need', 'staff', 'admin', $productId, 'yellow', $message);

        $stmt = $this->db->prepare("
            INSERT INTO product_requests (product_id, requested_by, quantity_needed, urgency_level, reason)
            VALUES (?, ?, ?, ?, 'Staff identified low stock')
        ");
        $stmt->bind_param('iiis', $productId, $staffId, $quantityNeeded, $urgency);
        $stmt->execute();
        return (int)$this->db->insert_id;
    }

    /** Admin ordered from supplier → notify supplier (blue dot) and log supplier order. */
    public function onAdminSupplierOrder(
        int $productRequestId,
        int $supplierId,
        int $productId,
        int $quantity
    ): void {
        $this->createDot(
            'admin_order_request', 'admin', 'supplier', $productRequestId,
            'blue', 'Admin has placed an order for products. Please confirm delivery.'
        );

        $stmt = $this->db->prepare("
            INSERT INTO supplier_orders (product_request_id, supplier_id, product_id, quantity)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param('iiii', $productRequestId, $supplierId, $productId, $quantity);
        $stmt->execute();
    }

    /**
     * Supplier responded to an order → notify admin and close the original dot.
     *
     * @param  string  $response  'accepted' | 'later' | 'cancelled'
     */
    public function onSupplierResponse(int $supplierOrderId, string $response): void
    {
        $colorMap   = ['accepted' => 'green', 'later' => 'yellow', 'cancelled' => 'red'];
        $messageMap = [
            'accepted'  => 'Supplier has accepted the order and will send products.',
            'later'     => 'Supplier will send products later.',
            'cancelled' => 'Supplier cannot fulfill the order at this time.',
        ];

        $color   = $colorMap[$response]   ?? 'red';
        $message = $messageMap[$response] ?? 'Supplier response received.';

        $stmt = $this->db->prepare(
            "UPDATE supplier_orders SET status = ?, updated_at = NOW() WHERE id = ?"
        );
        $stmt->bind_param('si', $response, $supplierOrderId);
        $stmt->execute();

        $this->createDot('supplier_response', 'supplier', 'admin', $supplierOrderId, $color, $message);
        $this->deactivateDots('admin_order_request', $supplierOrderId);
    }

    // ================================================================== private helpers

    private function _getCustomer(int $customerId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    private function _getOrderWithProduct(int $orderId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT co.*, p.name AS product_name
            FROM   customer_orders co
            JOIN   products p ON co.product_id = p.id
            WHERE  co.id = ?
        ");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * Persist a notification row and mirror it as an AI chat message.
     */
    private function _saveNotification(
        int     $customerId,
        ?int    $orderId,
        string  $type,
        string  $message
    ): bool {
        $stmt = $this->db->prepare("
            INSERT INTO automated_notifications (customer_id, order_id, notification_type, message, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param('iiss', $customerId, $orderId, $type, $message);

        if (!$stmt->execute()) {
            return false;
        }

        // Mirror into AI chat for in-app visibility
        $aiMessage  = "🤖 AI Assistant: {$message}";
        $chatInsert = $this->db->prepare("
            INSERT INTO ai_chat_messages (customer_id, response, message_type, created_at)
            VALUES (?, ?, 'ai_to_customer', NOW())
        ");
        if ($chatInsert) {
            $chatInsert->bind_param('is', $customerId, $aiMessage);
            $chatInsert->execute();
        }

        return true;
    }
}
