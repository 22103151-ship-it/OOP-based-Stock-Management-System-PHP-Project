<?php

namespace App\Services;

use App\Models\Customer;

class CustomerCheckoutService
{
    private \mysqli $db;
    private Customer $customerModel;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->customerModel = new Customer($db);
    }

    /**
     * @return array{
     *   customer: array<string,mixed>,
     *   is_member: bool,
     *   items: array<int,array<string,mixed>>,
     *   total_stocks: int,
     *   subtotal: float,
     *   discount_percent: int,
     *   discount_amount: float,
     *   total: float,
     *   min_per_product: int,
     *   min_required: int,
     *   errors: array<int,string>
     * }
     */
    public function prepareCheckout(int $customerId): array
    {
        $customer = $this->customerModel->findById($customerId) ?? [];
        $isMember = (bool)($customer['is_member'] ?? false);

        $cartStmt = $this->db->prepare(
            'SELECT cc.*, p.name, p.price, p.stock
             FROM customer_cart cc
             JOIN products p ON cc.product_id = p.id
             WHERE cc.customer_id = ?'
        );
        $cartStmt->bind_param('i', $customerId);
        $cartStmt->execute();
        $items = $cartStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $totalStocks = 0;
        $subtotal = 0.0;
        foreach ($items as $item) {
            $totalStocks += (int)$item['quantity'];
            $subtotal += (float)$item['price'] * (int)$item['quantity'];
        }

        $discountPercent = 0;
        if ($isMember) {
            if ($totalStocks >= 100) {
                $discountPercent = 20;
            } elseif ($totalStocks >= 30) {
                $discountPercent = 15;
            }
        }

        $discountAmount = $subtotal * ($discountPercent / 100);
        $total = $subtotal - $discountAmount;
        $minPerProduct = 10;
        $minRequired = $isMember ? 30 : 100;

        $errors = [];
        if (empty($items)) {
            $errors[] = 'Cart is empty';
        }

        foreach ($items as $item) {
            if ((int)$item['quantity'] < $minPerProduct) {
                $errors[] = $item['name'] . ' requires minimum ' . $minPerProduct . ' stocks';
            }
            if ((int)$item['stock'] < (int)$item['quantity']) {
                $errors[] = $item['name'] . ' has insufficient stock';
            }
        }

        if ($totalStocks < $minRequired) {
            $errors[] = 'Minimum ' . $minRequired . ' total stocks required';
        }

        return [
            'customer' => $customer,
            'is_member' => $isMember,
            'items' => $items,
            'total_stocks' => $totalStocks,
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'min_per_product' => $minPerProduct,
            'min_required' => $minRequired,
            'errors' => $errors,
        ];
    }

    /** @return array{success: bool, order_ids?: array<int,int>, errors?: array<int,string>} */
    public function placeOrders(int $customerId, array $items, int $discountPercent, string $paymentMethod): array
    {
        $this->db->begin_transaction();

        try {
            $orderIds = [];
            foreach ($items as $item) {
                $itemDiscountAmount = ((float)$item['price'] * (int)$item['quantity']) * ($discountPercent / 100);
                $status = ($paymentMethod === 'sslcommerz') ? 'pending_payment' : 'pending';

                $orderStmt = $this->db->prepare(
                    'INSERT INTO customer_orders (customer_id, product_id, quantity, price, discount_percent, discount_amount, total_stocks, status, order_date)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );

                $customerIdParam = (int)$customerId;
                $productIdParam = (int)$item['product_id'];
                $quantityParam = (int)$item['quantity'];
                $priceParam = (float)$item['price'];
                $discountPercentParam = (float)$discountPercent;
                $itemDiscountParam = (float)$itemDiscountAmount;
                $totalStocksParam = (int)$item['quantity'];
                $statusParam = $status;

                $orderStmt->bind_param(
                    'iiidddis',
                    $customerIdParam,
                    $productIdParam,
                    $quantityParam,
                    $priceParam,
                    $discountPercentParam,
                    $itemDiscountParam,
                    $totalStocksParam,
                    $statusParam
                );
                $orderStmt->execute();
                $orderIds[] = (int)$this->db->insert_id;
                $orderStmt->close();
            }

            if ($paymentMethod !== 'sslcommerz') {
                $clearStmt = $this->db->prepare('DELETE FROM customer_cart WHERE customer_id = ?');
                $clearStmt->bind_param('i', $customerId);
                $clearStmt->execute();
                $clearStmt->close();
            }

            $this->db->commit();
            return ['success' => true, 'order_ids' => $orderIds];
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'errors' => ['Checkout failed: ' . $e->getMessage()]];
        }
    }
}
