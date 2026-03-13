<?php

namespace App\Services;

use App\Models\Customer;

class CustomerProfileService
{
    private \mysqli $db;
    private Customer $customerModel;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->customerModel = new Customer($db);
    }

    public function getProfile(int $customerId): ?array
    {
        return $this->customerModel->findById($customerId);
    }

    public function updateProfile(int $customerId, array $fields): bool
    {
        return $this->customerModel->updateProfile($customerId, $fields);
    }

    /** @return array<int,array<string,mixed>> */
    public function getSupportHistory(int $customerId, int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM support_messages WHERE customer_id = ? ORDER BY created_at DESC LIMIT ?');
        $stmt->bind_param('ii', $customerId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        return array_reverse($rows);
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
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        return [
            'total_orders' => (int)($row['total_orders'] ?? 0),
            'delivered_orders' => (int)($row['delivered_orders'] ?? 0),
            'pending_orders' => (int)($row['pending_orders'] ?? 0),
        ];
    }

    public function getTotalSpent(int $customerId): float
    {
        $stmt = $this->db->prepare(
            'SELECT SUM(price * quantity) as total_spent FROM customer_orders WHERE customer_id = ? AND status = ?'
        );
        $status = 'delivered';
        $stmt->bind_param('is', $customerId, $status);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return (float)($row['total_spent'] ?? 0);
    }
}
