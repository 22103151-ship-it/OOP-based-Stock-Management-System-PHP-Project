<?php

namespace App\Models;

/**
 * PurchaseOrder — model for the `purchase_orders` table.
 *
 * Purchase orders track stock bought from suppliers.
 */
class PurchaseOrder extends BaseModel
{
    protected string $table = 'purchase_orders';

    // ------------------------------------------------------------------ reads

    /** All purchase orders with supplier and product info, newest first. */
    public function findAllDetailed(): array
    {
        $result = $this->db->query("
            SELECT po.*, s.name AS supplier_name, p.name AS product_name
            FROM   purchase_orders po
            JOIN   suppliers s ON po.supplier_id = s.id
            JOIN   products  p ON po.product_id  = p.id
            ORDER  BY po.created_at DESC
        ");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /** Orders pending supplier delivery. */
    public function findPending(): array
    {
        return $this->_findByStatus('pending');
    }

    /** Orders pending for a specific supplier. */
    public function findPendingBySupplier(int $supplierId): array
    {
        return $this->_findByStatusAndSupplier('pending', $supplierId);
    }

    /** Orders that have been delivered. */
    public function findDelivered(): array
    {
        return $this->_findByStatus('delivered');
    }

    /** Orders marked as returned. */
    public function findReturned(): array
    {
        return $this->_findByStatus('returned');
    }

    // ------------------------------------------------------------------ writes

    /** Create a new purchase order. Returns the new ID. */
    public function create(
        int    $supplierId,
        int    $productId,
        int    $quantity,
        float  $totalCost,
        string $status = 'pending'
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO purchase_orders
                (supplier_id, product_id, quantity, total_cost, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('iiids', $supplierId, $productId, $quantity, $totalCost, $status);
        $stmt->execute();
        return (int)$this->db->insert_id;
    }

    /** Update the order status and timestamp. */
    public function updateStatus(int $orderId, string $status): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE purchase_orders SET status = ?, updated_at = NOW() WHERE id = ?"
        );
        $stmt->bind_param('si', $status, $orderId);
        return $stmt->execute();
    }

    /** Update status, but only for a specific supplier order. */
    public function updateStatusForSupplier(int $orderId, int $supplierId, string $status): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE purchase_orders SET status = ?, updated_at = NOW() WHERE id = ? AND supplier_id = ?"
        );
        $stmt->bind_param('sii', $status, $orderId, $supplierId);
        return $stmt->execute();
    }

    /** Fetch one purchase order with product details and optional supplier guard. */
    public function findOneWithProduct(int $orderId, ?int $supplierId = null): ?array
    {
        if ($supplierId !== null && $supplierId > 0) {
            $stmt = $this->db->prepare(" 
                SELECT po.*, p.price
                FROM purchase_orders po
                JOIN products p ON po.product_id = p.id
                WHERE po.id = ? AND po.supplier_id = ?
                LIMIT 1
            ");
            $stmt->bind_param('ii', $orderId, $supplierId);
        } else {
            $stmt = $this->db->prepare(" 
                SELECT po.*, p.price
                FROM purchase_orders po
                JOIN products p ON po.product_id = p.id
                WHERE po.id = ?
                LIMIT 1
            ");
            $stmt->bind_param('i', $orderId);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /** Supplier orders with product name + unit price for table views. */
    public function findBySupplierDetailed(?int $supplierId = null): array
    {
        if ($supplierId !== null && $supplierId > 0) {
            $stmt = $this->db->prepare(" 
                SELECT po.id, p.name AS product_name, po.quantity, po.status, po.created_at, p.price
                FROM purchase_orders po
                JOIN products p ON po.product_id = p.id
                WHERE po.supplier_id = ?
                ORDER BY po.id DESC
            ");
            $stmt->bind_param('i', $supplierId);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        $result = $this->db->query(" 
            SELECT po.id, p.name AS product_name, po.quantity, po.status, po.created_at, p.price
            FROM purchase_orders po
            JOIN products p ON po.product_id = p.id
            ORDER BY po.id DESC
        ");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function countAll(?int $supplierId = null): int
    {
        if ($supplierId !== null && $supplierId > 0) {
            $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM purchase_orders WHERE supplier_id = ?");
            $stmt->bind_param('i', $supplierId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            return (int)($row['cnt'] ?? 0);
        }

        $result = $this->db->query("SELECT COUNT(*) AS cnt FROM purchase_orders");
        $row = $result ? $result->fetch_assoc() : null;
        return (int)($row['cnt'] ?? 0);
    }

    public function countByStatus(string $status, ?int $supplierId = null): int
    {
        if ($supplierId !== null && $supplierId > 0) {
            $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM purchase_orders WHERE status = ? AND supplier_id = ?");
            $stmt->bind_param('si', $status, $supplierId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            return (int)($row['cnt'] ?? 0);
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM purchase_orders WHERE status = ?");
        $stmt->bind_param('s', $status);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int)($row['cnt'] ?? 0);
    }

    /** Count pending orders with optional supplier filter. */
    public function countPending(?int $supplierId = null): int
    {
        if ($supplierId !== null && $supplierId > 0) {
            $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM purchase_orders WHERE status='pending' AND supplier_id = ?");
            $stmt->bind_param('i', $supplierId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            return (int)($row['cnt'] ?? 0);
        }

        $result = $this->db->query("SELECT COUNT(*) AS cnt FROM purchase_orders WHERE status='pending'");
        $row = $result ? $result->fetch_assoc() : null;
        return (int)($row['cnt'] ?? 0);
    }

    // ------------------------------------------------------------------ private

    private function _findByStatus(string $status): array
    {
        $stmt = $this->db->prepare("
            SELECT po.*, s.name AS supplier_name, p.name AS product_name
            FROM   purchase_orders po
            JOIN   suppliers s ON po.supplier_id = s.id
            JOIN   products  p ON po.product_id  = p.id
            WHERE  po.status = ?
            ORDER  BY po.created_at DESC
        ");
        $stmt->bind_param('s', $status);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private function _findByStatusAndSupplier(string $status, int $supplierId): array
    {
        $stmt = $this->db->prepare(" 
            SELECT po.*, s.name AS supplier_name, p.name AS product_name
            FROM purchase_orders po
            JOIN suppliers s ON po.supplier_id = s.id
            JOIN products  p ON po.product_id  = p.id
            WHERE po.status = ? AND po.supplier_id = ?
            ORDER BY po.created_at DESC
        ");
        $stmt->bind_param('si', $status, $supplierId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
