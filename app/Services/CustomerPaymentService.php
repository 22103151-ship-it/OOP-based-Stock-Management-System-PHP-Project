<?php

namespace App\Services;

/**
 * Central service for customer payment callback flows.
 */
class CustomerPaymentService
{
    private \mysqli $db;
    private ?SSLCommerzService $ssl;

    public function __construct(\mysqli $db, ?SSLCommerzService $ssl = null)
    {
        $this->db = $db;
        $this->ssl = $ssl;
    }

    /**
     * Complete membership payment and activate membership.
     *
     * @return array{ok: bool, error?: string}
     */
    public function completeMembershipPayment(string $tranId, int $customerId, int $paymentId, string $gatewayStatus, string $valId = ''): array
    {
        if ($tranId === '' || $paymentId <= 0 || $customerId <= 0) {
            return ['ok' => false, 'error' => 'Missing payment callback data'];
        }

        if (!$this->isGatewayStatusValid($gatewayStatus)) {
            return ['ok' => false, 'error' => 'Payment validation failed'];
        }

        if ($valId !== '' && $this->ssl !== null) {
            $validation = $this->ssl->validateTransaction($valId);
            if (empty($validation['ok'])) {
                return ['ok' => false, 'error' => 'Payment validation failed'];
            }
            $vStatus = strtoupper((string)($validation['data']['status'] ?? ''));
            if (!in_array($vStatus, ['VALID', 'VALIDATED'], true)) {
                return ['ok' => false, 'error' => 'Payment validation failed'];
            }
        }

        $payStmt = $this->db->prepare('SELECT amount FROM membership_payments WHERE id = ? AND tran_id = ? LIMIT 1');
        $payStmt->bind_param('is', $paymentId, $tranId);
        $payStmt->execute();
        $payment = $payStmt->get_result()->fetch_assoc();
        $payStmt->close();

        if (!$payment) {
            return ['ok' => false, 'error' => 'Payment validation failed'];
        }

        $amount = (float)$payment['amount'];

        $this->db->begin_transaction();
        try {
            $updPay = $this->db->prepare("UPDATE membership_payments SET status = 'completed' WHERE id = ?");
            $updPay->bind_param('i', $paymentId);
            $updPay->execute();
            $updPay->close();

            $updCustomer = $this->db->prepare(
                'UPDATE customers SET is_member = 1, membership_fee_paid = membership_fee_paid + ?, membership_date = NOW() WHERE id = ?'
            );
            $updCustomer->bind_param('di', $amount, $customerId);
            $updCustomer->execute();
            $updCustomer->close();

            $this->db->commit();
            return ['ok' => true];
        } catch (\Throwable $e) {
            $this->db->rollback();
            return ['ok' => false, 'error' => 'Payment validation failed'];
        }
    }

    /**
     * Confirm a successful order payment callback and return order row.
     *
     * @return array{ok: bool, order?: array<string,mixed>, error?: string}
     */
    public function confirmOrderPayment(int $orderId, string $gatewayStatus, string $valId = ''): array
    {
        if ($orderId <= 0) {
            return ['ok' => false, 'error' => 'Invalid order'];
        }

        if (!$this->isGatewayStatusValid($gatewayStatus)) {
            return ['ok' => false, 'error' => 'Payment was not successful'];
        }

        if ($valId !== '' && $this->ssl !== null) {
            $validation = $this->ssl->validateTransaction($valId);
            if (empty($validation['ok'])) {
                return ['ok' => false, 'error' => 'Payment validation failed'];
            }
            $vStatus = strtoupper((string)($validation['data']['status'] ?? ''));
            if (!in_array($vStatus, ['VALID', 'VALIDATED'], true)) {
                return ['ok' => false, 'error' => 'Payment not valid'];
            }
        }

        $update = $this->db->prepare("UPDATE customer_orders SET status = 'confirmed' WHERE id = ?");
        $update->bind_param('i', $orderId);
        $update->execute();
        $update->close();

        $orderStmt = $this->db->prepare('SELECT * FROM customer_orders WHERE id = ? LIMIT 1');
        $orderStmt->bind_param('i', $orderId);
        $orderStmt->execute();
        $order = $orderStmt->get_result()->fetch_assoc();
        $orderStmt->close();

        return ['ok' => true, 'order' => $order ?: []];
    }

    public function markOrderFailed(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }
        $update = $this->db->prepare("UPDATE customer_orders SET status = 'payment_failed' WHERE id = ?");
        $update->bind_param('i', $orderId);
        $update->execute();
        $update->close();
    }

    public function markOrderCancelled(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }
        $update = $this->db->prepare("UPDATE customer_orders SET status = 'cancelled' WHERE id = ?");
        $update->bind_param('i', $orderId);
        $update->execute();
        $update->close();
    }

    public function resolveCustomerIdByUserId(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? (int)$row['id'] : null;
    }

    /** @return array<string,mixed>|null */
    public function findOrderForPayment(int $orderId, int $customerId): ?array
    {
        if ($orderId <= 0 || $customerId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT co.*, p.name AS product_name, p.price AS unit_price
             FROM customer_orders co
             JOIN products p ON co.product_id = p.id
             WHERE co.id = ? AND co.customer_id = ?
             LIMIT 1'
        );
        $stmt->bind_param('ii', $orderId, $customerId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $order;
    }

    /** @return array{subtotal:float,delivery_charge:float,tax_rate:float,tax_amount:float,total:float} */
    public function calculatePayable(array $order): array
    {
        $subtotal = (float)($order['unit_price'] ?? 0) * (int)($order['quantity'] ?? 0);
        $deliveryCharge = 60.0;
        $taxRate = 0.05;
        $taxAmount = $subtotal * $taxRate;
        $total = $subtotal + $deliveryCharge + $taxAmount;

        return [
            'subtotal' => $subtotal,
            'delivery_charge' => $deliveryCharge,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ];
    }

    /** @return array{name:string,email:string,phone:string} */
    public function getCustomerContact(int $customerId): array
    {
        $stmt = $this->db->prepare('SELECT name, email, phone FROM customers WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        $phone = preg_replace('/\D+/', '', (string)($row['phone'] ?? ''));
        if (strlen($phone) < 10) {
            $phone = '01700000000';
        }

        return [
            'name' => (string)($row['name'] ?? 'Customer'),
            'email' => (string)($row['email'] ?? 'customer@example.com'),
            'phone' => $phone,
        ];
    }

    public function createInitiatedCustomerPayment(int $orderId, int $customerId, string $tranId, float $amount): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO customer_payments (order_id, customer_id, gateway, tran_id, amount, currency, status)
             VALUES (?, ?, 'sslcommerz', ?, ?, 'BDT', 'initiated')"
        );
        $stmt->bind_param('iisd', $orderId, $customerId, $tranId, $amount);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /** @return array<string,mixed>|null */
    public function findMappedOrderByTranId(string $tranId): ?array
    {
        if ($tranId === '') {
            return null;
        }
        $stmt = $this->db->prepare('SELECT order_id, customer_id FROM customer_payments WHERE tran_id = ? LIMIT 1');
        $stmt->bind_param('s', $tranId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $row;
    }

    public function markPaymentByTranId(string $tranId, string $status): bool
    {
        if ($tranId === '') {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE customer_payments SET status = ? WHERE tran_id = ? LIMIT 1');
        $stmt->bind_param('ss', $status, $tranId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function markPaymentSuccessMeta(string $tranId, string $valId, string $bankTranId, string $cardType): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE customer_payments SET status='success', val_id=?, bank_tran_id=?, card_type=? WHERE tran_id = ? LIMIT 1"
        );
        $stmt->bind_param('ssss', $valId, $bankTranId, $cardType, $tranId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    private function isGatewayStatusValid(string $status): bool
    {
        $s = strtoupper($status);
        return in_array($s, ['VALID', 'VALIDATED'], true);
    }
}
