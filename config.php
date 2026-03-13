<?php

// ============================================================
// Bootstrap the OOP class autoloader
// ============================================================
require_once __DIR__ . '/app/bootstrap.php';

// ============================================================
// Database connection
// App\Core\Database is a singleton — use it from OOP code via:
//   $conn = \App\Core\Database::getInstance()->getConnection();
//
// The $conn variable below keeps the existing procedural pages
// working without any changes to them.
// ============================================================
$conn = \App\Core\Database::getInstance()->getConnection();
?>
