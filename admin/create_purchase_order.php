<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\PurchaseOrderManagementService;
Auth::requireRole('admin');

include '../includes/header.php';

$message = '';
$purchaseService = new PurchaseOrderManagementService($conn);

// Load products and suppliers for the form
$products = $purchaseService->getProducts();
$suppliers = $purchaseService->getSuppliers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

    $result = $purchaseService->createFromAdminForm($product_id, $supplier_id, $quantity);
    $message = $result['message'] ?? '<div class="error-message">Failed to create order.</div>';
}
?>

<style>
    .po-container { max-width: 900px; margin: 30px auto; background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 10px 24px rgba(0,0,0,0.08); }
    .po-header { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
    .po-header h2 { margin:0; color:#1a2a47; }
    .po-form { display:grid; gap:16px; }
    .form-row { display:grid; gap:10px; }
    label { font-weight:600; color:#374151; }
    select, input[type="number"] { padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; }
    .actions { display:flex; gap:10px; flex-wrap:wrap; }
    .btn { padding:10px 16px; border:none; border-radius:8px; font-weight:600; cursor:pointer; }
    .btn-primary { background:#ff9800; color:#fff; }
    .btn-secondary { background:#e5e7eb; color:#374151; text-decoration:none; }
    .success-message { background:#ecfdf3; color:#166534; padding:10px 12px; border-radius:8px; margin-bottom:12px; }
    .error-message { background:#fef2f2; color:#b91c1c; padding:10px 12px; border-radius:8px; margin-bottom:12px; }
    .hint { font-size:12px; color:#6b7280; }
</style>

<div class="po-container">
    <div class="po-header">
        <h2>🧾 Create Purchase Order</h2>
        <a class="btn btn-secondary" href="dashboard.php">Back to Dashboard</a>
    </div>

    <?php echo $message; ?>

    <form method="POST" class="po-form">
        <div class="form-row">
            <label for="product_id">Product</label>
            <select id="product_id" name="product_id" required>
                <option value="">Select product</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['id']; ?>">
                        <?php echo htmlspecialchars($product['name']); ?> (Stock: <?php echo (int)$product['stock']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label for="supplier_id">Supplier</label>
            <select id="supplier_id" name="supplier_id" required>
                <option value="">Select supplier</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?php echo $supplier['id']; ?>">
                        <?php echo htmlspecialchars($supplier['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label for="quantity">Quantity</label>
            <input type="number" id="quantity" name="quantity" min="1" placeholder="Enter quantity" required>
            <div class="hint">Only numbers allowed.</div>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Create Order</button>
            <a class="btn btn-secondary" href="purchase_orders.php">View Purchase Orders</a>
        </div>
    </form>
</div>

<?php include '../includes/footer.php'; ?>



