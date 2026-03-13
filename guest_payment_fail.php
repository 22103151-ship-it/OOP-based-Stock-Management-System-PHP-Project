<?php
session_start();

// Get order ID from demo or real gateway response
$orderId = (int)($_GET['value_a'] ?? $_POST['value_a'] ?? 0);

if ($orderId) {
    $_SESSION['error'] = 'Payment failed for order #' . $orderId . '. Please try again.';
} else {
    $_SESSION['error'] = 'Payment failed. Please try again.';
}

header('Location: home.php');
exit;
