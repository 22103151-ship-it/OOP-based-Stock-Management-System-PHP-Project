<?php
session_start();
include '../config.php';
require_once '../includes/sslcommerz_config.php';
use App\Services\CustomerPaymentService;
use App\Services\SSLCommerzService;

$tran_id = $_POST['tran_id'] ?? '';
$val_id = $_POST['val_id'] ?? '';
$status = $_POST['status'] ?? '';
$customer_id = (int)($_POST['value_a'] ?? 0);
$payment_id = (int)($_POST['value_b'] ?? 0);

// Keep callback page thin: DB/payment orchestration lives in service layer.
$ssl = new SSLCommerzService(
    (string)$SSLCOMMERZ_STORE_ID,
    (string)$SSLCOMMERZ_STORE_PASS,
    (bool)$SSLCOMMERZ_SANDBOX
);
$paymentService = new CustomerPaymentService($conn, $ssl);

$result = $paymentService->completeMembershipPayment($tran_id, $customer_id, $payment_id, (string)$status, (string)$val_id);

if (!empty($result['ok'])) {
    $_SESSION['success'] = 'Membership activated successfully! You now have access to member discounts.';
    header('Location: membership.php');
    exit;
}

$_SESSION['error'] = $result['error'] ?? 'Payment validation failed';
header('Location: membership.php');
exit;
