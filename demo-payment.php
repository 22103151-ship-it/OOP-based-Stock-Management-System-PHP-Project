<?php
/**
 * Demo Payment Gateway Page
 * This simulates SSLCommerz payment gateway for local testing
 * In production, this should NOT be used. Disable DEMO_MODE and use real SSLCommerz.
 * 
 * Usage: http://localhost:8000/demo-payment.php?tran_id=...&amount=...&success_url=...
 */

session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo Payment Gateway - Testing Mode</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .payment-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 8px;
        }

        .badge {
            display: inline-block;
            background: #ffc107;
            color: #333;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .demo-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 25px;
            font-size: 13px;
            color: #856404;
        }

        .payment-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .info-row label {
            color: #666;
            font-weight: 500;
        }

        .info-row value {
            color: #333;
            font-weight: 600;
        }

        .amount {
            font-size: 28px;
            color: #667eea;
            font-weight: 700;
            text-align: center;
            margin: 20px 0;
            padding: 15px 0;
            border-top: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        button {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        .note {
            font-size: 12px;
            color: #999;
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }

        .note strong {
            color: #333;
        }

        .processing {
            text-align: center;
            padding: 40px;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .processing p {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <?php
        $tran_id = $_GET['tran_id'] ?? '';
        $amount = $_GET['amount'] ?? 0;
        $success_url = $_GET['success_url'] ?? '';
        $fail_url = $_GET['fail_url'] ?? '';

        // Validate required params
        if (empty($tran_id) || empty($amount)) {
            ?>
            <div class="header">
                <h1>❌ Error</h1>
            </div>
            <div class="demo-warning">
                Missing required parameters for demo payment.
            </div>
            <div class="action-buttons">
                <button class="btn-danger" onclick="window.history.back()">Go Back</button>
            </div>
            <?php
            exit;
        }
        ?>

        <div class="header">
            <h1><i class="fas fa-credit-card"></i> Demo Payment</h1>
            <span class="badge">Testing Mode</span>
        </div>

        <div class="demo-warning">
            <strong><i class="fas fa-info-circle"></i> Demo Mode Active</strong><br>
            This is a test payment gateway. In production, you'll use real SSLCommerz.
        </div>

        <div class="payment-info">
            <div class="info-row">
                <label>Transaction ID:</label>
                <value><?php echo htmlspecialchars(substr($tran_id, 0, 40)); ?></value>
            </div>
            <div class="info-row">
                <label>Status:</label>
                <value><span style="color: #ffc107;">Pending</span></value>
            </div>
            <div class="info-row">
                <label>Payment Method:</label>
                <value>Card / bKash (Demo)</value>
            </div>
        </div>

        <div class="amount">
            ৳<?php echo number_format((float)$amount, 2); ?>
        </div>

        <div class="action-buttons">
            <button class="btn-success" onclick="completePayment('success')">
                <i class="fas fa-check-circle"></i> Success (Test)
            </button>
            <button class="btn-danger" onclick="completePayment('fail')">
                <i class="fas fa-times-circle"></i> Fail (Test)
            </button>
        </div>

        <div class="note">
            <strong>Note:</strong> Click "Success" to simulate successful payment.<br>
            Click "Fail" to test failure handling.
        </div>
    </div>

    <script>
        function completePayment(status) {
            const tranId = '<?php echo htmlspecialchars($tran_id); ?>';
            const amount = '<?php echo htmlspecialchars($amount); ?>';
            const successUrl = '<?php echo htmlspecialchars($success_url); ?>';
            const failUrl = '<?php echo htmlspecialchars($fail_url); ?>';

            // Create a dummy val_id for demo
            const valId = 'DEMO' + Math.random().toString(36).substr(2, 9).toUpperCase();

            if (status === 'success' && successUrl) {
                // Redirect to success URL with dummy validation ID
                const url = successUrl + (successUrl.includes('?') ? '&' : '?') + 
                           'val_id=' + encodeURIComponent(valId) + 
                           '&tran_id=' + encodeURIComponent(tranId);
                window.location.href = url;
            } else if (status === 'fail' && failUrl) {
                // Redirect to fail URL
                window.location.href = failUrl;
            } else {
                alert('No redirect URL configured for ' + status + ' status');
            }
        }
    </script>
</body>
</html>
