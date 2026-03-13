<?php

namespace App\Core;

/**
 * Database — Singleton wrapper around mysqli.
 *
 * Usage:
 *   $db = Database::getInstance()->getConnection();
 *
 * The singleton ensures only one connection is opened per request,
 * which is the standard OOP approach for DB management.
 */
class Database
{
    private static ?Database $instance = null;
    private \mysqli $conn;

    private function __construct()
    {
        $host = 'localhost';
        $user = 'root';
        $pass = '';
        $db   = 'stock_management_system';

        $this->conn = new \mysqli($host, $user, $pass, $db);

        if ($this->conn->connect_error) {
            die('Database connection failed: ' . $this->conn->connect_error);
        }

        $this->conn->set_charset('utf8');
    }

    /** Returns the single instance, creating it on first call. */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Returns the raw mysqli connection for use in statements. */
    public function getConnection(): \mysqli
    {
        return $this->conn;
    }

    // Prevent cloning or external unserialization of the singleton.
    private function __clone() {}
    public function __wakeup(): void {}
}
