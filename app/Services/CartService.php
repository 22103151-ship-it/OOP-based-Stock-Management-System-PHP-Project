<?php

namespace App\Services;

use App\Models\Product;

/**
 * CartService — all cart operations for registered customers.
 *
 * Encapsulates discount rules (VIP vs PRO), stock validation,
 * and every CRUD action on the `customer_cart` table.
 *
 * Usage:
 *   $cart = new CartService($conn, $customerId, $customerType);
 *   $cart->add($productId, $qty);
 *   $data = $cart->get();
 *   echo $data['total'];
 */
class CartService
{
    private \mysqli $db;
    private int     $customerId;
    private Product $productModel;

    // Discount configuration (driven by customer type)
    private int $baseDiscount;    // % applied always
    private int $bulkDiscount;    // % applied when total qty >= bulkThreshold
    private int $bulkThreshold;   // qty that triggers bulk discount
    private int $minPerProduct;   // minimum qty per individual product

    public function __construct(\mysqli $db, int $customerId, string $customerType = 'pro')
    {
        $this->db           = $db;
        $this->customerId   = $customerId;
        $this->productModel = new Product($db);

        $isVip                = ($customerType === 'vip');
        $this->baseDiscount   = $isVip ? 10 : 5;
        $this->bulkDiscount   = $isVip ? 20 : 15;
        $this->bulkThreshold  = $isVip ? 70 : 50;
        $this->minPerProduct  = $isVip ? 10 : 20;
    }

    // ------------------------------------------------------------------ info

    /** Return the discount configuration for display on the cart page. */
    public function getDiscountInfo(): array
    {
        return [
            'base_discount'   => $this->baseDiscount,
            'bulk_discount'   => $this->bulkDiscount,
            'bulk_threshold'  => $this->bulkThreshold,
            'min_per_product' => $this->minPerProduct,
        ];
    }

    // ------------------------------------------------------------------ add / update / remove

    /**
     * Add a product to the cart (or replace the quantity if already present).
     *
     * @return array{success: bool, message: string}
     */
    public function add(int $productId, int $quantity): array
    {
        if ($quantity < $this->minPerProduct) {
            return ['success' => false, 'message' => "Minimum {$this->minPerProduct} stocks per product required"];
        }

        $product = $this->productModel->findById($productId);
        if (!$product || $product['stock'] < $quantity) {
            return ['success' => false, 'message' => 'Not enough stock available'];
        }

        $check = $this->db->prepare(
            "SELECT id FROM customer_cart WHERE customer_id = ? AND product_id = ?"
        );
        $check->bind_param('ii', $this->customerId, $productId);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $stmt = $this->db->prepare(
                "UPDATE customer_cart SET quantity = ? WHERE customer_id = ? AND product_id = ?"
            );
            $stmt->bind_param('iii', $quantity, $this->customerId, $productId);
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO customer_cart (customer_id, product_id, quantity) VALUES (?, ?, ?)"
            );
            $stmt->bind_param('iii', $this->customerId, $productId, $quantity);
        }

        $stmt->execute();
        return ['success' => true, 'message' => 'Added to cart'];
    }

    /**
     * Update the quantity of an existing cart row.
     *
     * @return array{success: bool, message: string}
     */
    public function update(int $productId, int $quantity): array
    {
        if ($quantity < $this->minPerProduct) {
            return ['success' => false, 'message' => "Minimum {$this->minPerProduct} stocks per product"];
        }

        $stmt = $this->db->prepare(
            "UPDATE customer_cart SET quantity = ? WHERE customer_id = ? AND product_id = ?"
        );
        $stmt->bind_param('iii', $quantity, $this->customerId, $productId);
        $stmt->execute();
        return ['success' => true, 'message' => 'Cart updated'];
    }

    /** Remove one product from the cart. */
    public function remove(int $productId): array
    {
        $stmt = $this->db->prepare(
            "DELETE FROM customer_cart WHERE customer_id = ? AND product_id = ?"
        );
        $stmt->bind_param('ii', $this->customerId, $productId);
        $stmt->execute();
        return ['success' => true, 'message' => 'Removed from cart'];
    }

    /** Empty the whole cart. */
    public function clear(): array
    {
        $stmt = $this->db->prepare("DELETE FROM customer_cart WHERE customer_id = ?");
        $stmt->bind_param('i', $this->customerId);
        $stmt->execute();
        return ['success' => true, 'message' => 'Cart cleared'];
    }

    // ------------------------------------------------------------------ read

    /**
     * Fetch all cart items with computed totals and discounts.
     *
     * @return array{
     *   success: bool,
     *   items: array,
     *   total_qty: int,
     *   subtotal: float,
     *   discount_pct: int,
     *   discount: float,
     *   total: float
     * }
     */
    public function get(): array
    {
        $stmt = $this->db->prepare("
            SELECT cc.product_id, cc.quantity, p.name, p.price, p.stock, p.image
            FROM   customer_cart cc
            JOIN   products p ON cc.product_id = p.id
            WHERE  cc.customer_id = ?
        ");
        $stmt->bind_param('i', $this->customerId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $totalQty = 0;
        $subtotal = 0.0;
        $items    = [];

        foreach ($rows as $row) {
            $totalQty += (int)$row['quantity'];
            $subtotal += (float)$row['price'] * (int)$row['quantity'];
            $items[]   = $row;
        }

        $discountPct = ($totalQty >= $this->bulkThreshold) ? $this->bulkDiscount : $this->baseDiscount;
        $discount    = $subtotal * $discountPct / 100;
        $total       = $subtotal - $discount;

        return [
            'success'      => true,
            'items'        => $items,
            'total_qty'    => $totalQty,
            'subtotal'     => round($subtotal, 2),
            'discount_pct' => $discountPct,
            'discount'     => round($discount, 2),
            'total'        => round($total, 2),
        ];
    }

    // ------------------------------------------------------------------ validate

    /**
     * Check all cart items for minimum quantity and available stock.
     * Returns errors array (empty means valid).
     *
     * @return array{success: bool, errors?: string[], message?: string}
     */
    public function validate(): array
    {
        $stmt = $this->db->prepare("
            SELECT cc.product_id, cc.quantity, p.name, p.stock
            FROM   customer_cart cc
            JOIN   products p ON cc.product_id = p.id
            WHERE  cc.customer_id = ?
        ");
        $stmt->bind_param('i', $this->customerId);
        $stmt->execute();
        $rows   = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $errors = [];

        foreach ($rows as $row) {
            if ((int)$row['quantity'] < $this->minPerProduct) {
                $errors[] = "{$row['name']}: minimum {$this->minPerProduct} required";
            }
            if ((int)$row['stock'] < (int)$row['quantity']) {
                $errors[] = "{$row['name']}: only {$row['stock']} available";
            }
        }

        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }

        return ['success' => true, 'message' => 'Cart is valid'];
    }
}
