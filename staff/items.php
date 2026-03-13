<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\ProductLookupService;
Auth::requireRole('staff');
include '../includes/header.php'; // Header and sidebar

$productLookupService = new ProductLookupService($conn);

// ২. সার্চ লজিক (Search Logic) - নাম + বাংলা উচ্চারণ (English টাইপ) সাপোর্ট
$search_query = "";
$product_rows = [];

if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $search_query = trim($_GET['search']);
    $product_rows = $productLookupService->searchByName($search_query);
} else {
    // সার্চ না করলে সব দেখাবে
    $product_rows = $productLookupService->searchByName('');
}
?>

<div class="main-container">
    <a href="dashboard.php" class="back-btn">Back</a>

    <h2 class="page-title">Item List (Stock Check)</h2>

    <form method="GET" action="" class="search-form" style="text-align: center; margin-bottom: 20px;">
        <input type="text" name="search" placeholder="Search Product Name..." 
               value="<?php echo htmlspecialchars($search_query); ?>"
               style="padding: 8px; width: 250px; border: 1px solid #ccc; border-radius: 4px;">
        
        <button type="submit" class="btn-primary" style="padding: 8px 15px; margin-left: 5px;">Search</button>
        
        <?php if($search_query != ""): ?>
            <a href="items.php" class="reset-btn" style="margin-left: 5px;">Reset</a>
        <?php endif; ?>
    </form>

    <div class="table-container">
        <table class="styled-table">
            <thead>
                <tr>
                    <th>Serial</th>
                    <th>Product Name</th>
                    <th>Price</th>
                    <th>Stock Available</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (!empty($product_rows)):
                    $serial = count($product_rows);
                    foreach ($product_rows as $row): 
                        // স্টকের কালার লজিক
                        $stock_color = ($row['stock'] < 10) ? 'red' : 'green';
                ?>
                <tr>
                    <td><?php echo $serial--; ?></td>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo number_format($row['price'], 2); ?> BDT</td>
                    
                    <td style="color: <?php echo $stock_color; ?>; font-weight: bold;">
                        <?php echo $row['stock']; ?>
                    </td>

                    <td>
                        <?php if($row['stock'] > 0): ?>
                            <span style="background: #28a745; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px;">In Stock</span>
                        <?php else: ?>
                            <span style="background: #dc3545; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px;">Out of Stock</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php 
                    endforeach; 
                else:
                ?>
                    <tr>
                        <td colspan="5" style="text-align:center; color:red; padding: 20px;">No Product Found!</td>
                    </tr>
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

    .btn-primary {
        background: #007BFF;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    .reset-btn {
        padding: 8px 15px;
        background: #6c757d;
        color: white;
        border-radius: 4px;
        text-decoration: none;
        display: inline-block;
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
</style>