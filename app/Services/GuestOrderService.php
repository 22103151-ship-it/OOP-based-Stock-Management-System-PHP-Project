<?php

namespace App\Services;

use App\Models\GuestCustomer;
use App\Models\GuestOrder;
use App\Models\Product;

/**
 * GuestOrderService — complete guest-checkout flow as one service object.
 *
 * Covers:
 *  1. OTP generation and delivery (sendOTP)
 *  2. OTP verification          (verifyOTP)
 *  3. Product catalogue browse  (getProducts)
 *  4. Order checkout            (checkout)
 *  5. Post-payment stock decrement (markPaid)
 *
 * Discount rule for guests: ৳1000 off per 100 stocks ordered.
 */
class GuestOrderService
{
    private \mysqli       $db;
    private GuestCustomer $guestModel;
    private GuestOrder    $orderModel;
    private Product       $productModel;

    public function __construct(\mysqli $db)
    {
        $this->db           = $db;
        $this->guestModel   = new GuestCustomer($db);
        $this->orderModel   = new GuestOrder($db);
        $this->productModel = new Product($db);
    }

    // ------------------------------------------------------------------ OTP

    /**
     * Generate and persist a 4-digit OTP for the given phone number.
     * In production replace the returned 'otp' with an SMS API call.
     *
     * @return array{success: bool, otp?: string, message: string}
     */
    public function sendOTP(string $name, string $phone, string $sessionId): array
    {
        if (strlen($phone) !== 11 || !ctype_digit($phone)) {
            return ['success' => false, 'message' => 'Invalid phone number. Must be 11 digits.'];
        }

        if (trim($name) === '') {
            return ['success' => false, 'message' => 'Name is required.'];
        }

        $otp     = (string)random_int(1000, 9999);
        $expires = date('Y-m-d H:i:s', time() + 300); // 5-minute window

        $this->guestModel->upsertWithOTP($name, $phone, $otp, $expires, $sessionId);

        return ['success' => true, 'otp' => $otp, 'message' => 'OTP sent successfully'];
    }

    /**
     * Verify the OTP supplied by the guest.
     *
     * @return array{success: bool, guest_id?: int, guest_name?: string, message: string}
     */
    public function verifyOTP(string $sessionId, string $otp, ?string $phone = null): array
    {
        if ($phone !== null && $phone !== '') {
            $stmt = $this->db->prepare(
                "SELECT id, name, phone, otp_code, otp_expires
                 FROM   guest_customers
                 WHERE  phone = ? AND otp_code = ?"
            );
            $stmt->bind_param('ss', $phone, $otp);
        } else {
            $stmt = $this->db->prepare(
                "SELECT id, name, phone, otp_code, otp_expires
                 FROM   guest_customers
                 WHERE  session_id = ? AND otp_code = ?"
            );
            $stmt->bind_param('ss', $sessionId, $otp);
        }

        $stmt->execute();
        $guest = $stmt->get_result()->fetch_assoc();

        if (!$guest) {
            return ['success' => false, 'message' => 'Invalid OTP'];
        }

        if (strtotime($guest['otp_expires']) < time()) {
            return ['success' => false, 'message' => 'OTP expired. Please request a new one.'];
        }

        $this->guestModel->verifyOTP((int)$guest['id']);

        return [
            'success'    => true,
            'guest_id'   => (int)$guest['id'],
            'guest_name' => $guest['name'],
            'message'    => 'OTP verified successfully',
        ];
    }

    // ------------------------------------------------------------------ catalogue

    /** Return all in-stock products for the guest product grid. */
    public function getProducts(): array
    {
        return $this->productModel->findInStock();
    }

    /** @return array<string,mixed>|null */
    public function findVerifiedGuestBySession(string $sessionId): ?array
    {
        if ($sessionId === '') {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT id, name, phone FROM guest_customers WHERE session_id = ? AND otp_verified = 1 LIMIT 1'
        );
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $row;
    }

    public function attachTranId(int $orderId, string $tranId): bool
    {
        $stmt = $this->db->prepare('UPDATE guest_orders SET tran_id = ? WHERE id = ?');
        $stmt->bind_param('si', $tranId, $orderId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ------------------------------------------------------------------ checkout

    /**
     * Process a guest checkout atomically inside a transaction.
     *
     * @param  int    $guestId
     * @param  array  $cartItems  Each item: ['product_id' => int, 'quantity' => int]
     * @return array{
     *   success: bool,
     *   order_id?: int,
     *   total_stocks?: int,
     *   subtotal?: float,
     *   discount?: float,
     *   total?: float,
     *   message?: string
     * }
     */
    public function checkout(int $guestId, array $cartItems): array
    {
        if (empty($cartItems)) {
            return ['success' => false, 'message' => 'Cart is empty'];
        }

        // Validate stock and compute totals
        $totalStocks = 0;
        $subtotal    = 0.0;
        $validated   = [];

        foreach ($cartItems as $item) {
            $productId = (int)$item['product_id'];
            $quantity  = (int)$item['quantity'];
            $product   = $this->productModel->findById($productId);

            if (!$product) {
                return ['success' => false, 'message' => "Product #{$productId} not found"];
            }

            if ((int)$product['stock'] < $quantity) {
                return ['success' => false, 'message' => "Insufficient stock for: {$product['name']}"];
            }

            $totalStocks += $quantity;
            $subtotal    += (float)$product['price'] * $quantity;
            $validated[]  = [
                'product_id' => $productId,
                'quantity'   => $quantity,
                'price'      => (float)$product['price'],
            ];
        }

        // Discount: ৳1000 per 100 stocks
        $discount = floor($totalStocks / 100) * 1000;
        $total    = $subtotal - $discount;

        $this->db->begin_transaction();

        try {
            $orderId = $this->orderModel->create($guestId, $totalStocks, $subtotal, $discount, $total);

            foreach ($validated as $item) {
                $this->orderModel->addItem($orderId, $item['product_id'], $item['quantity'], $item['price']);
            }

            $this->guestModel->incrementOrderStats($guestId, $totalStocks);
            $this->db->commit();

            return [
                'success'      => true,
                'order_id'     => $orderId,
                'total_stocks' => $totalStocks,
                'subtotal'     => round($subtotal, 2),
                'discount'     => round($discount, 2),
                'total'        => round($total, 2),
            ];
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Order failed: ' . $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------ post-payment

    /**
     * Mark the order as paid and decrement product stock.
     * Called from the SSLCommerz success callback.
     */
    public function markPaid(int $orderId, string $tranId): bool
    {
        if (!$this->orderModel->markPaid($orderId, $tranId)) {
            return false;
        }

        $items = $this->orderModel->getItems($orderId);
        foreach ($items as $item) {
            $this->productModel->decrementStock((int)$item['product_id'], (int)$item['quantity']);
        }

        return true;
    }
}
