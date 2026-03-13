<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Models\CustomerOrder;
Auth::requireRole('staff');
require '../includes/header.php';

$customerOrderModel = new CustomerOrder($conn);

$orders = $customerOrderModel->findByStatusesDetailed(['shipped'], 200);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Orders</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family:'Poppins', sans-serif; background:#f6f8fb; margin:0; padding:20px; }
        .wrap { max-width:1000px; margin:0 auto; background:#fff; border-radius:12px; padding:20px; box-shadow:0 12px 24px rgba(0,0,0,0.08); }
        h1 { margin:0 0 16px 0; font-size:22px; color:#333; }
        table { width:100%; border-collapse: collapse; }
        th, td { padding:12px; text-align:left; border-bottom:1px solid #eee; }
        th { background:#f9fafb; font-weight:600; color:#555; }
        tr:hover { background:#fafbff; }
        .btn { border:none; padding:8px 12px; border-radius:8px; cursor:pointer; font-weight:600; font-size:13px; }
        .btn-primary { background:#22c55e; color:#fff; }
        .btn-muted { background:#e5e7eb; color:#374151; }
        .hint { font-size:12px; color:#6b7280; margin-top:6px; }
    </style>
</head>
<body>
<div class="wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h1><i class="fa-solid fa-truck"></i> Processing Orders (Admin Approved)</h1>
        <a href="dashboard.php" style="text-decoration:none; color:#2563eb; font-weight:600;">Back to Dashboard</a>
    </div>
    <?php if (empty($orders)): ?>
        <p>No processing orders right now.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Serial</th>
                <th>Customer</th>
                <th>Product</th>
                <th>Qty</th>
                <th>Ordered</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php $serial = count($orders); foreach ($orders as $order): ?>
                <tr>
                    <td><?php echo $serial--; ?></td>
                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                    <td><?php echo $order['quantity']; ?></td>
                    <td><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></td>
                    <td>
                        <form method="POST" action="deliver_customer_order.php">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <button class="btn btn-primary" type="submit">OK, Order Sent (Notify Admin)</button>
                            <div class="hint">Marks delivered, pings admin instantly.</div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</body>
</html>
