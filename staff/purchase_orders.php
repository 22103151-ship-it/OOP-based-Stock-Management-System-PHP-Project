<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\StaffOrderService;
Auth::requireRole('staff');
include '../includes/header.php';

$staffOrderService = new StaffOrderService($conn);
$message = '';

// ---------------- Fetch Products for dropdown ----------------
$products = $staffOrderService->getProducts();

// ---------------- Add Order ----------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_order'])) {
    $ok = $staffOrderService->addOrder((string)$_POST['product_name'], (int)$_POST['quantity']);
    $message = $ok ? "<p style='color:green;'>Order added successfully!</p>" : "<p style='color:red;'>Failed to add order.</p>";
}

// ---------------- Edit Order ----------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_order'])) {
    $ok = $staffOrderService->editOrder((int)$_POST['id'], (string)$_POST['product_name'], (int)$_POST['quantity'], (string)$_POST['status']);
    $message = $ok ? "<p style='color:green;'>Order updated successfully!</p>" : "<p style='color:red;'>Failed to update order.</p>";
}

// ---------------- Delete Order ----------------
if (isset($_GET['delete'])) {
    $ok = $staffOrderService->deleteOrder((int)$_GET['delete']);
    $message = $ok ? "<p style='color:red;'>Order deleted successfully!</p>" : "<p style='color:red;'>Failed to delete order.</p>";
}

// ---------------- Fetch Orders ----------------
$order_rows = $staffOrderService->getOrders();

// ---------------- If editing, fetch order details ----------------
$edit_order = null;
if (isset($_GET['edit'])) {
    $edit_order = $staffOrderService->findOrder((int)$_GET['edit']);
}
?>

<div style="max-width:900px; margin:20px auto; padding:20px; background:#f8f8f8; border-radius:8px;">
    <!-- Back Button -->
    <a href="dashboard.php" style="display:inline-block; margin-bottom:20px; padding:8px 15px; background:#555; color:white; border-radius:5px; text-decoration:none;"> Back </a>

    <h2>📦 Manage Staff Orders</h2>
    <?php echo $message; ?>

    <!-- Add / Edit Order Form -->
    <form method="POST" style="margin-bottom: 30px;">
        <h3><?php echo $edit_order ? "Edit Order" : "Add New Order"; ?></h3>

        <input type="hidden" name="id" value="<?php echo $edit_order['id'] ?? ''; ?>">

        <label>Product:</label>
        <select name="product_name" required style="width:100%; padding:8px; margin:5px 0;">
            <option value="">-- Select Product --</option>
            <?php 
            foreach ($products as $p): ?>
                <option value="<?php echo htmlspecialchars($p['name']); ?>" 
                    <?php if (isset($edit_order['product_name']) && $edit_order['product_name'] == $p['name']) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($p['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Quantity:</label>
        <input type="number" name="quantity" required value="<?php echo $edit_order['quantity'] ?? ''; ?>" style="width:100%; padding:8px; margin:5px 0;">

        <?php if($edit_order): ?>
            <label>Status:</label>
            <select name="status" required style="width:100%; padding:8px; margin:5px 0;">
                <option value="pending" <?php if(isset($edit_order['status']) && $edit_order['status']=='pending') echo 'selected'; ?>>Pending</option>
                <option value="delivered" <?php if(isset($edit_order['status']) && $edit_order['status']=='delivered') echo 'selected'; ?>>Delivered</option>
                <option value="returned" <?php if(isset($edit_order['status']) && $edit_order['status']=='returned') echo 'selected'; ?>>Returned</option>
            </select>
        <?php endif; ?>

        <button type="submit" name="<?php echo $edit_order ? 'edit_order' : 'add_order'; ?>" class="<?php echo $edit_order ? 'btn-edit' : 'btn-add'; ?>">
            <?php echo $edit_order ? '✏️ Update Order' : '➕ Add Order'; ?>
        </button>
        <?php if ($edit_order): ?>
            <a href="staff_orders.php" style="margin-left:10px; color:#555;">Cancel</a>
        <?php endif; ?>
    </form>

    <!-- Orders Table -->
    <table border="1" cellpadding="10" cellspacing="0" style="width:100%; border-collapse:collapse; background:white; text-align:left;">
        <tr style="background:#ddd;">
            <th>Serial</th>
            <th>Product Name</th>
            <th>Quantity</th>
            <th>Status</th>
            <th>Created At</th>
            <th style="width:160px; text-align:center;">Action</th>
        </tr>
        <?php if (empty($order_rows)): ?>
            <tr><td colspan="6" style="text-align:center; color:#666;">No staff orders found.</td></tr>
        <?php else: ?>
            <?php $serial = count($order_rows); foreach ($order_rows as $row): ?>
            <tr>
                <td><?php echo $serial--; ?></td>
                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                <td><?php echo $row['quantity']; ?></td>
                <td><?php echo ucfirst($row['status']); ?></td>
                <td><?php echo $row['created_at']; ?></td>
                <td style="white-space:nowrap; text-align:center;">
                    <a href="staff_orders.php?edit=<?php echo $row['id']; ?>" class="btn-edit" style="text-decoration:none; margin-right:8px;">✏️ Edit</a>
                    <a href="staff_orders.php?delete=<?php echo $row['id']; ?>" class="btn-delete" style="text-decoration:none;" onclick="return confirm('Are you sure?')">🗑️ Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</div>

<footer style="
    position: auto;
    bottom: 0;
    left: 0;
    width: 100%;
    background-color: gray;
    color: white;
    text-align: center;
    padding: 15px 0;
">
    <p>© 2025 Stock Management System. All rights reserved.</p>
</footer>
