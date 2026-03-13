<?php

namespace App\Services;

use App\Models\CustomerOrder;
use App\Models\Product;

class CustomerOrderWorkflowService
{
    private CustomerOrder $orderModel;
    private Product $productModel;
    private NotificationService $notificationService;

    public function __construct(\mysqli $db)
    {
        $this->orderModel = new CustomerOrder($db);
        $this->productModel = new Product($db);
        $this->notificationService = new NotificationService($db);
    }

    public function approvePendingByStaff(int $orderId): string
    {
        $order = $this->orderModel->findById($orderId);
        if (!$order || ($order['status'] ?? '') !== 'pending') {
            return 'error';
        }

        $quantity = (int)($order['quantity'] ?? 0);
        $productId = (int)($order['product_id'] ?? 0);
        $product = $this->productModel->findById($productId);

        if (!$product || (int)$product['stock'] < $quantity) {
            return 'insufficient_stock';
        }

        $okStatus = $this->orderModel->updateStatus($orderId, 'confirmed');
        $okStock = $this->productModel->decrementStock($productId, $quantity);
        if (!$okStatus || !$okStock) {
            return 'error';
        }

        $customerId = (int)($order['customer_id'] ?? 0);
        if ($customerId > 0) {
            $this->notificationService->sendOrderStatusUpdateNotification($customerId, $orderId, 'confirmed');
            $this->notificationService->createDot(
                'customer_order_confirmed',
                'staff',
                'customer',
                $orderId,
                'blue',
                'Your order has been approved by staff'
            );
        }

        return 'approved';
    }

    public function grantConfirmedByAdmin(int $orderId, string $action): string
    {
        if (!in_array($action, ['direct', 'staff'], true)) {
            return 'error';
        }

        $order = $this->orderModel->findById($orderId);
        if (!$order || ($order['status'] ?? '') !== 'confirmed') {
            return 'error';
        }

        $quantity = (int)($order['quantity'] ?? 0);
        $productId = (int)($order['product_id'] ?? 0);
        $product = $this->productModel->findById($productId);

        if (!$product || (int)$product['stock'] < $quantity) {
            return 'insufficient_stock';
        }

        $okStock = $this->productModel->decrementStock($productId, $quantity);
        $okStatus = $this->orderModel->updateStatus($orderId, 'shipped');
        if (!$okStock || !$okStatus) {
            return 'error';
        }

        $customerId = (int)($order['customer_id'] ?? 0);
        if ($customerId > 0) {
            $this->notificationService->sendOrderStatusUpdateNotification($customerId, $orderId, 'shipped');
        }

        if ($action === 'staff') {
            $this->notificationService->createDot(
                'admin_grant_staff',
                'admin',
                'staff',
                $orderId,
                'green',
                'Admin granted order to staff for delivery'
            );
        } else {
            $this->notificationService->createDot(
                'admin_grant_direct',
                'admin',
                'admin',
                $orderId,
                'blue',
                'Admin handling delivery directly'
            );
        }

        return 'ok';
    }

    public function confirmPendingByCustomer(int $orderId, int $customerId): string
    {
        if ($orderId <= 0 || $customerId <= 0) {
            return 'error';
        }

        $order = $this->orderModel->findById($orderId);
        if (!$order || (int)($order['customer_id'] ?? 0) !== $customerId || ($order['status'] ?? '') !== 'pending') {
            return 'invalid';
        }

        $ok = $this->orderModel->updateStatus($orderId, 'confirmed');
        if (!$ok) {
            return 'error';
        }

        $this->notificationService->sendOrderStatusUpdateNotification($customerId, $orderId, 'confirmed');
        $this->notificationService->createDot(
            'customer_order_confirmed',
            'customer',
            'admin',
            $orderId,
            'blue',
            'Customer confirmed an order'
        );

        return 'ok';
    }

    public function shipConfirmedByStaff(int $orderId): string
    {
        $order = $this->orderModel->findById($orderId);
        if (!$order || ($order['status'] ?? '') !== 'confirmed') {
            return 'error';
        }

        if (!$this->orderModel->updateStatus($orderId, 'shipped')) {
            return 'error';
        }

        $customerId = (int)($order['customer_id'] ?? 0);
        if ($customerId > 0) {
            $this->notificationService->sendOrderStatusUpdateNotification($customerId, $orderId, 'shipped');
            $this->notificationService->createDot(
                'customer_order_shipped',
                'staff',
                'customer',
                $orderId,
                'yellow',
                'Your order has been shipped'
            );
        }

        return 'ok';
    }

    public function deliverShippedByStaff(int $orderId): string
    {
        $order = $this->orderModel->findById($orderId);
        if (!$order || ($order['status'] ?? '') !== 'shipped') {
            return 'error';
        }

        if (!$this->orderModel->updateStatus($orderId, 'delivered')) {
            return 'error';
        }

        $customerId = (int)($order['customer_id'] ?? 0);
        if ($customerId > 0) {
            $this->notificationService->sendOrderNotification($customerId, $orderId);
        }

        $this->notificationService->createDot(
            'staff_delivery_done',
            'staff',
            'admin',
            $orderId,
            'green',
            'Staff marked order delivered'
        );
        $this->notificationService->deactivateDots('admin_grant_staff', $orderId);

        return 'ok';
    }

    public function approvePendingByAdmin(int $orderId): string
    {
        $order = $this->orderModel->findById($orderId);
        if (!$order || ($order['status'] ?? '') !== 'pending') {
            return 'invalid';
        }

        if (!$this->orderModel->updateStatus($orderId, 'confirmed')) {
            return 'error';
        }

        $customerId = (int)($order['customer_id'] ?? 0);
        if ($customerId > 0) {
            $this->notificationService->sendOrderStatusUpdateNotification($customerId, $orderId, 'confirmed');
            $this->notificationService->createDot(
                'customer_order_confirmed',
                'admin',
                'customer',
                $orderId,
                'blue',
                'Admin approved your order'
            );
        }

        return 'ok';
    }

    public function cancelConfirmedByAdmin(int $orderId): string
    {
        $order = $this->orderModel->findById($orderId);
        if (!$order || ($order['status'] ?? '') !== 'confirmed') {
            return 'invalid';
        }

        if (!$this->orderModel->updateStatus($orderId, 'cancelled')) {
            return 'error';
        }

        $customerId = (int)($order['customer_id'] ?? 0);
        if ($customerId > 0) {
            $this->notificationService->sendOrderStatusUpdateNotification($customerId, $orderId, 'cancelled');
            $this->notificationService->createDot(
                'customer_order_cancelled',
                'admin',
                'customer',
                $orderId,
                'red',
                'Your order was cancelled by admin'
            );
        }

        return 'ok';
    }

    public function updateStatusByAdmin(int $orderId, string $status): bool
    {
        $allowed = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled', 'returned', 'return_requested'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $order = $this->orderModel->findById($orderId);
        if (!$order) {
            return false;
        }

        if (!$this->orderModel->updateStatus($orderId, $status)) {
            return false;
        }

        $customerId = (int)($order['customer_id'] ?? 0);
        if ($customerId > 0) {
            if ($status === 'delivered') {
                $this->notificationService->sendOrderNotification($customerId, $orderId);
            } else {
                $this->notificationService->sendOrderStatusUpdateNotification($customerId, $orderId, $status);
            }
        }

        return true;
    }

    public function customerConfirmReceived(int $orderId, int $customerId): string
    {
        $order = $this->orderModel->findById($orderId);
        if (!$order || (int)($order['customer_id'] ?? 0) !== $customerId) {
            return 'invalid';
        }
        if (($order['status'] ?? '') !== 'shipped') {
            return 'invalid';
        }

        if (!$this->orderModel->updateStatus($orderId, 'delivered')) {
            return 'error';
        }

        $this->notificationService->sendOrderStatusUpdateNotification($customerId, $orderId, 'delivered');
        return 'ok';
    }
}
