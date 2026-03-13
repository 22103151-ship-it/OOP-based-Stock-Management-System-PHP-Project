<?php

namespace App\Models;

/**
 * CustomerOrder — model for the `customer_orders` table.
 *
 * Covers the full lifecycle: pending → confirmed → delivered / cancelled / returned.
 */
class CustomerOrder extends BaseModel
{
    protected string $table = 'customer_orders';

    // ------------------------------------------------------------------ reads

    /**
     * All orders for a customer, newest first, with product name joined.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByCustomer(int $customerId): array
    {
        $stmt = $this->db->prepare("
            SELECT co.*, p.name AS product_name, p.price AS unit_price, p.image AS product_image
            FROM   customer_orders co
            JOIN   products p ON co.product_id = p.id
            WHERE  co.customer_id = ?
            ORDER  BY co.created_at DESC
        ");
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /** Orders filtered by status (e.g. 'pending', 'confirmed', 'delivered'). */
    public function findByStatus(string $status): array
    {
        $stmt = $this->db->prepare("
            SELECT co.*, c.name AS customer_name, p.name AS product_name
            FROM   customer_orders co
            JOIN   customers c ON co.customer_id = c.id
            JOIN   products  p ON co.product_id  = p.id
            WHERE  co.status = ?
            ORDER  BY co.created_at DESC
        ");
        $stmt->bind_param('s', $status);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /** Single order with all joined info. */
    public function findDetailed(int $orderId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT co.*, p.name AS product_name, p.price, p.image AS product_image,
                   c.name AS customer_name, c.phone AS customer_phone
            FROM   customer_orders co
            JOIN   products  p ON co.product_id  = p.id
            JOIN   customers c ON co.customer_id = c.id
            WHERE  co.id = ?
        ");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public function findPendingByCustomer(int $customerId): array
    {
        $stmt = $this->db->prepare(" 
            SELECT co.id, co.quantity, co.price, co.status, co.order_date, co.created_at,
                   p.name AS product_name, p.image AS product_image
            FROM customer_orders co
            JOIN products p ON co.product_id = p.id
            WHERE co.customer_id = ? AND co.status = 'pending'
            ORDER BY co.order_date DESC
        ");
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function findForStaffBoard(int $limit = 200): array
    {
        $stmt = $this->db->prepare(" 
            SELECT co.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
                   p.name AS product_name
            FROM customer_orders co
            JOIN customers c ON co.customer_id = c.id
            JOIN products p ON co.product_id = p.id
            WHERE co.status IN ('pending', 'confirmed', 'shipped')
            ORDER BY co.order_date DESC
            LIMIT ?
        ");
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function findInvoiceByCustomer(int $orderId, int $customerId): ?array
    {
        $stmt = $this->db->prepare(" 
            SELECT co.id, co.quantity, co.price, co.order_date, co.status,
                   p.name AS product_name, c.name AS customer_name
            FROM customer_orders co
            JOIN products p ON co.product_id = p.id
            JOIN customers c ON co.customer_id = c.id
            WHERE co.id = ? AND co.customer_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('ii', $orderId, $customerId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public function findByStatusesDetailed(array $statuses, int $limit = 200): array
    {
        if (empty($statuses)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $types = str_repeat('s', count($statuses)) . 'i';
        $params = [...$statuses, $limit];

        $stmt = $this->db->prepare(
            "SELECT co.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
                    p.name AS product_name
             FROM customer_orders co
             JOIN customers c ON co.customer_id = c.id
             JOIN products p ON co.product_id = p.id
             WHERE co.status IN ({$placeholders})
             ORDER BY co.order_date DESC
             LIMIT ?"
        );
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function findAllDetailedWithCustomer(int $limit = 500): array
    {
        $stmt = $this->db->prepare(
            'SELECT co.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
                    p.name AS product_name
             FROM customer_orders co
             JOIN customers c ON co.customer_id = c.id
             JOIN products p ON co.product_id = p.id
             ORDER BY co.order_date DESC
             LIMIT ?'
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ------------------------------------------------------------------ writes

    /**
     * Create a new customer order and return the new order ID.
     *
     * @param  string  $paymentMethod  'later' | 'sslcommerz' | etc.
     */
    public function create(
        int    $customerId,
        int    $productId,
        int    $quantity,
        float  $price,
        string $paymentMethod = 'later',
        string $status        = 'pending'
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO customer_orders
                (customer_id, product_id, quantity, price, payment_method, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('iiidss', $customerId, $productId, $quantity, $price, $paymentMethod, $status);
        $stmt->execute();
        return (int)$this->db->insert_id;
    }

    /** Transition an order to a new status. */
    public function updateStatus(int $orderId, string $status): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE customer_orders SET status = ?, updated_at = NOW() WHERE id = ?"
        );
        $stmt->bind_param('si', $status, $orderId);
        return $stmt->execute();
    }

    /** Confirm receipt of delivered goods. */
    public function confirmReceived(int $orderId, int $customerId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE customer_orders
            SET    status = 'received', updated_at = NOW()
            WHERE  id = ? AND customer_id = ? AND status = 'delivered'
        ");
        $stmt->bind_param('ii', $orderId, $customerId);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    /** Request a return for a delivered order. */
    public function requestReturn(int $orderId, int $customerId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE customer_orders
            SET    status = 'return_requested', updated_at = NOW()
            WHERE  id = ? AND customer_id = ? AND status IN ('delivered','received')
        ");
        $stmt->bind_param('ii', $orderId, $customerId);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }
}
