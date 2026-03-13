<?php

namespace App\Services;

use App\Models\Customer;

class CustomerOverviewService
{
    private \mysqli $db;
    private Customer $customerModel;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->customerModel = new Customer($db);
    }

    /**
     * @return array{customers: array<int,array<string,mixed>>, total_pro: int, total_vip: int, total_revenue: float, total_customers: int}
     */
    public function getOverviewData(): array
    {
        $customers = $this->customerModel->findAll('created_at DESC');
        $totalPro = $this->customerModel->count('customer_type = ?', ['pro']);
        $totalVip = $this->customerModel->count('customer_type = ?', ['vip']);

        $result = $this->db->query('SELECT SUM(registration_fee) AS total FROM customers');
        $row = $result ? $result->fetch_assoc() : null;
        $totalRevenue = (float)($row['total'] ?? 0);

        return [
            'customers' => $customers,
            'total_pro' => $totalPro,
            'total_vip' => $totalVip,
            'total_revenue' => $totalRevenue,
            'total_customers' => $totalPro + $totalVip,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function getDetailedCustomers(): array
    {
        $result = $this->db->query(
            'SELECT c.*, u.username
             FROM customers c
             LEFT JOIN users u ON c.user_id = u.id
             ORDER BY c.created_at DESC'
        );
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
}
