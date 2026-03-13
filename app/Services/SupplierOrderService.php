<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;

/**
 * Encapsulates supplier order status updates and stock synchronization.
 */
class SupplierOrderService
{
    private \mysqli $db;
    private PurchaseOrder $purchaseOrderModel;
    private Product $productModel;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->purchaseOrderModel = new PurchaseOrder($db);
        $this->productModel = new Product($db);
    }

    /**
     * @param array<int,string> $statusMap
     * @return array{pending_remaining:int,new_delivered:int}
     */
    public function updateStatuses(?int $supplierId, array $statusMap): array
    {
        $newDelivered = 0;

        foreach ($statusMap as $orderIdRaw => $statusRaw) {
            $orderId = (int)$orderIdRaw;
            $status = (string)$statusRaw;
            if (!in_array($status, ['pending', 'delivered'], true)) {
                continue;
            }

            $order = $this->purchaseOrderModel->findOneWithProduct($orderId, $supplierId);
            if (!$order) {
                continue;
            }

            $prevStatus = (string)$order['status'];
            $productId = (int)$order['product_id'];
            $quantity = (int)$order['quantity'];

            if ($supplierId !== null && $supplierId > 0) {
                $this->purchaseOrderModel->updateStatusForSupplier($orderId, $supplierId, $status);
            } else {
                $this->purchaseOrderModel->updateStatus($orderId, $status);
            }

            if ($prevStatus !== 'delivered' && $status === 'delivered') {
                $this->productModel->updateStock($productId, $quantity);
                $newDelivered++;
            } elseif ($prevStatus === 'delivered' && $status !== 'delivered') {
                $this->productModel->updateStock($productId, -$quantity);
            }
        }

        $pendingRemaining = $this->purchaseOrderModel->countPending($supplierId);
        return ['pending_remaining' => $pendingRemaining, 'new_delivered' => $newDelivered];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getOrders(?int $supplierId): array
    {
        return $this->purchaseOrderModel->findBySupplierDetailed($supplierId);
    }

    public function syncDeliveredStock(?int $supplierId): void
    {
        if ($supplierId !== null && $supplierId > 0) {
            $stmt = $this->db->prepare(" 
                SELECT po.id, po.product_id, po.quantity
                FROM purchase_orders po
                WHERE po.status = 'delivered' AND po.stock_updated = 0 AND po.supplier_id = ?
            ");
            $stmt->bind_param('i', $supplierId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            $result = $this->db->query(" 
                SELECT po.id, po.product_id, po.quantity
                FROM purchase_orders po
                WHERE po.status = 'delivered' AND po.stock_updated = 0
            ");
            $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }

        foreach ($rows as $order) {
            $this->productModel->updateStock((int)$order['product_id'], (int)$order['quantity']);
            $stmt = $this->db->prepare('UPDATE purchase_orders SET stock_updated = 1 WHERE id = ?');
            $id = (int)$order['id'];
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function getDeliveredOrders(?int $supplierId): array
    {
        if ($supplierId !== null && $supplierId > 0) {
            $stmt = $this->db->prepare(" 
                SELECT po.id, p.name AS product_name, po.quantity, po.status, po.created_at
                FROM purchase_orders po
                JOIN products p ON po.product_id = p.id
                WHERE po.status = 'delivered' AND po.supplier_id = ?
                ORDER BY po.id DESC
            ");
            $stmt->bind_param('i', $supplierId);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        $result = $this->db->query(" 
            SELECT po.id, p.name AS product_name, po.quantity, po.status, po.created_at
            FROM purchase_orders po
            JOIN products p ON po.product_id = p.id
            WHERE po.status = 'delivered'
            ORDER BY po.id DESC
        ");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /** @return array<int,array<string,mixed>> */
    public function getOrdersByStatus(string $status, ?int $supplierId): array
    {
        if ($supplierId !== null && $supplierId > 0) {
            $stmt = $this->db->prepare(" 
                SELECT po.id, p.name AS product_name, po.quantity, po.status, po.created_at
                FROM purchase_orders po
                JOIN products p ON po.product_id = p.id
                WHERE po.status = ? AND po.supplier_id = ?
                ORDER BY po.id DESC
            ");
            $stmt->bind_param('si', $status, $supplierId);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }

        $stmt = $this->db->prepare(" 
            SELECT po.id, p.name AS product_name, po.quantity, po.status, po.created_at
            FROM purchase_orders po
            JOIN products p ON po.product_id = p.id
            WHERE po.status = ?
            ORDER BY po.id DESC
        ");
        $stmt->bind_param('s', $status);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function findInvoiceOrder(int $orderId, ?int $supplierId): ?array
    {
        if ($supplierId !== null && $supplierId > 0) {
            $stmt = $this->db->prepare(" 
                SELECT po.id, po.quantity, po.created_at, p.name AS product_name, p.price
                FROM purchase_orders po
                JOIN products p ON po.product_id = p.id
                WHERE po.id = ? AND po.supplier_id = ?
                LIMIT 1
            ");
            $stmt->bind_param('ii', $orderId, $supplierId);
            $stmt->execute();
            return $stmt->get_result()->fetch_assoc() ?: null;
        }

        $stmt = $this->db->prepare(" 
            SELECT po.id, po.quantity, po.created_at, p.name AS product_name, p.price
            FROM purchase_orders po
            JOIN products p ON po.product_id = p.id
            WHERE po.id = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }
}
