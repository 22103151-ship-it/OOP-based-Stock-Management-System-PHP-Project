<?php

namespace App\Services;

use App\Models\StaffOrder;

class AdminOrderService
{
    private \mysqli $db;
    private StaffOrder $staffOrderModel;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->staffOrderModel = new StaffOrder($db);
    }

    /** @return array<int,array<string,mixed>> */
    public function getDeliveredOrdersCombined(): array
    {
        $supplierDelivered = $this->db->query(
            "SELECT po.id, po.product_id, p.name AS product_name, po.quantity, po.status, po.created_at, p.price
             FROM purchase_orders po
             JOIN products p ON po.product_id = p.id
             WHERE po.status = 'delivered'
             ORDER BY po.created_at DESC"
        );

        $customerDelivered = $this->db->query(
            "SELECT co.id, co.product_id, p.name AS product_name, co.quantity, co.status, co.order_date AS created_at, co.price
             FROM customer_orders co
             JOIN products p ON co.product_id = p.id
             WHERE co.status = 'delivered'
             ORDER BY co.order_date DESC"
        );

        $rows = [];
        if ($supplierDelivered) {
            foreach ($supplierDelivered->fetch_all(MYSQLI_ASSOC) as $row) {
                $row['source'] = 'supplier';
                $rows[] = $row;
            }
        }
        if ($customerDelivered) {
            foreach ($customerDelivered->fetch_all(MYSQLI_ASSOC) as $row) {
                $row['source'] = 'customer';
                $rows[] = $row;
            }
        }

        usort($rows, static function (array $a, array $b): int {
            return strtotime((string)$b['created_at']) <=> strtotime((string)$a['created_at']);
        });

        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function getStaffOrders(): array
    {
        return $this->staffOrderModel->findAll('created_at DESC');
    }

    /** @param array<string,string> $statusMap */
    public function updateStaffOrderStatuses(array $statusMap): void
    {
        foreach ($statusMap as $orderIdRaw => $status) {
            $orderId = (int)$orderIdRaw;
            if (!in_array($status, ['Pending', 'Delivered'], true)) {
                continue;
            }
            $this->staffOrderModel->updateStatus($orderId, $status);
        }
    }

    public function recordPurchasePayment(string $transactionId, string $paymentType, string $transactionDate): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO admin_payments (transaction_id, payment_type, transaction_date) VALUES (?, ?, ?)'
        );
        $stmt->bind_param('sss', $transactionId, $paymentType, $transactionDate);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function markPurchaseOrderPaid(int $orderId): bool
    {
        $stmt = $this->db->prepare("UPDATE purchase_orders SET payment_status='Paid' WHERE id = ?");
        $stmt->bind_param('i', $orderId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
