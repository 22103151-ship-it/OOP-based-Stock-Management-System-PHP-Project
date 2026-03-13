<?php

namespace App\Models;

class StaffOrder extends BaseModel
{
    protected string $table = 'staff_orders';

    public function create(string $productName, int $quantity, string $status = 'pending'): int
    {
        $stmt = $this->db->prepare('INSERT INTO staff_orders (product_name, quantity, status, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->bind_param('sis', $productName, $quantity, $status);
        $stmt->execute();
        return (int)$this->db->insert_id;
    }

    public function updateOrder(int $id, string $productName, int $quantity, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE staff_orders SET product_name=?, quantity=?, status=? WHERE id=?');
        $stmt->bind_param('sisi', $productName, $quantity, $status, $id);
        return $stmt->execute();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE staff_orders SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $id);
        return $stmt->execute();
    }
}
