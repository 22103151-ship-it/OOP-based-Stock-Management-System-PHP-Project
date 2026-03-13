<?php
session_start();
ob_start();
include '../config.php';
include '../includes/notification_functions.php';
require_once '../includes/sslcommerz_config.php';

use App\Core\Auth;
use App\Core\Request;
use App\Models\Customer;
use App\Models\Product;
use App\Models\CustomerOrder;
use App\Services\CustomerPaymentService;
use App\Services\SSLCommerzService;

Auth::requireRole('customer');

$customer_id = Auth::customerId();
if (!$customer_id) {
    header('Location: register.php');
    exit;
}

// Get customer type for discount display
$customerModel         = new Customer($conn);
$display_customer_type = $customerModel->getType($customer_id) ?? 'pro';

// Discount display constants
$display_base_discount    = ($display_customer_type === 'vip') ? 10 : 5;
$display_bulk_discount    = ($display_customer_type === 'vip') ? 20 : 15;
$display_bulk_threshold   = ($display_customer_type === 'vip') ? 70 : 50;
$display_min_per_product  = ($display_customer_type === 'vip') ? 10 : 20;

$product = null;
$message = '';
$productModel = new Product($conn);
$orderModel   = new CustomerOrder($conn);
$paymentService = new CustomerPaymentService($conn);

if (Request::get('product_id')) {
    $product = $productModel->findById(Request::getInt('product_id'));
    if ($product && (int)$product['stock'] <= 0) {
        $product = null;
    }
}

if (Request::isPost() && Request::hasPost('place_order')) {
    $product_id     = Request::postInt('product_id');
    $quantity       = Request::postInt('quantity');
    $payment_method = Request::postString('payment_method') ?: 'later';

    $product_data = $productModel->findById($product_id);

    if ($product_data && $quantity > 0 && $quantity <= (int)$product_data['stock']) {
        $customer_type    = $display_customer_type;
        $base_discount    = ($customer_type === 'vip') ? 10 : 5;
        $bulk_discount    = ($customer_type === 'vip') ? 20 : 15;
        $bulk_threshold   = ($customer_type === 'vip') ? 70 : 50;

        $unit_price       = (float)$product_data['price'];
        $discounted_price = $unit_price * (1 - $base_discount / 100);
        if ($quantity >= $bulk_threshold) {
            $discounted_price *= (1 - $bulk_discount / 100);
        }

        $order_id = $orderModel->create($customer_id, $product_id, $quantity, $discounted_price, $payment_method);

        if ($order_id) {
            handleCustomerProductRequest($customer_id, $product_id, 'order', $conn);

            if ($payment_method === 'bkash_sslcommerz') {
                // Fetch customer info for SSLCommerz
                $contact = $paymentService->getCustomerContact($customer_id);
                $cName = $contact['name'];
                $cEmail = $contact['email'];
                $cPhone = $contact['phone'];

                $ssl    = new SSLCommerzService($SSLCOMMERZ_STORE_ID, $SSLCOMMERZ_STORE_PASS, (bool)$SSLCOMMERZ_SANDBOX);
                $ssl->ensurePaymentsTable($conn);

                $tranId  = 'C' . $customer_id . 'O' . $order_id . 'T' . time();
                $amount  = round($discounted_price * $quantity, 2);

                $paymentService->createInitiatedCustomerPayment($order_id, $customer_id, $tranId, $amount);

                $ssl = new SSLCommerzService($SSLCOMMERZ_STORE_ID, $SSLCOMMERZ_STORE_PASS, (bool)$SSLCOMMERZ_SANDBOX, (bool)($SSLCOMMERZ_DEMO_MODE ?? false), $SSLCOMMERZ_CALLBACK_URL ?? '');
                $https    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $script   = $_SERVER['SCRIPT_NAME'] ?? '';
                $basePath = (strpos($script, '/stock/') !== false) ? '/stock' : rtrim(dirname($script), '/\\');
                $base     = $https . '://' . $host . $basePath;

                $payload = [
                    'store_id'        => $SSLCOMMERZ_STORE_ID,
                    'store_passwd'    => $SSLCOMMERZ_STORE_PASS,
                    'total_amount'    => $amount,
                    'currency'        => 'BDT',
                    'tran_id'         => $tranId,
                    'success_url'     => $base . '/customer/sslcommerz_success.php',
                    'fail_url'        => $base . '/customer/sslcommerz_fail.php',
                    'cancel_url'      => $base . '/customer/sslcommerz_cancel.php',
                    'shipping_method' => 'NO',
                    'product_name'    => (string)($product_data['name'] ?? 'Product'),
                    'product_category' => 'General',
                    'product_profile'  => 'general',
                    'cus_name'        => $cName,
                    'cus_email'       => $cEmail,
                    'cus_add1'        => 'Dhaka',
                    'cus_city'        => 'Dhaka',
                    'cus_postcode'    => '1200',
                    'cus_country'     => 'Bangladesh',
                    'cus_phone'       => $cPhone,
                    'value_a'         => (string)$order_id,
                    'value_b'         => (string)$customer_id,
                    'multi_card_name' => 'bkash',
                ];

                $init = $ssl->initPayment($payload);
                if (!empty($init['ok']) && !empty($init['gateway_url'])) {
                    header('Location: ' . $init['gateway_url']);
                    exit;
                }
                $message = '<div class="error-message">' . htmlspecialchars($init['error'] ?? 'Unable to start payment.') . '</div>';
            } else {
                $message = '<div class="success-message">Order placed successfully! Please confirm &amp; pay to proceed.</div>';
            }
        } else {
            $message = '<div class="error-message">Failed to place order. Please try again.</div>';
        }
    } else {
        $message = '<div class="error-message">Invalid quantity or insufficient stock.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Order - Customer</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --bg-color: #f4f7fc;
            --main-color: #2c3e50;
            --accent-color: #3498db;
            --card-bg: #ffffff;
            --border-color: #e1e8ed;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --text-color: #2c3e50;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
        }

        .page-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, var(--main-color), var(--accent-color));
            color: white;
            border-radius: 8px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }

        .order-form {
            max-width: 600px;
            margin: 0 auto;
            background: var(--card-bg);
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px var(--shadow-color);
        }

        .product-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
            border-left: 4px solid var(--accent-color);
        }

        .product-info h3 {
            margin: 0 0 10px 0;
            color: var(--text-color);
        }

        .product-info p {
            margin: 5px 0;
            color: #666;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-group input[type="number"] {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 1rem;
            font-family: inherit;
        }

        .form-group input[type="number"]:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .order-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .order-summary h4 {
            margin: 0 0 15px 0;
            color: var(--text-color);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .total-row {
            border-top: 1px solid var(--border-color);
            padding-top: 10px;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--accent-color);
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }

        .btn-primary {
            background: var(--accent-color);
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
            margin-left: 10px;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .order-form {
                margin: 0 15px;
                padding: 20px;
            }
        }

        .back-navigation {
            margin-bottom: 20px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--accent-color);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: background 0.2s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .back-btn:hover {
            background: #2980b9;
            color: white;
        }

        .back-btn i {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <div class="page-header">
        <h1>Place Your Order</h1>
    </div>

    <div class="back-navigation">
        <a href="products.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Products
        </a>
    </div>

    <?php echo $message; ?>

    <?php if ($product): 
        $original_price = $product['price'];
        $base_discounted_price = $original_price * (1 - $display_base_discount / 100);
    ?>
        <!-- Customer Type Badge -->
        <?php if ($display_customer_type === 'vip'): ?>
        <div style="text-align: center; margin-bottom: 20px;">
            <span style="display: inline-block; background: linear-gradient(135deg, #ffd700, #ff8c00); color: #000; padding: 8px 20px; border-radius: 25px; font-weight: 600;">
                <i class="fas fa-crown"></i> VIP Customer • <?php echo $display_base_discount; ?>% Off
            </span>
        </div>
        <?php else: ?>
        <div style="text-align: center; margin-bottom: 20px;">
            <span style="display: inline-block; background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; padding: 8px 20px; border-radius: 25px; font-weight: 600;">
                <i class="fas fa-star"></i> Pro Customer • <?php echo $display_base_discount; ?>% Off
            </span>
        </div>
        <?php endif; ?>

        <form class="order-form" method="POST">
            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

            <div class="product-info">
                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                <p>
                    <strong>Original Price:</strong> <span style="text-decoration: line-through; color: #999;">৳<?php echo number_format($original_price, 2); ?></span>
                    <span style="color: #27ae60; font-weight: bold;"> ৳<?php echo number_format($base_discounted_price, 2); ?></span> per unit
                    <span style="background: #27ae60; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8em; margin-left: 5px;"><?php echo $display_base_discount; ?>% OFF</span>
                </p>
                <p><strong>Available Stock:</strong> <?php echo $product['stock']; ?> units</p>
                <p style="color: #e67e22; font-size: 0.9em;"><i class="fas fa-info-circle"></i> Minimum: <?php echo $display_min_per_product; ?> stocks • Extra <?php echo $display_bulk_discount; ?>% off on <?php echo $display_bulk_threshold; ?>+ stocks</p>
            </div>

            <div class="form-group">
                <label for="quantity">Quantity (Min: <?php echo $display_min_per_product; ?>):</label>
                <input type="number" id="quantity" name="quantity" min="<?php echo $display_min_per_product; ?>" max="<?php echo $product['stock']; ?>" value="<?php echo $display_min_per_product; ?>" required>
            </div>

            <div class="order-summary">
                <h4>Order Summary</h4>
                <div class="summary-row">
                    <span>Original Price:</span>
                    <span style="text-decoration: line-through; color: #999;">৳<?php echo number_format($original_price, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Discounted Price (<?php echo $display_base_discount; ?>% off):</span>
                    <span style="color: #27ae60;">৳<?php echo number_format($base_discounted_price, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Quantity:</span>
                    <span id="quantity-display"><?php echo $display_min_per_product; ?></span>
                </div>
                <div class="summary-row" id="bulk-discount-row" style="display: none; color: #e67e22;">
                    <span>Bulk Discount (<?php echo $display_bulk_discount; ?>% on <?php echo $display_bulk_threshold; ?>+ stocks):</span>
                    <span id="bulk-discount-amount">-৳0.00</span>
                </div>
                <div class="summary-row total-row">
                    <span>Total:</span>
                    <span id="total-display">৳<?php echo number_format($base_discounted_price * $display_min_per_product, 2); ?></span>
                </div>
            </div>

            <div class="form-group" style="margin-top:14px;">
                <label>Payment:</label>
                <div style="display:grid; gap:10px;">
                    <label style="display:flex; align-items:center; gap:10px; padding:10px; border:1px solid var(--border-color); border-radius:8px; cursor:pointer;">
                        <input type="radio" name="payment_method" value="later" checked>
                        <span>Pay later (place order only)</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:10px; padding:10px; border:1px solid var(--border-color); border-radius:8px; cursor:pointer;">
                        <input type="radio" name="payment_method" value="bkash_sslcommerz">
                        <span>Pay now with bKash (SSLCommerz)</span>
                    </label>
                </div>
            </div>

            <div>
                <button type="submit" name="place_order" class="btn btn-primary">
                    <i class="fa-solid fa-cart-plus"></i> Place Order
                </button>
                <a href="products.php" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Back to Products
                </a>
            </div>
        </form>

        <script>
            // Update total when quantity changes
            document.getElementById('quantity').addEventListener('input', function() {
                const quantity = parseInt(this.value) || <?php echo $display_min_per_product; ?>;
                const baseDiscountedPrice = <?php echo $base_discounted_price; ?>;
                const bulkDiscount = <?php echo $display_bulk_discount; ?>;
                const bulkThreshold = <?php echo $display_bulk_threshold; ?>;
                
                let total = quantity * baseDiscountedPrice;
                let bulkSavings = 0;
                
                // Apply bulk discount if quantity meets threshold
                if (quantity >= bulkThreshold) {
                    bulkSavings = total * (bulkDiscount / 100);
                    total = total - bulkSavings;
                    document.getElementById('bulk-discount-row').style.display = 'flex';
                    document.getElementById('bulk-discount-amount').textContent = '-৳' + bulkSavings.toFixed(2);
                } else {
                    document.getElementById('bulk-discount-row').style.display = 'none';
                }

                document.getElementById('quantity-display').textContent = quantity;
                document.getElementById('total-display').textContent = '৳' + total.toFixed(2);
            });
        </script>
    <?php else: ?>
        <div class="order-form">
            <div class="error-message">
                <i class="fa-solid fa-exclamation-triangle"></i>
                Product not found or out of stock.
            </div>
            <a href="products.php" class="btn btn-primary">
                <i class="fa-solid fa-arrow-left"></i> Back to Products
            </a>
        </div>
    <?php endif; ?>
</body>
</html>