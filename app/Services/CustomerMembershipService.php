<?php

namespace App\Services;

use App\Models\Customer;

class CustomerMembershipService
{
    private \mysqli $db;
    private Customer $customerModel;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->customerModel = new Customer($db);
    }

    /** @return array<string,mixed>|null */
    public function getCustomer(int $customerId): ?array
    {
        return $this->customerModel->findById($customerId);
    }

    /** @return array{ok:bool,payment_id?:int,tran_id?:string,error?:string} */
    public function createPendingMembershipPayment(int $customerId, float $amount): array
    {
        if ($customerId <= 0 || $amount < 100) {
            return ['ok' => false, 'error' => 'Minimum membership fee is ৳100'];
        }

        $tranId = 'MEM' . $customerId . 'T' . time();
        $stmt = $this->db->prepare(
            "INSERT INTO membership_payments (customer_id, amount, tran_id, status) VALUES (?, ?, ?, 'pending')"
        );
        $stmt->bind_param('ids', $customerId, $amount, $tranId);
        $ok = $stmt->execute();
        $paymentId = (int)$this->db->insert_id;
        $stmt->close();

        if (!$ok || $paymentId <= 0) {
            return ['ok' => false, 'error' => 'Unable to create membership payment record'];
        }

        return ['ok' => true, 'payment_id' => $paymentId, 'tran_id' => $tranId];
    }
}
