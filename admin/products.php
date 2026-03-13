<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\ProductManagementService;
Auth::requireRole('admin');

$productService = new ProductManagementService($conn);
$message = '';

include '../includes/header.php'; // Keep your header

// ---------------- Add Product ----------------
if (isset($_POST['add_product'])) {
    $result = $productService->addProduct($_POST, $_FILES);
    $message = $result['message'] ?? '';
}

// ---------------- Edit Product ----------------
if (isset($_POST['edit_product'])) {
    $result = $productService->editProduct($_POST, $_FILES);
    $message = $result['message'] ?? '';
}

// ---------------- Delete Product ----------------
if (isset($_GET['delete'])) {
    $result = $productService->deleteProduct((int)$_GET['delete']);
    $message = $result['message'] ?? '';
}

// ---------------- Fetch Products ----------------
$product_rows = $productService->getProductsWithSupplier();
$suppliers = $productService->getSuppliers();

// ---------------- If editing, fetch product details ----------------
$edit_product = null;
if (isset($_GET['edit'])) {
    $edit_product = $productService->findById((int)$_GET['edit']);
}
?>

<div style="max-width:900px; margin:20px auto; padding:20px; background:#f8f8f8; border-radius:8px;">

    <!-- Back Button -->
    <a href="dashboard.php" style="display:inline-block; margin-bottom:20px; padding:8px 15px; background:#555; color:white; border-radius:5px; text-decoration:none;">Back </a>

    <h2>Manage Products</h2>
    <?php echo $message; ?>

    <!-- Add / Edit Product Form -->
    <form method="POST" enctype="multipart/form-data" autocomplete="off" style="margin-bottom: 30px;">
        <h3><?php echo $edit_product ? "Edit Product" : "Add New Product"; ?></h3>

        <input type="hidden" name="id" value="<?php echo $edit_product['id'] ?? ''; ?>">

        <input type="text" name="name" placeholder="Product Name" required 
               value="<?php echo $edit_product['name'] ?? ''; ?>" 
               style="width:100%; padding:10px; margin:8px 0; box-sizing:border-box; border:1px solid #ccc; border-radius:5px;" autocomplete="off">

        <input type="number" step="0.01" name="price" placeholder="Price" required 
               value="<?php echo $edit_product['price'] ?? ''; ?>" 
               style="width:100%; padding:10px; margin:8px 0; box-sizing:border-box; border:1px solid #ccc; border-radius:5px;" autocomplete="off">

        <input type="number" name="stock" placeholder="Stock" required 
               value="<?php echo $edit_product['stock'] ?? ''; ?>" 
               style="width:100%; padding:10px; margin:8px 0; box-sizing:border-box; border:1px solid #ccc; border-radius:5px;" autocomplete="off">

        <!-- Product Image Upload -->
        <label style="display:block; margin:8px 0 4px 0; font-weight:500;">Product Image:</label>
        <input type="file" name="image" accept="image/*" 
               style="width:100%; padding:10px; margin:8px 0; box-sizing:border-box; border:1px solid #ccc; border-radius:5px;">
        <?php if (!empty($edit_product['image'])): ?>
            <div style="margin:8px 0;">
                <small>Current image: <strong><?php echo htmlspecialchars($edit_product['image']); ?></strong></small><br>
                <img src="../assets/images/<?php echo htmlspecialchars($edit_product['image']); ?>" 
                     alt="Current product image" 
                     style="max-width:100px; max-height:100px; border:1px solid #ddd; margin-top:5px;">
            </div>
        <?php endif; ?>

        <!-- Supplier Dropdown -->
        <select name="supplier_id" style="width:100%; padding:10px; margin:8px 0; box-sizing:border-box; border:1px solid #ccc; border-radius:5px;">
            <option value="0">Select Supplier</option>
            <?php foreach($suppliers as $supplier): ?>
                <option value="<?php echo $supplier['id']; ?>" <?php 
                    if(isset($edit_product['supplier_id']) && $edit_product['supplier_id'] == $supplier['id']) echo 'selected'; 
                ?>>
                    <?php echo htmlspecialchars($supplier['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" name="<?php echo $edit_product ? 'edit_product' : 'add_product'; ?>" 
                class="<?php echo $edit_product ? 'btn-edit' : 'btn-add'; ?>">
            <?php echo $edit_product ? '✏️ Update Product' : '➕ Add Product'; ?>
        </button>
        <?php if ($edit_product): ?>
            <a href="products.php" style="margin-left:10px; color:#555; text-decoration:none; font-weight:600;">Cancel</a>
        <?php endif; ?>
    </form>

    <!-- Products Table -->
    <table border="1" cellpadding="10" cellspacing="0" style="width:100%; border-collapse:collapse; background:white; text-align:left;">
        <tr style="background:#ddd;">
            <th>Serial</th>
            <th>Image</th>
            <th>Name</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Supplier</th>
            <th>Created At</th>
            <th style="width:160px; text-align:center;">Action</th>
        </tr>
        <?php if (empty($product_rows)): ?>
            <tr><td colspan="8" style="text-align:center; color:#666;">No products found.</td></tr>
        <?php else: ?>
            <?php $serial = count($product_rows); foreach ($product_rows as $row): ?>
            <tr>
                <td><?php echo $serial--; ?></td>
                <td style="text-align:center;">
                    <?php if (!empty($row['image'])): ?>
                        <img src="../assets/images/<?php echo htmlspecialchars($row['image']); ?>" 
                             alt="<?php echo htmlspecialchars($row['name']); ?>" 
                             style="max-width:50px; max-height:50px; border:1px solid #ddd;">
                    <?php else: ?>
                        <span style="color:#999;">No image</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo number_format($row['price'],2); ?></td>
                <td><?php echo $row['stock']; ?></td>
                <td><?php echo htmlspecialchars($row['supplier_name'] ?? 'N/A'); ?></td>
                <td><?php echo $row['created_at']; ?></td>
                <td style="white-space: nowrap; text-align:center;">
                    <a href="products.php?edit=<?php echo $row['id']; ?>" class="btn-edit" style="display:inline-block; text-decoration:none; margin-right:8px;">✏️ Edit</a>
                    <a href="products.php?delete=<?php echo $row['id']; ?>" class="btn-delete" style="display:inline-block; text-decoration:none;" onclick="return confirm('Are you sure?')">🗑️ Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</div>

