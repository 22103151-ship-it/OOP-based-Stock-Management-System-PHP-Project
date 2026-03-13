<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\SupplierOrderService;
Auth::requireRole('supplier');

$supplierOrderService = new SupplierOrderService($conn);

$supplier_id = 0;
if (file_exists('../includes/supplier_helpers.php')) {
    include '../includes/supplier_helpers.php';
    $supplier_id = getResolvedSupplierId($conn);
}

// -------- Handle Status Updates --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $summary = $supplierOrderService->updateStatuses($supplier_id > 0 ? $supplier_id : null, (array)$_POST['status']);
    $pending_remaining = (int)($summary['pending_remaining'] ?? 0);
    $new_delivered = (int)($summary['new_delivered'] ?? 0);
    $_SESSION['flash_message'] = "🫵 $pending_remaining pending remaining / 👌 $new_delivered delivered successfully";

    header("Location: my_orders.php");
    exit;
}

// include header after handling POST so header() redirects work before output
include '../includes/header.php';
// if POST updates occurred, we may have set a flash message in the session
if (isset($_SESSION['flash_message'])) {
    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
} else {
    $flash = null;
}

// -------- Fetch Orders --------
$orders = $supplierOrderService->getOrders($supplier_id > 0 ? $supplier_id : null);
?>

<div class="main-container">
    <?php if (!empty($flash)): ?>
        <div id="flash-message" class="flash-message"><?php echo htmlspecialchars($flash); ?></div>
    <?php endif; ?>
    <!-- Back Button -->
    <a href="dashboard.php" class="back-btn">Back</a>

    <h2 class="page-title">📦 My Orders</h2>

    <form method="post" action="my_orders.php">
        <div class="table-container">
            <table class="styled-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Price (per unit)</th>
                        <th>Total Price</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Invoice</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($orders)): ?>
                        <?php foreach($orders as $o): ?>
                        <tr>
                            <td><?php echo $o['id']; ?></td>
                            <td><?php echo htmlspecialchars($o['product_name']); ?></td>
                            <td><?php echo $o['quantity']; ?></td>
                            <td><?php echo number_format($o['price'], 2); ?></td>
                            <td><?php echo number_format($o['quantity'] * $o['price'], 2); ?></td>
                            <td>
                                <select name="status[<?php echo $o['id']; ?>]" class="status-select">
                                    <option value="pending"   <?php if ($o['status']=='pending') echo 'selected'; ?>>Pending</option>
                                    <option value="delivered" <?php if ($o['status']=='delivered') echo 'selected'; ?>>Delivered</option>
                                </select>
                            </td>
                            <td><?php echo $o['created_at']; ?></td>
                            <td>
                                <a href="generate_invoice.php?order_id=<?php echo $o['id']; ?>" target="_blank" class="invoice-btn">Proforma Invoice</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center;">No orders yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Save Button -->
        <div class="action-btn">
            <button type="submit" class="btn-primary">💾 Save Changes</button>
        </div>
    </form>
</div>

<style>
    .main-container {
        max-width: 1100px;
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
        background-color: #f9f9f9;
    }

    .styled-table tbody tr:hover {
        background-color: #e9f5ff;
    }

    .status-select {
        padding: 5px;
        border-radius: 4px;
        border: 1px solid #ccc;
    }

    .invoice-btn {
        padding: 6px 12px;
        background: #007bff;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        transition: background 0.3s;
    }

    .invoice-btn:hover {
        background: #0056b3;
    }

    .action-btn {
        margin-top: 15px;
        text-align: left;
    }

    .btn-primary {
        padding: 10px 18px;
        background: #28a745;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 15px;
        transition: background 0.3s;
    }

    .btn-primary:hover {
        background: #218838;
    }
    .flash-message {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        background: #e6ffed;
        color: #14532d;
        border: 1px solid #c7f0d6;
        box-shadow: 0 2px 6px rgba(20,83,45,0.08);
        font-weight: 600;
    }
</style>

<script>
    (function(){
        var el = document.getElementById('flash-message');
        if(!el) return;
        setTimeout(function(){
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(function(){ el.remove(); }, 500);
        }, 5000);
    })();
</script>
