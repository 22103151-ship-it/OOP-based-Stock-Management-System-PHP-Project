<?php

namespace App\Services;

use App\Core\Auth;
use App\Models\User;

/**
 * Handles paid customer registration lifecycle: pending -> payment -> activation.
 */
class CustomerRegistrationService
{
    private \mysqli $db;
    private SSLCommerzService $ssl;
    private User $userModel;
    private string $currency;
    private string $storeId;
    private string $storePass;

    public function __construct(\mysqli $db, string $storeId, string $storePass, bool $sandbox, string $currency = 'BDT')
    {
        $this->db = $db;
        $this->ssl = new SSLCommerzService($storeId, $storePass, $sandbox);
        $this->userModel = new User($db);
        $this->currency = $currency;
        $this->storeId = $storeId;
        $this->storePass = $storePass;
    }

    /**
     * @param array{name:string,phone:string,email:string,nid:string,password:string,type:string,fee:float,cancel_path:string} $data
     * @return array{ok: bool, gateway_url?: string, error?: string}
     */
    public function initiatePaidRegistration(array $data): array
    {
        $check = $this->db->prepare('SELECT id FROM customers WHERE phone = ? OR email = ? OR nid = ? LIMIT 1');
        $check->bind_param('sss', $data['phone'], $data['email'], $data['nid']);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($exists) {
            return ['ok' => false, 'error' => 'Phone, Email or NID already registered'];
        }

        $prefix = strtoupper($data['type']) === 'VIP' ? 'VIP' : 'PRO';
        $tranId = $prefix . time() . rand(1000, 9999);
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        $pending = $this->db->prepare(
            'INSERT INTO pending_registrations (tran_id, name, email, phone, nid, password, customer_type, fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $pending->bind_param(
            'sssssssd',
            $tranId,
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['nid'],
            $hashedPassword,
            $data['type'],
            $data['fee']
        );
        $pending->execute();
        $pending->close();

        $https    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script   = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = (strpos($script, '/stock/') !== false) ? '/stock' : rtrim(dirname($script), '/\\');
        $base     = $https . '://' . $host . $basePath;

        $payload = [
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePass,
            'total_amount' => $data['fee'],
            'currency' => $this->currency,
            'tran_id' => $tranId,
            'success_url' => $base . '/customer/registration_success.php',
            'fail_url' => $base . '/customer/registration_fail.php',
            'cancel_url' => $base . $data['cancel_path'],
            'shipping_method' => 'NO',
            'product_name' => strtoupper($data['type']) === 'VIP' ? 'VIP Customer Registration' : 'Pro Customer Registration',
            'product_category' => 'Membership',
            'product_profile' => 'general',
            'cus_name' => $data['name'],
            'cus_email' => $data['email'],
            'cus_phone' => $data['phone'],
            'cus_add1' => 'Bangladesh',
            'cus_city' => 'Dhaka',
            'cus_postcode' => '1200',
            'cus_country' => 'Bangladesh',
            'value_a' => $data['type'],
            'value_b' => $tranId,
            'multi_card_name' => 'bkash,nagad',
        ];

        $init = $this->ssl->initPayment($payload);
        if (!empty($init['ok']) && !empty($init['gateway_url'])) {
            return ['ok' => true, 'gateway_url' => $init['gateway_url']];
        }

        return ['ok' => false, 'error' => 'Payment gateway error: ' . ($init['error'] ?? 'Please try again')];
    }

    /**
     * @return array{ok: bool, error?: string, data?: array<string,mixed>}
     */
    public function completeRegistration(string $tranId, string $valId, string $status): array
    {
        $pending = $this->db->prepare('SELECT * FROM pending_registrations WHERE tran_id = ? LIMIT 1');
        $pending->bind_param('s', $tranId);
        $pending->execute();
        $reg = $pending->get_result()->fetch_assoc();
        $pending->close();

        if (!$reg) {
            return ['ok' => false, 'error' => 'Invalid or expired registration session.'];
        }

        if ($status !== 'VALID' && $status !== 'VALIDATED') {
            return ['ok' => false, 'error' => 'Payment was not successful. Status: ' . $status];
        }

        $validation = $this->ssl->validateTransaction($valId);
        if (empty($validation['ok'])) {
            return ['ok' => false, 'error' => 'Payment validation failed: ' . ($validation['error'] ?? 'Unknown error')];
        }

        $vStatus = strtoupper($validation['data']['status'] ?? '');
        if (!in_array($vStatus, ['VALID', 'VALIDATED'], true)) {
            return ['ok' => false, 'error' => 'Payment not valid. Status: ' . ($validation['data']['status'] ?? 'Unknown')];
        }

        $this->db->begin_transaction();
        try {
            $userId = $this->userModel->create($reg['name'], $reg['email'], $reg['password'], 'customer');

            $invoicePrefix = $reg['customer_type'] === 'vip' ? 'VIP' : 'PRO';
            $invoiceNumber = $invoicePrefix . '-' . date('Ymd') . '-' . str_pad((string)$userId, 5, '0', STR_PAD_LEFT);

            $customer = $this->db->prepare(
                'INSERT INTO customers (user_id, name, email, phone, nid, customer_type, registration_fee, registration_date, registration_invoice, is_member) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, 1)'
            );
            $customer->bind_param(
                'isssssds',
                $userId,
                $reg['name'],
                $reg['email'],
                $reg['phone'],
                $reg['nid'],
                $reg['customer_type'],
                $reg['fee'],
                $invoiceNumber
            );
            $customer->execute();
            $customerId = (int)$this->db->insert_id;
            $customer->close();

            $del = $this->db->prepare('DELETE FROM pending_registrations WHERE tran_id = ?');
            $del->bind_param('s', $tranId);
            $del->execute();
            $del->close();

            $this->db->commit();

            Auth::establishSession((int)$userId, (string)$reg['name'], 'customer', $customerId, (string)$reg['customer_type']);

            return [
                'ok' => true,
                'data' => [
                    'id' => $customerId,
                    'name' => $reg['name'],
                    'email' => $reg['email'],
                    'phone' => $reg['phone'],
                    'nid' => $reg['nid'],
                    'type' => $reg['customer_type'],
                    'fee' => $reg['fee'],
                    'invoice' => $invoiceNumber,
                    'tran_id' => $tranId,
                    'val_id' => $valId,
                ],
            ];
        } catch (\Throwable $e) {
            $this->db->rollback();
            return ['ok' => false, 'error' => 'Registration failed: ' . $e->getMessage()];
        }
    }
}
