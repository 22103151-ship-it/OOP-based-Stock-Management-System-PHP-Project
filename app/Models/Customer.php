<?php

namespace App\Models;

/**
 * Customer — model for the `customers` table.
 *
 * Each customer row is linked to a `users` row via user_id.
 * Customer type ('pro' or 'vip') drives discount logic.
 */
class Customer extends BaseModel
{
    protected string $table = 'customers';

    // ------------------------------------------------------------------ reads

    /** Find a customer profile by `users.id`. Returns null when not found. */
    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM customers WHERE user_id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /** Return the customer_type string ('pro' or 'vip') for a given customer ID. */
    public function getType(int $customerId): string
    {
        $stmt = $this->db->prepare(
            "SELECT customer_type FROM customers WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row['customer_type'] ?? 'pro';
    }

    // ------------------------------------------------------------------ writes

    /**
     * Create a new customer profile linked to an existing user account.
     * Returns the new customer ID.
     */
    public function create(
        int    $userId,
        string $name,
        string $email,
        string $phone = '',
        string $nid   = '',
        string $type  = 'pro'
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO customers (user_id, name, email, phone, nid, customer_type)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('isssss', $userId, $name, $email, $phone, $nid, $type);
        $stmt->execute();
        return (int)$this->db->insert_id;
    }

    /**
     * Update allowed profile fields.
     * Only fields listed in $allowed are written to the database.
     *
     * @param  array<string, string>  $fields  e.g. ['name' => 'Alice', 'phone' => '01700000000']
     */
    public function updateProfile(int $id, array $fields): bool
    {
        $allowed = ['name', 'phone', 'address', 'nid', 'email', 'extra_phone', 'profile_picture'];
        $sets    = [];
        $types   = '';
        $values  = [];

        foreach ($fields as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $sets[]  = "`{$k}` = ?";
                $types  .= 's';
                $values[] = $v;
            }
        }

        if (empty($sets)) {
            return false;
        }

        $types   .= 'i';
        $values[] = $id;
        $sql      = "UPDATE customers SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt     = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$values);
        return $stmt->execute();
    }

    /** Upgrade or downgrade a customer membership type. */
    public function setType(int $id, string $type): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE customers SET customer_type = ? WHERE id = ?"
        );
        $stmt->bind_param('si', $type, $id);
        return $stmt->execute();
    }
}
