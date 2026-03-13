<?php

namespace App\Models;

/**
 * GuestOrder — model for `guest_orders` and `guest_order_items` tables.
 *
 * Handles order creation, item insertion, status transitions,
 * and read queries needed by the invoice/success pages.
 */
class GuestOrder extends BaseModel
{
    protected string $table = 'guest_orders';

    // ------------------------------------------------------------------ reads

    /**
     * Fetch a single guest order together with the guest customer info.
     * Used by the order-success / invoice page.
     */
    public function getWithCustomer(int $orderId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT go.*, gc.name AS guest_name, gc.phone AS guest_phone
            FROM   guest_orders go
            JOIN   guest_customers gc ON go.guest_id = gc.id
            WHERE  go.id = ?
        ");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * Fetch all line items for a guest order, including product name.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getItemsWithProduct(int $orderId): array
    {
        $stmt = $this->db->prepare("
            SELECT goi.*, p.name AS product_name
            FROM   guest_order_items goi
            JOIN   products p ON goi.product_id = p.id
            WHERE  goi.guest_order_id = ?
        ");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Fetch minimal item rows (product_id, quantity) — used when
     * decrementing stock after payment confirmation.
     */
    public function getItems(int $orderId): array
    {
        $stmt = $this->db->prepare(
            "SELECT product_id, quantity FROM guest_order_items WHERE guest_order_id = ?"
        );
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ------------------------------------------------------------------ writes

    /**
     * Insert a new guest order header row.
     * Returns the new order ID.
     */
    public function create(
        int   $guestId,
        int   $totalStocks,
        float $subtotal,
        float $discount,
        float $total
    ): int {
        $status = 'pending';
        $stmt   = $this->db->prepare("
            INSERT INTO guest_orders
                (guest_id, total_stocks, subtotal, discount_amount, total_amount, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('iiidds', $guestId, $totalStocks, $subtotal, $discount, $total, $status);
        $stmt->execute();
        return (int)$this->db->insert_id;
    }

    /** Append a single line item to an existing guest order. */
    public function addItem(
        int   $orderId,
        int   $productId,
        int   $quantity,
        float $unitPrice
    ): bool {
        $stmt = $this->db->prepare("
            INSERT INTO guest_order_items
                (guest_order_id, product_id, quantity, unit_price)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param('iiid', $orderId, $productId, $quantity, $unitPrice);
        return $stmt->execute();
    }

    /** Set the payment gateway transaction ID (written before redirect). */
    public function setTransactionId(int $orderId, string $tranId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE guest_orders SET tran_id = ? WHERE id = ?"
        );
        $stmt->bind_param('si', $tranId, $orderId);
        return $stmt->execute();
    }

    /** Mark an order as paid after the payment gateway callback. */
    public function markPaid(int $orderId, string $tranId, string $method = 'bKash/SSLCommerz'): bool
    {
        $stmt = $this->db->prepare("
            UPDATE guest_orders
            SET    status = 'paid', payment_method = ?
            WHERE  id = ? AND tran_id = ?
        ");
        $stmt->bind_param('sis', $method, $orderId, $tranId);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }
}
