<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\SellProductService;
Auth::requireRole('admin');

$sellProductService = new SellProductService($conn);

$msg = "";

// ---------------- Handle Sell Product Form ----------------
if (isset($_POST['sell_product'])) {
    $_SESSION['msg'] = $sellProductService->sell((int)$_POST['product_id'], (int)$_POST['quantity']);

    // Redirect to prevent resubmission on refresh
    header("Location: sell_product.php");
    exit;
}

// ---------------- Fetch Products ----------------
$products = $sellProductService->getProductsForSell();

// ---------------- Fetch Sold Products History ----------------
$sold_products = $sellProductService->getHistory();

// ✅ include header only AFTER all redirects are done
include '../includes/header.php';
?>

<div style="max-width:900px; margin:20px auto; padding:20px; background:#f8f8f8; border-radius:8px;">
    <a href="dashboard.php" style="display:inline-block; margin-bottom:20px; padding:8px 15px; background:#555; color:white; border-radius:5px; text-decoration:none;">Back</a>
    <h2>Sell Product</h2>

    <?php 
    if (isset($_SESSION['msg'])) {
        echo $_SESSION['msg'];
        unset($_SESSION['msg']);
    }
    ?>

    <!-- Sell Product Form -->
    <form method="POST" style="margin-bottom:20px;">
        <label>Product:</label>
        <select name="product_id" id="product_select" required style="width:100%; padding:8px; margin:5px 0;">
            <option value="">-- Select Product --</option>
            <?php foreach($products as $p): ?>
                <option value="<?php echo $p['id']; ?>" data-stock="<?php echo $p['stock']; ?>">
                    <?php echo htmlspecialchars($p['name']); ?> (Stock: <?php echo $p['stock']; ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label>Quantity:</label>
        <input type="number" name="quantity" id="quantity" min="1" required style="width:100%; padding:8px; margin:5px 0;">

        <button type="submit" name="sell_product" class="btn-add">Sell</button>
    </form>

    <!-- Sold Products Table -->
    <h3>Sold Products History</h3>
    <table border="1" cellpadding="8" cellspacing="0" width="100%" style="border-collapse:collapse;">
        <thead style="background:#ddd;">
            <tr>
                <th>ID</th>
                <th>Product</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Total</th>
                <th>Current Stock</th>
                <th>Sold At</th>
                <th>Invoice</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($sold_products as $s): ?>
                <tr>
                    <td><?php echo $s['id']; ?></td>
                    <td><?php echo htmlspecialchars($s['product_name']); ?></td>
                    <td><?php echo $s['quantity']; ?></td>
                    <td><?php echo number_format($s['price'],2); ?></td>
                    <td><?php echo number_format($s['price']*$s['quantity'],2); ?></td>
                    <td><?php echo $s['current_stock'] !== null ? $s['current_stock'] : 'N/A'; ?></td>
                    <td><?php echo $s['created_at']; ?></td>
                    <td>
                        <a href="generate_invoice.php?sell_id=<?php echo $s['id']; ?>" target="_blank" class="btn-add" style="padding:5px 10px; text-decoration:none; border-radius:3px; display:inline-block;">Invoice</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    const productSelect = document.getElementById('product_select');
    const quantityInput = document.getElementById('quantity');

    quantityInput.addEventListener('input', () => {
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        if (!selectedOption) return;
        const stock = parseInt(selectedOption.getAttribute('data-stock'));
        const quantity = parseInt(quantityInput.value);
        if (quantity > stock) {
            alert('Stock not sufficient! Available: ' + stock);
            quantityInput.value = stock;
        }
    });
</script>

<!-- <footer style="background-color: gray; color:white; text-align:center; padding:15px 0;">
    <p>Stock Management System</p>
</footer> -->
