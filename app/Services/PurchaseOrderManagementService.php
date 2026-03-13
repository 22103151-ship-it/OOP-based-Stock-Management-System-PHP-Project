<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;

class PurchaseOrderManagementService
{
    private \mysqli $db;
    private Product $productModel;
    private Supplier $supplierModel;
    private PurchaseOrder $purchaseOrderModel;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->productModel = new Product($db);
        $this->supplierModel = new Supplier($db);
        $this->purchaseOrderModel = new PurchaseOrder($db);
    }

    /** @return array<int,array<string,mixed>> */
    public function getProducts(): array
    {
        return $this->productModel->findAll('name ASC');
    }

    /** @return array<int,array<string,mixed>> */
    public function getSuppliers(): array
    {
        return $this->supplierModel->findAll('name ASC');
    }

    public function createFromAdminForm(int $productId, int $supplierId, int $quantity): array
    {
        if ($productId <= 0 || $supplierId <= 0 || $quantity <= 0) {
            return ['ok' => false, 'message' => '<div class="error-message">Please select product, supplier, and quantity.</div>'];
        }

        $hasSupplierColumn = false;
        $colCheck = $this->db->query("SHOW COLUMNS FROM purchase_orders LIKE 'supplier_id'");
        if ($colCheck && $colCheck->num_rows > 0) {
            $hasSupplierColumn = true;
        } else {
            $alter = $this->db->query('ALTER TABLE purchase_orders ADD COLUMN supplier_id INT(11) DEFAULT NULL');
            if ($alter) {
                $hasSupplierColumn = true;
            }
        }

        if ($hasSupplierColumn) {
            $stmt = $this->db->prepare("INSERT INTO purchase_orders (product_id, supplier_id, quantity, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
            $stmt->bind_param('iii', $productId, $supplierId, $quantity);
            $ok = $stmt->execute();
            $stmt->close();
            return ['ok' => $ok, 'message' => $ok ? '<div class="success-message">Purchase order created successfully.</div>' : '<div class="error-message">Failed to create order. Please try again.</div>'];
        }

        $stmt = $this->db->prepare("INSERT INTO purchase_orders (product_id, quantity, status, created_at) VALUES (?, ?, 'pending', NOW())");
        $stmt->bind_param('ii', $productId, $quantity);
        $ok = $stmt->execute();
        $stmt->close();
        return ['ok' => $ok, 'message' => $ok ? '<div class="success-message">Purchase order created successfully (supplier not linked).</div>' : '<div class="error-message">Failed to create order. Please try again.</div>'];
    }

    public function addOrder(int $productId, int $quantity): bool
    {
        $stmt = $this->db->prepare("INSERT INTO purchase_orders (product_id, quantity, status, created_at) VALUES (?, ?, 'pending', NOW())");
        $stmt->bind_param('ii', $productId, $quantity);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function editOrder(int $id, int $productId, int $quantity, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE purchase_orders SET product_id=?, quantity=?, status=? WHERE id=?');
        $stmt->bind_param('iisi', $productId, $quantity, $status, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteOrder(int $id): bool
    {
        return $this->purchaseOrderModel->deleteById($id);
    }

    /** @return array<int,array<string,mixed>> */
    public function getDetailedOrders(): array
    {
        $result = $this->db->query('SELECT po.id, p.name AS product_name, po.quantity, po.status, po.created_at FROM purchase_orders po JOIN products p ON po.product_id = p.id ORDER BY po.id DESC');
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function findOrder(int $id): ?array
    {
        return $this->purchaseOrderModel->findById($id);
    }

    /** @param array<string,string> $statusMap */
    public function updateStatuses(array $statusMap): void
    {
        foreach ($statusMap as $orderIdRaw => $status) {
            $orderId = (int)$orderIdRaw;
            if ($orderId <= 0 || $status === '') {
                continue;
            }

            $stmt = $this->db->prepare('UPDATE purchase_orders SET status = ? WHERE id = ?');
            $stmt->bind_param('si', $status, $orderId);
            $stmt->execute();
            $stmt->close();
        }
    }
}
