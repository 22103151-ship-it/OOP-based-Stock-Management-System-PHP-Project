<?php

namespace App\Models;

/**
 * Supplier — model for the `suppliers` table.
 */
class Supplier extends BaseModel
{
    protected string $table = 'suppliers';

    /** Find a supplier profile by users.id. */
    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM suppliers WHERE user_id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /** Resolve a supplier by matching email or name. */
    public function findByEmailOrName(string $email, string $name): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM suppliers WHERE email = ? OR name = ? LIMIT 1"
        );
        $stmt->bind_param('ss', $email, $name);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /** Create a new supplier profile and return the new ID. */
    public function create(
        int    $userId,
        string $name,
        string $email,
        string $phone   = '',
        string $address = ''
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO suppliers (user_id, name, email, phone, address) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('issss', $userId, $name, $email, $phone, $address);
        $stmt->execute();
        return (int)$this->db->insert_id;
    }

    /** Update contact fields of an existing supplier. */
    public function updateProfile(int $id, array $fields): bool
    {
        $allowed = ['name', 'phone', 'address', 'email'];
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
        $stmt     = $this->db->prepare(
            "UPDATE suppliers SET " . implode(', ', $sets) . " WHERE id = ?"
        );
        $stmt->bind_param($types, ...$values);
        return $stmt->execute();
    }
}
