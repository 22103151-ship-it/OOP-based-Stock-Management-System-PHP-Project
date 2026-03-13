<?php

namespace App\Models;

/**
 * GuestCustomer — model for the `guest_customers` table.
 *
 * Guest users are identified by phone number and a session ID.
 * They verify identity via a time-limited OTP before ordering.
 */
class GuestCustomer extends BaseModel
{
    protected string $table = 'guest_customers';

    // ------------------------------------------------------------------ reads

    /** Find a guest by phone number. */
    public function findByPhone(string $phone): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM guest_customers WHERE phone = ? LIMIT 1"
        );
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /** Find a guest whose session_id matches (for cart/flow continuity). */
    public function findBySession(string $sessionId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM guest_customers WHERE session_id = ? LIMIT 1"
        );
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /** Find a guest that has already verified their OTP in this session. */
    public function findVerifiedBySession(string $sessionId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM guest_customers WHERE session_id = ? AND otp_verified = 1 LIMIT 1"
        );
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    // ------------------------------------------------------------------ writes

    /**
     * Insert a new guest or update an existing one (matched by phone) with
     * a fresh OTP and session ID.  Returns the guest customer ID.
     */
    public function upsertWithOTP(
        string $name,
        string $phone,
        string $otp,
        string $expires,
        string $sessionId
    ): int {
        $existing = $this->findByPhone($phone);

        if ($existing) {
            $stmt = $this->db->prepare(
                "UPDATE guest_customers
                 SET name = ?, otp_code = ?, otp_expires = ?, otp_verified = 0, session_id = ?
                 WHERE id = ?"
            );
            $stmt->bind_param('ssssi', $name, $otp, $expires, $sessionId, $existing['id']);
            $stmt->execute();
            return (int)$existing['id'];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO guest_customers (name, phone, otp_code, otp_expires, session_id)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('sssss', $name, $phone, $otp, $expires, $sessionId);
        $stmt->execute();
        return (int)$this->db->insert_id;
    }

    /** Mark the OTP as verified for a guest. */
    public function verifyOTP(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE guest_customers SET otp_verified = 1 WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    /** Increment lifetime order statistics after a successful checkout. */
    public function incrementOrderStats(int $id, int $totalStocks): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE guest_customers
             SET total_orders = total_orders + 1,
                 total_stocks_ordered = total_stocks_ordered + ?
             WHERE id = ?"
        );
        $stmt->bind_param('ii', $totalStocks, $id);
        return $stmt->execute();
    }
}
