<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\SupplierOrderService;
Auth::requireRole('admin', 'staff');
include '../includes/header.php';

$supplierOrderService = new SupplierOrderService($conn);
$returned_rows = $supplierOrderService->getOrdersByStatus('returned', null);
?>

<div style="max-width:900px; margin:20px auto; padding:20px; background:#f8f8f8; border-radius:8px;">
    <a href="dashboard.php" style="display:inline-block; margin-bottom:20px; padding:8px 15px; background:#555; color:white; border-radius:5px; text-decoration:none;"> Back </a>
    <h2>📦 Returned Orders</h2>

    <table border="1" cellpadding="10" cellspacing="0" style="width:100%; border-collapse:collapse; background:white; text-align:left;">
        <tr style="background:#ddd;">
            <th>ID</th>
            <th>Product</th>
            <th>Quantity</th>
            <th>Status</th>
            <th>Created At</th>
        </tr>
        <?php if (empty($returned_rows)): ?>
        <tr>
            <td colspan="5" style="text-align:center; color:#666;">No returned orders.</td>
        </tr>
        <?php else: foreach ($returned_rows as $row): ?>
        <tr>
            <td><?php echo $row['id']; ?></td>
            <td><?php echo htmlspecialchars($row['product_name']); ?></td>
            <td><?php echo $row['quantity']; ?></td>
            <td><?php echo ucfirst($row['status']); ?></td>
            <td><?php echo $row['created_at']; ?></td>
        </tr>
        <?php endforeach; endif; ?>
    </table>
</div>

       
    </div>
    <style>
        /* Responsive Grid */
@media (max-width: 992px) {
    .dashboard-cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
}

@media (max-width: 600px) {
    .dashboard-cards {
        grid-template-columns: 1fr;
        gap: 10px;
    }
}
    </style>
    <!-- <footer style="
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    background-color: gray;
    color: white;
    text-align: center;
    padding: 15px 0;
">
    <p>© 2025 Stock Management System. All rights reserved.</p>
</footer> -->


