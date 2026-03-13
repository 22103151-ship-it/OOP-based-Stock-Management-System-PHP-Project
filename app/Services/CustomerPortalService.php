<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Product;

class CustomerPortalService
{
    private \mysqli $db;
    private Customer $customerModel;
    private Product $productModel;
    private CustomerOrder $customerOrderModel;
    private NotificationService $notificationService;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->customerModel = new Customer($db);
        $this->productModel = new Product($db);
        $this->customerOrderModel = new CustomerOrder($db);
        $this->notificationService = new NotificationService($db);
    }

    public function resolveCustomerByUserId(int $userId): ?array
    {
        return $this->customerModel->findByUserId($userId);
    }

    /** @return array<int,array<string,mixed>> */
    public function getProductsForBrowse(): array
    {
        return $this->productModel->findAll('stock DESC, name ASC');
    }

    public function getCartTotalQuantity(int $customerId): int
    {
        $stmt = $this->db->prepare('SELECT SUM(quantity) AS total FROM customer_cart WHERE customer_id = ?');
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['total'] ?? 0);
    }

    /** @return array{base_discount:int,bulk_discount:int,bulk_threshold:int,min_per_product:int} */
    public function getDiscountConfig(string $customerType): array
    {
        $isVip = ($customerType === 'vip');
        return [
            'base_discount' => $isVip ? 10 : 5,
            'bulk_discount' => $isVip ? 20 : 15,
            'bulk_threshold' => $isVip ? 70 : 50,
            'min_per_product' => $isVip ? 10 : 20,
        ];
    }

    /** @return array{total_orders:int,delivered_orders:int,pending_orders:int} */
    public function getOrderStats(int $customerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders
             FROM customer_orders
             WHERE customer_id = ?"
        );
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        return [
            'total_orders' => (int)($stats['total_orders'] ?? 0),
            'delivered_orders' => (int)($stats['delivered_orders'] ?? 0),
            'pending_orders' => (int)($stats['pending_orders'] ?? 0),
        ];
    }

    public function getAvailableProductCount(): int
    {
        return $this->productModel->count('stock > 0');
    }

    public function getUnreadNotificationsCount(int $customerId): int
    {
        return $this->notificationService->getUnreadCount($customerId);
    }

    /** @return array<int,array<string,mixed>> */
    public function getRecentNotifications(int $customerId, int $limit = 5): array
    {
        return $this->notificationService->getCustomerNotifications($customerId, $limit);
    }

    /** @return array<int,array<string,mixed>> */
    public function getCustomerOrders(int $customerId): array
    {
        return $this->customerOrderModel->findByCustomer($customerId);
    }

    /** @return array<int,array<string,mixed>> */
    public function getPendingOrders(int $customerId): array
    {
        return $this->customerOrderModel->findPendingByCustomer($customerId);
    }

    /** @return array<string,mixed>|null */
    public function getInvoiceOrder(int $customerId, int $orderId): ?array
    {
        return $this->customerOrderModel->findInvoiceByCustomer($orderId, $customerId);
    }
}
