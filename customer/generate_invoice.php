<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\CustomerPortalService;
Auth::requireRole('customer');

$portalService = new CustomerPortalService($conn);

$customer_id = $_SESSION['customer_id'] ?? null;
if (!$customer_id && isset($_SESSION['user_id'])) {
    $customer = $portalService->resolveCustomerByUserId((int)$_SESSION['user_id']);
    if ($customer) {
        $customer_id = (int)$customer['id'];
        $_SESSION['customer_id'] = $customer_id;
    }
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0 || !$customer_id) {
    die("Invalid order.");
}

$order = $portalService->getInvoiceOrder((int)$customer_id, $order_id);
if (!$order) {
    die("Invalid order.");
}
$total_amount = $order['price'] * $order['quantity'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $order_id; ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background:#f5f7fb; }
        .invoice-box { max-width: 600px; margin:auto; padding:30px; border:1px solid #eee; border-radius:10px; background:#fff; box-shadow:0 0 10px rgba(0,0,0,.12); }
        h2 { text-align:center; margin:0 0 10px 0; }
        table { width:100%; line-height:inherit; text-align:left; border-collapse: collapse; margin-top: 20px; }
        table th, table td { border:1px solid #ddd; padding:8px; }
        table th { background:#f2f2f2; }
        .total { text-align:right; margin-top:20px; font-weight:bold; }
        .print-btn { margin-top: 20px; padding:10px 20px; background:#28a745; color:white; border:none; border-radius:5px; cursor:pointer; }
        .meta { color:#555; margin-top:6px; }
    </style>
</head>
<body>

<div class="invoice-box">
    <h2>Customer Invoice</h2>
    <p class="meta"><strong>Invoice ID:</strong> <?php echo $order['id']; ?></p>
    <p class="meta"><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
    <p class="meta"><strong>Date:</strong> <?php echo $order['order_date']; ?></p>
    <p class="meta"><strong>Status:</strong> <?php echo ucfirst($order['status']); ?></p>

    <table>
        <tr>
            <th>Product</th>
            <th>Quantity</th>
            <th>Price</th>
            <th>Total</th>
        </tr>
        <tr>
            <td><?php echo htmlspecialchars($order['product_name']); ?></td>
            <td><?php echo $order['quantity']; ?></td>
            <td><?php echo number_format($order['price'],2); ?></td>
            <td><?php echo number_format($total_amount,2); ?></td>
        </tr>
    </table>

    <div class="total">
        Grand Total: <?php echo number_format($total_amount,2); ?>
    </div>

    <button class="print-btn" onclick="window.print();">🖨️ Print Invoice</button>
</div>

</body>
</html>
