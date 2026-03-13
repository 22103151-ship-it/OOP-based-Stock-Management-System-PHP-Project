<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\SupplierOrderService;
Auth::requireRole('admin', 'staff', 'supplier');
include '../includes/header.php';

$supplierOrderService = new SupplierOrderService($conn);

$supplier_id = 0;
if (file_exists('../includes/supplier_helpers.php')) {
    include '../includes/supplier_helpers.php';
    $supplier_id = getResolvedSupplierId($conn);
}

// ------------------ Update stock for delivered orders ------------------
$supplierOrderService->syncDeliveredStock($supplier_id > 0 ? $supplier_id : null);

// ------------------ Fetch delivered orders ------------------
$rows = $supplierOrderService->getDeliveredOrders($supplier_id > 0 ? $supplier_id : null);
?>

<div class="main-container">
    <!-- Back Button -->
    <a href="dashboard.php" class="back-btn">Back</a>

    <h2 class="page-title">📦 Delivered Orders</h2>

    <div class="table-container">
        <table class="styled-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Status</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($rows)): ?>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                        <td><?php echo $row['quantity']; ?></td>
                        <td><?php echo ucfirst($row['status']); ?></td>
                        <td><?php echo $row['created_at']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">No delivered orders found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    .main-container {
        max-width: 1000px;
        margin: 40px auto;
        background: #fff;
        padding: 20px 30px;
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .page-title {
        text-align: center;
        margin-bottom: 20px;
        color: #333;
    }

    .back-btn {
        display: inline-block;
        margin-bottom: 20px;
        padding: 8px 15px;
        background: #555;
        color: white;
        border-radius: 5px;
        text-decoration: none;
        transition: background 0.3s;
    }

    .back-btn:hover {
        background: #333;
    }

    .table-container {
        overflow-x: auto;
    }

    .styled-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0 auto;
        font-size: 15px;
        border-radius: 5px;
        overflow: hidden;
    }

    .styled-table thead tr {
        background-color: #007BFF;
        color: #ffffff;
        text-align: left;
    }

    .styled-table th, .styled-table td {
        padding: 12px 15px;
        border: 1px solid #ddd;
    }

    .styled-table tbody tr:nth-child(even) {
        background-color: #f3f3f3;
    }

    .styled-table tbody tr:hover {
        background-color: #e9f5ff;
    }
</style>


<!-- <footer style="
    background-color: gray;
    color: white;
    text-align: center;
    padding: 15px 0;
">
    <p>© 2025 Stock Management System. All rights reserved.</p>
</footer> -->
