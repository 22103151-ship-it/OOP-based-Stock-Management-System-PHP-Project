<?php
session_start();
include '../config.php';
use App\Core\Auth;
use App\Services\AdminOrderService;
Auth::requireRole('admin');
include '../includes/header.php'; // Keep header, but no sidebar

$adminOrderService = new AdminOrderService($conn);

// -------- Handle Status Updates --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $adminOrderService->updateStaffOrderStatuses($_POST['status']);
    header("Location: seller_orders.php");
    exit;
}

// -------- Fetch Staff Orders --------
$orders = $adminOrderService->getStaffOrders();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Seller Orders (Admin)</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        /* Layout */
        html, body {
            height: 100%;
            margin: 0;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
        }
        .content {
            flex: 1;
            padding: 20px;
            max-width: 1000px;
            margin: 0 auto;
            width: 100%;
        }

        /* Table styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table th, table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        table th {
            background-color: blue;
            color: white;
        }
        table tr:nth-child(even) {
            background-color: #fafafa;
        }
        select {
            padding: 5px;
        }
        button {
            padding: 8px 15px;
            background: #555;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background: #333;
        }

        /* Footer styling */
        footer {
            background-color: #555;
            color: white;
            text-align: center;
            padding: 15px 0;
            flex-shrink: 0; /* ensures footer stays at bottom */
        }

        a.back-btn {
            display:inline-block; 
            margin-bottom:20px; 
            padding:8px 15px; 
            background:#555; 
            color:white; 
            border-radius:5px; 
            text-decoration:none;
        }
        a.back-btn:hover {
            background:#333;
        }

        /* Ensure table and content grow */
        .content table {
            table-layout: fixed;
        }
    </style>
</head>
<body>

<div class="content">
    <a href="dashboard.php" class="back-btn">Back</a>

    <h2>📦 Staff Orders</h2>

    <form method="post" action="seller_orders.php">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Placed By</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Status</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): ?>
                    <?php foreach($orders as $o): ?>
                    <tr>
                        <td><?php echo $o['id']; ?></td>
                        <td>Staff</td>
                        <td><?php echo htmlspecialchars($o['product_name']); ?></td>
                        <td><?php echo $o['quantity']; ?></td>
                        <td>
                            <select name="status[<?php echo $o['id']; ?>]">
                                <option value="Pending" <?php if ($o['status']=='Pending') echo 'selected'; ?>>Pending</option>
                                <option value="Delivered" <?php if ($o['status']=='Delivered') echo 'selected'; ?>>Delivered</option>
                            </select>
                        </td>
                        <td><?php echo $o['created_at']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center;">No orders yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top:15px; text-align:left;">
            <button type="submit">💾 Save Changes</button>
        </div>
    </form>
</div>



</body>
</html>

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

       
    </div>
    
    <footer style="background-color: gray;"
            
            height: 10px;>

    <p>  Stock Management System</p>
</footer>