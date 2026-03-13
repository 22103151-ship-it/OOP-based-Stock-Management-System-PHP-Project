<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SellProduct;

class SellProductService
{
    private Product $productModel;
    private SellProduct $sellProductModel;

    public function __construct(\mysqli $db)
    {
        $this->productModel = new Product($db);
        $this->sellProductModel = new SellProduct($db);
    }

    public function sell(int $productId, int $quantity): string
    {
        $product = $this->productModel->findById($productId);
        if (!$product) {
            return "<p style='color:red;'>Invalid product selected.</p>";
        }

        $stock = (int)$product['stock'];
        if ($quantity > $stock) {
            return "<p style='color:red;'>Stock not sufficient! Available: {$stock}</p>";
        }

        $this->productModel->updateStock($productId, -$quantity);
        $this->sellProductModel->create($productId, (string)$product['name'], $quantity, (float)$product['price']);

        return "<p style='color:green;'>Product sold successfully!</p>";
    }

    /** @return array<int,array<string,mixed>> */
    public function getProductsForSell(): array
    {
        return $this->productModel->findAll('name ASC');
    }

    /** @return array<int,array<string,mixed>> */
    public function getHistory(): array
    {
        return $this->sellProductModel->getHistoryWithCurrentStock();
    }

    public function getSaleById(int $id): ?array
    {
        return $this->sellProductModel->findByIdWithCurrentStock($id);
    }
}
