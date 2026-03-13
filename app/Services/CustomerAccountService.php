<?php

namespace App\Services;

use App\Core\Auth;
use App\Models\User;

/**
 * CustomerAccountService centralizes customer login and registration flows.
 */
class CustomerAccountService
{
    private \mysqli $db;
    private User $userModel;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->userModel = new User($db);
    }

    /**
     * Authenticate customer by email or phone and establish session.
     *
     * @return array{ok: bool, error?: string}
     */
    public function loginCustomer(string $login, string $password): array
    {
        $query = "SELECT u.id, u.password, u.role, c.id as customer_id, c.name, c.customer_type
                  FROM users u
                  JOIN customers c ON u.id = c.user_id
                  WHERE (c.email = ? OR c.phone = ?) AND u.role = 'customer'
                  LIMIT 1";

        $stmt = $this->db->prepare($query);
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Database error: failed to prepare statement.'];
        }

        $stmt->bind_param('ss', $login, $login);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            return ['ok' => false, 'error' => 'No account found with this email/phone.'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['ok' => false, 'error' => 'Invalid password.'];
        }

        Auth::establishSession(
            (int)$user['id'],
            (string)$user['name'],
            (string)$user['role'],
            (int)$user['customer_id'],
            (string)($user['customer_type'] ?? 'regular')
        );

        return ['ok' => true];
    }

    public function phoneExists(string $phone): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM customers WHERE phone = ? LIMIT 1');
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function emailExists(string $email): bool
    {
        return $this->userModel->emailExists($email);
    }

    /**
     * Register customer using the legacy basic flow (register.php).
     *
     * @return array{ok: bool, customer_id?: int, error?: string}
     */
    public function registerBasicCustomer(string $name, string $email, string $phone, string $password): array
    {
        $this->db->begin_transaction();

        try {
            $loginEmail = $email !== '' ? $email : $phone . '@customer.local';
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $userId = $this->userModel->create($name, $loginEmail, $hashedPassword, 'customer');

            $customerStmt = $this->db->prepare(
                'INSERT INTO customers (user_id, name, email, phone) VALUES (?, ?, ?, ?)'
            );
            $customerStmt->bind_param('isss', $userId, $name, $email, $phone);
            $customerStmt->execute();
            $customerId = (int)$this->db->insert_id;
            $customerStmt->close();

            Auth::establishSession($userId, $name, 'customer', $customerId, 'regular');

            $notification = $this->db->prepare(
                "INSERT INTO automated_notifications (customer_id, notification_type, message) VALUES (?, 'order_placed', 'Welcome to Stock Management System! Your account has been created successfully.')"
            );
            $notification->bind_param('i', $customerId);
            $notification->execute();
            $notification->close();

            $this->db->commit();
            return ['ok' => true, 'customer_id' => $customerId];
        } catch (\Throwable $e) {
            $this->db->rollback();
            return ['ok' => false, 'error' => 'Error creating account. Please try again.'];
        }
    }

    /**
     * Register customer using the new flow (register_new.php).
     *
     * @return array{ok: bool, customer_id?: int, error?: string}
     */
    public function registerNewCustomer(string $name, string $email, string $phone, string $password): array
    {
        $this->db->begin_transaction();

        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $userEmail = $email !== '' ? $email : $phone . '@customer.local';
            $userId = $this->userModel->create($name, $userEmail, $hashedPassword, 'customer');

            $customerStmt = $this->db->prepare(
                'INSERT INTO customers (user_id, name, email, phone, is_member, membership_fee_paid) VALUES (?, ?, ?, ?, 0, 0.00)'
            );
            $customerEmail = $email !== '' ? $email : null;
            $customerStmt->bind_param('isss', $userId, $name, $customerEmail, $phone);
            $customerStmt->execute();
            $customerId = (int)$this->db->insert_id;
            $customerStmt->close();

            Auth::establishSession($userId, $name, 'customer', $customerId, 'regular');

            $notification = $this->db->prepare(
                "INSERT INTO automated_notifications (customer_id, notification_type, message) VALUES (?, 'welcome', 'Welcome to Stock Management System! Your account has been created successfully. Buy membership (min ৳100) to unlock discounts!')"
            );
            $notification->bind_param('i', $customerId);
            $notification->execute();
            $notification->close();

            $this->db->commit();
            return ['ok' => true, 'customer_id' => $customerId];
        } catch (\Throwable $e) {
            $this->db->rollback();
            return ['ok' => false, 'error' => 'Error creating account. Please try again.'];
        }
    }
}
