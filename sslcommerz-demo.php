<?php
/**
 * SSL Commerz Sandbox Demo & Testing Page
 * Direct integration with SSL Commerz payment gateway
 * Test Store: testecommlegx
 */

session_start();
include 'config.php';
require_once 'includes/sslcommerz_config.php';

$page = $_GET['page'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSL Commerz Sandbox Demo - Stock Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .header h1 {
            color: #667eea;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            line-height: 1.6;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            background: white;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .tab-btn.active {
            background: #667eea;
            color: white;
        }

        .tab-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            font-size: 14px;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        button {
            padding: 12px 30px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        button:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .info-box {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .success {
            color: #28a745;
            background: #d4edda;
            border-left-color: #28a745;
        }

        .error {
            color: #dc3545;
            background: #f8d7da;
            border-left-color: #dc3545;
        }

        .warning {
            color: #ffc107;
            background: #fff3cd;
            border-left-color: #ffc107;
        }

        .credentials-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .credentials-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }

        .credentials-table td:first-child {
            font-weight: 600;
            width: 200px;
            background: #f5f5f5;
        }

        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }

        form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        form > button {
            grid-column: 1 / -1;
        }

        .grid-full {
            grid-column: 1 / -1;
        }

        h2 {
            color: #667eea;
            margin-bottom: 20px;
        }

        h3 {
            color: #333;
            margin-top: 30px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 SSL Commerz Sandbox Demo</h1>
            <p>Direct integration with SSL Commerz payment gateway for testing</p>
        </div>

        <div class="tabs">
            <button class="tab-btn <?= $page === 'home' ? 'active' : '' ?>" onclick="window.location.href='?page=home'">
                <i class="fas fa-home"></i> Home
            </button>
            <button class="tab-btn <?= $page === 'credentials' ? 'active' : '' ?>" onclick="window.location.href='?page=credentials'">
                <i class="fas fa-key"></i> Credentials
            </button>
            <button class="tab-btn <?= $page === 'test-payment' ? 'active' : '' ?>" onclick="window.location.href='?page=test-payment'">
                <i class="fas fa-credit-card"></i> Test Payment
            </button>
            <button class="tab-btn <?= $page === 'api-test' ? 'active' : '' ?>" onclick="window.location.href='?page=api-test'">
                <i class="fas fa-flask"></i> API Test
            </button>
        </div>

        <div class="content">
            <?php if ($page === 'home'): ?>
                <h2>Welcome to SSL Commerz Sandbox Demo</h2>
                
                <div class="info-box success">
                    <strong><i class="fas fa-check-circle"></i> Connected!</strong>
                    Your payment gateway is configured and ready to test.
                </div>

                <h3>Quick Start Guide</h3>
                <ol style="line-height: 2;">
                    <li>Click on <strong>"Test Payment"</strong> tab to initiate a sample payment</li>
                    <li>Enter customer and product details</li>
                    <li>Click "Initiate Payment" to be redirected to SSL Commerz gateway</li>
                    <li>Complete the test transaction</li>
                    <li>Check the payment confirmation</li>
                </ol>

                <h3>Store Information</h3>
                <table class="credentials-table">
                    <tr>
                        <td>Store Name:</td>
                        <td><strong><?= htmlspecialchars('testecommlegx') ?></strong></td>
                    </tr>
                    <tr>
                        <td>Registered URL:</td>
                        <td><strong><?= htmlspecialchars('www.nayeem.com') ?></strong></td>
                    </tr>
                    <tr>
                        <td>Environment:</td>
                        <td><strong><?= $SSLCOMMERZ_SANDBOX ? 'Sandbox' : 'Live' ?></strong></td>
                    </tr>
                    <tr>
                        <td>Test Cards Available:</td>
                        <td>Visa, Mastercard, bKash, Nagad, Rocket</td>
                    </tr>
                </table>

                <h3>Test Card Numbers</h3>
                <div class="info-box warning">
                    <strong>Use these in sandbox to test:</strong>
                    <div style="margin-top: 10px; font-family: monospace;">
                        <div>💳 Visa: 4111111111111111</div>
                        <div>💳 Mastercard: 5555555555554444</div>
                        <div>🏦 bKash: 01611111111 (1234 as OTP)</div>
                        <div style="margin-top: 10px;">Any future expiry date, any 3-digit CVV</div>
                    </div>
                </div>

            <?php elseif ($page === 'credentials'): ?>
                <h2>Store Credentials</h2>
                
                <div class="info-box">
                    <strong>Current Configuration:</strong>
                </div>

                <table class="credentials-table">
                    <tr>
                        <td>Store ID:</td>
                        <td><code><?= htmlspecialchars($SSLCOMMERZ_STORE_ID) ?></code></td>
                    </tr>
                    <tr>
                        <td>Store Password:</td>
                        <td><code><?= htmlspecialchars(substr($SSLCOMMERZ_STORE_PASS, 0, 10)) ?>***</code></td>
                    </tr>
                    <tr>
                        <td>Sandbox Mode:</td>
                        <td><strong><?= $SSLCOMMERZ_SANDBOX ? '✅ Enabled' : '❌ Disabled' ?></strong></td>
                    </tr>
                    <tr>
                        <td>API Endpoint:</td>
                        <td><code>https://sandbox.sslcommerz.com/gwprocess/v3/api.php</code></td>
                    </tr>
                    <tr>
                        <td>Validator Endpoint:</td>
                        <td><code>https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php</code></td>
                    </tr>
                    <tr>
                        <td>Merchant Panel:</td>
                        <td><a href="https://sandbox.sslcommerz.com/manage/" target="_blank" style="color: #667eea; text-decoration: none;">https://sandbox.sslcommerz.com/manage/<i class="fas fa-external-link-alt"></i></a></td>
                    </tr>
                </table>

                <h3>Configuration File</h3>
                <div class="info-box">
                    <strong>Location:</strong> <code>includes/sslcommerz_config.php</code><br>
                    These credentials are automatically loaded from the configuration file.
                </div>

            <?php elseif ($page === 'test-payment'): ?>
                <h2>Test Payment Transaction</h2>
                
                <div class="info-box warning">
                    <strong>⚠️ Testing Mode:</strong> This will create a test transaction in SSL Commerz sandbox.
                </div>

                <?php
                    // Get order info from guest checkout
                    $amount_param = isset($_GET['amount']) ? (float)$_GET['amount'] : 0;
                    $order_id_param = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
                    $guest_id_param = isset($_GET['guest_id']) ? (int)$_GET['guest_id'] : 0;
                    $session_id = isset($_GET['session_id']) ? htmlspecialchars($_GET['session_id']) : '';
                    
                    // Determine if this is coming from guest checkout
                    $from_guest_checkout = !empty($session_id) && $order_id_param > 0;
                ?>

                <?php if ($from_guest_checkout): ?>
                <div class="info-box success">
                    <strong>✅ Order Created Successfully!</strong>
                    <div style="margin-top: 10px;">
                        Order ID: <strong>#<?= $order_id_param ?></strong> | 
                        Amount: <strong>৳<?= number_format($amount_param, 2) ?></strong>
                    </div>
                </div>
                <?php endif; ?>

                <form id="paymentForm" method="POST" action="sslcommerz-payment-gateway.php">
                    <div>
                        <label for="customer_name">Customer Name *</label>
                        <input type="text" id="customer_name" name="customer_name" value="<?= $from_guest_checkout ? 'Guest Customer' : 'Test Customer' ?>" <?= $from_guest_checkout ? 'readonly' : '' ?> style="<?= $from_guest_checkout ? 'background: #f5f5f5; cursor: not-allowed;' : '' ?>" required>
                    </div>

                    <div>
                        <label for="customer_email">Email Address *</label>
                        <input type="email" id="customer_email" name="customer_email" value="test@example.com" required>
                    </div>

                    <div>
                        <label for="customer_phone">Phone Number *</label>
                        <input type="tel" id="customer_phone" name="customer_phone" value="01712345678" required>
                    </div>

                    <div>
                        <label for="product_name">Product Name *</label>
                        <input type="text" id="product_name" name="product_name" value="<?= $from_guest_checkout ? 'Guest Bulk Order #' . $order_id_param : 'Test Product' ?>" required>
                    </div>

                    <div>
                        <label for="amount">Amount (BDT) *</label>
                        <input type="number" id="amount" name="amount" value="<?= $amount_param > 0 ? $amount_param : '100' ?>" min="1" step="0.01" required readonly style="background: #f5f5f5; cursor: not-allowed;">
                    </div>

                    <div>
                        <label for="order_id">Order ID *</label>
                        <input type="text" id="order_id" name="order_id" value="<?= $from_guest_checkout ? $order_id_param : 'TEST' . time() ?>" required <?= $from_guest_checkout ? 'readonly' : '' ?> style="<?= $from_guest_checkout ? 'background: #f5f5f5; cursor: not-allowed;' : '' ?>">
                    </div>

                    <div class="grid-full">
                        <label for="product_description">Product Description</label>
                        <textarea id="product_description" name="product_description" rows="3"><?= $from_guest_checkout ? 'Guest bulk order #' . $order_id_param : 'Test transaction for SSL Commerz sandbox' ?></textarea>
                    </div>

                    <!-- Hidden fields for tracking -->
                    <input type="hidden" name="order_id_db" value="<?= $order_id_param ?>">
                    <input type="hidden" name="guest_id" value="<?= $guest_id_param ?>">
                    <input type="hidden" name="session_id" value="<?= $session_id ?>">
                    <input type="hidden" name="from_guest_checkout" value="<?= $from_guest_checkout ? '1' : '0' ?>">

                    <button id="submitBtn" type="submit" style="grid-column: 1 / -1;">
                        <i class="fas fa-arrow-right"></i> Proceed to Payment
                    </button>
                </form>

                <script>
                document.getElementById('paymentForm').addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const btn = document.getElementById('submitBtn');
                    const originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    
                    try {
                        const formData = new FormData(this);
                        const response = await fetch('sslcommerz-payment-gateway.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const responseText = await response.text();
                        console.log('Gateway response:', responseText);
                        
                        // Check if it's HTML (successful redirect page)
                        if (responseText.includes('<!DOCTYPE html>') || responseText.includes('<html')) {
                            // Success - show the redirect page
                            document.open();
                            document.write(responseText);
                            document.close();
                        } else {
                            // Try to parse as JSON (error response)
                            try {
                                const data = JSON.parse(responseText);
                                alert('Payment Error: ' + (data.message || 'Unknown error') + 
                                    (data.details?.raw_response ? '\n\nRaw: ' + data.details.raw_response : ''));
                                btn.disabled = false;
                                btn.innerHTML = originalText;
                            } catch (e) {
                                alert('Payment Error: Invalid response from server\n\n' + responseText.substring(0, 200));
                                btn.disabled = false;
                                btn.innerHTML = originalText;
                            }
                        }
                    } catch (err) {
                        alert('Error: ' + err.message);
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                        console.error('Payment form error:', err);
                    }
                });
                </script>

            <?php elseif ($page === 'api-test'): ?>
                <h2>API Connection Test</h2>
                
                <div class="info-box">
                    Test your API connection to SSL Commerz gateway
                </div>

                <?php
                $test_payload = [
                    'store_id'        => $SSLCOMMERZ_STORE_ID,
                    'store_passwd'    => $SSLCOMMERZ_STORE_PASS,
                    'total_amount'    => 100,
                    'currency'        => 'BDT',
                    'tran_id'         => 'APITEST' . time(),
                    'success_url'     => 'http://www.nayeem.com/stock/guest_payment_success.php',
                    'fail_url'        => 'http://www.nayeem.com/stock/guest_payment_fail.php',
                    'cancel_url'      => 'http://www.nayeem.com/stock/guest_payment_cancel.php',
                    'product_name'    => 'Test API Product',
                    'cus_name'        => 'API Test User',
                    'cus_email'       => 'api@test.com',
                    'cus_phone'       => '01700000000',
                    'format'          => 'json',
                ];

                $api_url = 'https://sandbox.sslcommerz.com/gwprocess/v3/api.php';

                // Test connection
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL            => $api_url,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => http_build_query($test_payload),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT        => 20,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]);

                $response = curl_exec($ch);
                $error    = curl_error($ch);
                $errno    = curl_errno($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                // Display results
                echo '<h3>API Test Results</h3>';
                
                if ($error) {
                    echo '<div class="info-box error">';
                    echo '<strong>❌ Connection Error:</strong> ' . htmlspecialchars($error) . ' (errno: ' . $errno . ')';
                    echo '</div>';
                } else {
                    echo '<div class="info-box success">';
                    echo '<strong>✅ Connected to API</strong>';
                    echo '</div>';
                }

                echo '<div class="info-box">';
                echo '<strong>HTTP Status Code:</strong> ' . $http_code . '<br>';
                echo '<strong>Response Size:</strong> ' . strlen($response) . ' bytes<br>';
                echo '<strong>Timestamp:</strong> ' . date('Y-m-d H:i:s');
                echo '</div>';

                if ($response) {
                    echo '<h3>Response Data</h3>';
                    echo '<pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;">';
                    echo htmlspecialchars(substr($response, 0, 1000));
                    if (strlen($response) > 1000) {
                        echo "\n\n... (" . (strlen($response) - 1000) . " more characters)";
                    }
                    echo '</pre>';

                    $json = json_decode($response, true);
                    if ($json) {
                        echo '<h3>Parsed JSON Response</h3>';
                        echo '<pre style="background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;">';
                        echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                        echo '</pre>';
                    }
                }
                ?>

            <?php endif; ?>
        </div>
    </div>
</body>
</html>
