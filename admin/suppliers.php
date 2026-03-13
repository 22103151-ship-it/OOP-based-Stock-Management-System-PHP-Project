<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\SupplierManagementService;
Auth::requireRole('admin');
include '../includes/header.php'; // Keep header only

$supplierService = new SupplierManagementService($conn);
$message = '';

// ---------------- Add Supplier ----------------
if (isset($_POST['add_supplier'])) {
    $ok = $supplierService->addSupplier($_POST);
    $message = $ok ? "<p style='color:green;'>Supplier added successfully!</p>" : "<p style='color:red;'>Failed to add supplier.</p>";
}

// ---------------- Edit Supplier ----------------
if (isset($_POST['edit_supplier'])) {
    $ok = $supplierService->editSupplier((int)$_POST['id'], $_POST);
    $message = $ok ? "<p style='color:green;'>Supplier updated successfully!</p>" : "<p style='color:red;'>Failed to update supplier.</p>";
}

// ---------------- Delete Supplier ----------------
if (isset($_GET['delete'])) {
    $ok = $supplierService->deleteSupplier((int)$_GET['delete']);
    $message = $ok ? "<p style='color:red;'>Supplier deleted successfully!</p>" : "<p style='color:red;'>Failed to delete supplier.</p>";
}

// ---------------- Fetch Suppliers ----------------
$supplier_rows = $supplierService->getSuppliers();

// ---------------- If editing, fetch supplier details ----------------
$edit_supplier = null;
if (isset($_GET['edit'])) {
    $edit_supplier = $supplierService->findSupplier((int)$_GET['edit']);
}
?>

<div style="max-width:900px; margin:20px auto; padding:20px; background:#f8f8f8; border-radius:8px;">

    <!-- Back Button -->
    <a href="dashboard.php" style="display:inline-block; margin-bottom:20px; padding:8px 15px; background:#555; color:white; border-radius:5px; text-decoration:none;">Back </a>

    <h2>Manage Suppliers</h2>
    <?php echo $message; ?>

    <!-- Add / Edit Supplier Form -->
    <form method="POST" style="margin-bottom: 30px;">
        <h3><?php echo $edit_supplier ? "Edit Supplier" : "Add New Supplier"; ?></h3>

        <input type="hidden" name="id" value="<?php echo $edit_supplier['id'] ?? ''; ?>">

        <input type="text" name="name" placeholder="Supplier Name" required value="<?php echo $edit_supplier['name'] ?? ''; ?>" style="width:100%; padding:8px; margin:5px 0;">
        <input type="email" name="email" placeholder="Email" required value="<?php echo $edit_supplier['email'] ?? ''; ?>" style="width:100%; padding:8px; margin:5px 0;">
        <input type="text" name="phone" placeholder="Phone" value="<?php echo $edit_supplier['phone'] ?? ''; ?>" style="width:100%; padding:8px; margin:5px 0;">
        <input type="text" name="address" placeholder="Address" value="<?php echo $edit_supplier['address'] ?? ''; ?>" style="width:100%; padding:8px; margin:5px 0;">

        <button type="submit" name="<?php echo $edit_supplier ? 'edit_supplier' : 'add_supplier'; ?>" class="<?php echo $edit_supplier ? 'btn-edit' : 'btn-add'; ?>">
            <?php echo $edit_supplier ? '✏️ Update Supplier' : '➕ Add Supplier'; ?>
        </button>
        <?php if ($edit_supplier): ?>
            <a href="suppliers.php" style="margin-left:10px; color:#555;">Cancel</a>
        <?php endif; ?>
    </form>

    <!-- Suppliers Table -->
    <table border="1" cellpadding="10" cellspacing="0" style="width:100%; border-collapse:collapse; background:white; text-align:left;">
        <tr style="background:#ddd;">
            <th>Serial</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Address</th>
            <th style="width:160px; text-align:center;">Action</th>
        </tr>
        <?php if (empty($supplier_rows)): ?>
            <tr><td colspan="6" style="text-align:center; color:#666;">No suppliers found.</td></tr>
        <?php else: ?>
            <?php $serial = count($supplier_rows); foreach ($supplier_rows as $row): ?>
            <tr>
                <td><?php echo $serial--; ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                <td><?php echo htmlspecialchars($row['address']); ?></td>
                <td style="white-space:nowrap; text-align:center;">
                    <a href="suppliers.php?edit=<?php echo $row['id']; ?>" class="btn-edit" style="text-decoration:none; margin-right:8px;">✏️ Edit</a>
                    <a href="suppliers.php?delete=<?php echo $row['id']; ?>" class="btn-delete" style="text-decoration:none;" onclick="return confirm('Are you sure?')">🗑️ Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
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
    <!-- <footer style="background-color: gray;"
            
            height: 10px;>

    <p>  Stock Management System</p>
</footer> -->


