<?php

namespace App\Models;

class SellProduct extends BaseModel
{
    protected string $table = 'sell_product';

    public function create(int $productId, string $productName, int $quantity, float $price): int
    {
        $stmt = $this->db->prepare('INSERT INTO sell_product (product_id, product_name, quantity, price) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('isid', $productId, $productName, $quantity, $price);
        $stmt->execute();
        return (int)$this->db->insert_id;
    }

    /** @return array<int,array<string,mixed>> */
    public function getHistoryWithCurrentStock(): array
    {
        $result = $this->db->query(" 
            SELECT sp.id, sp.product_name, sp.quantity, sp.price, sp.created_at, p.stock AS current_stock
            FROM sell_product sp
            LEFT JOIN products p ON sp.product_id = p.id
            ORDER BY sp.id DESC
        ");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function findByIdWithCurrentStock(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT sp.*, p.stock AS current_stock
             FROM sell_product sp
             LEFT JOIN products p ON sp.product_id = p.id
             WHERE sp.id = ?
             LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }
}
