<?php

namespace App\Models;

/**
 * User — model for the `users` table.
 *
 * Handles system-level authentication data: credentials and role.
 * Profile details for customers/suppliers live in their own models.
 */
class User extends BaseModel
{
    protected string $table = 'users';

    /** Find a user by email address. Returns null when not found. */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /** Create a new user and return the new user ID. */
    public function create(string $name, string $email, string $password, string $role): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('ssss', $name, $email, $password, $role);
        $stmt->execute();
        return (int)$this->db->insert_id;
    }

    /** Update user fields used by admin user-management page. */
    public function updateDetails(int $id, string $name, string $email, string $password, string $role): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET name = ?, email = ?, password = ?, role = ? WHERE id = ?"
        );
        $stmt->bind_param('ssssi', $name, $email, $password, $role, $id);
        return $stmt->execute();
    }

    /** All users that belong to a given role, ordered by name. */
    public function findAllByRole(string $role): array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE role = ? ORDER BY name");
        $stmt->bind_param('s', $role);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /** Check whether an email is already registered. */
    public function emailExists(string $email): bool
    {
        return $this->count('email = ?', [$email]) > 0;
    }
}
