<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StaffOrder;

class StaffOrderService
{
    private Product $productModel;
    private StaffOrder $staffOrderModel;

    public function __construct(\mysqli $db)
    {
        $this->productModel = new Product($db);
        $this->staffOrderModel = new StaffOrder($db);
    }

    /** @return array<int,array<string,mixed>> */
    public function getProducts(): array
    {
        return $this->productModel->findAll('name ASC');
    }

    /** @return array<int,array<string,mixed>> */
    public function getOrders(): array
    {
        return $this->staffOrderModel->findAll('id DESC');
    }

    public function addOrder(string $productName, int $quantity): bool
    {
        return $this->staffOrderModel->create($productName, $quantity, 'pending') > 0;
    }

    public function editOrder(int $id, string $productName, int $quantity, string $status): bool
    {
        return $this->staffOrderModel->updateOrder($id, $productName, $quantity, $status);
    }

    public function deleteOrder(int $id): bool
    {
        return $this->staffOrderModel->deleteById($id);
    }

    public function findOrder(int $id): ?array
    {
        return $this->staffOrderModel->findById($id);
    }
}
