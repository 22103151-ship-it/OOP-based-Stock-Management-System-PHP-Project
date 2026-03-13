<?php

namespace App\Services;

use App\Models\CustomerOrder;
use App\Models\Product;
use App\Models\PurchaseOrder;

class OrderReturnService
{
    private Product $productModel;
    private PurchaseOrder $purchaseOrderModel;
    private CustomerOrder $customerOrderModel;

    public function __construct(\mysqli $db)
    {
        $this->productModel = new Product($db);
        $this->purchaseOrderModel = new PurchaseOrder($db);
        $this->customerOrderModel = new CustomerOrder($db);
    }

    public function returnDeliveredSupplierOrder(int $orderId): void
    {
        $order = $this->purchaseOrderModel->findOneWithProduct($orderId, null);
        if (!$order || ($order['status'] ?? '') !== 'delivered') {
            return;
        }

        $this->purchaseOrderModel->updateStatus($orderId, 'returned');
        $this->productModel->updateStock((int)$order['product_id'], -((int)$order['quantity']));
    }

    public function returnDeliveredCustomerOrder(int $orderId): void
    {
        $order = $this->customerOrderModel->findById($orderId);
        if (!$order || ($order['status'] ?? '') !== 'delivered') {
            return;
        }

        $this->customerOrderModel->updateStatus($orderId, 'returned');
        $this->productModel->updateStock((int)$order['product_id'], (int)$order['quantity']);
    }
}
