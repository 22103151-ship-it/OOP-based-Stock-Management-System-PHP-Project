<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\AdminOrderService;
Auth::requireRole('admin');

$adminOrderService = new AdminOrderService($conn);

// ✅ Get order_id from URL
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if($order_id == 0){
    die("Order ID not found.");
}

// Get payment info from POST
$tran_id   = isset($_POST['tran_id']) ? $_POST['tran_id'] : uniqid("txn_");
$card_type = isset($_POST['card_type']) ? $_POST['card_type'] : 'Unknown';
$tran_date = isset($_POST['tran_date']) ? $_POST['tran_date'] : date("Y-m-d H:i:s");

if ($adminOrderService->recordPurchasePayment($tran_id, $card_type, $tran_date)) {
    echo "✅ Payment recorded successfully.<br>";

    // Update the order as Paid
    $adminOrderService->markPurchaseOrderPaid($order_id);

    echo "✔ Invoice updated as Paid.<br>";

} else {
    echo "❌ Database Error while saving payment.";
}

echo "Transaction ID: $tran_id<br>";
echo "Payment Type: $card_type<br>";
echo "Transaction Date: $tran_date<br>";
?>
