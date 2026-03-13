<?php
/**
 * Test SSL Commerz API response
 * This helps debug what the payment gateway is returning
 */

echo "<h1>SSL Commerz API Test</h1>\n";

// Test data
$store_id = 'ecomm696d82eaadc7b';
$store_pass = 'ecomm696d82eaadc7b@ssl';
$sandbox = true;

$base_url = $sandbox 
    ? 'https://sandbox.sslcommerz.com' 
    : 'https://securepay.sslcommerz.com';

$endpoints = [
    'v3' => "{$base_url}/gwprocess/v3/api.php",
    'v4' => "{$base_url}/gwprocess/v4/api.php",
];

$payload = [
    'store_id'        => $store_id,
    'store_passwd'    => $store_pass,
    'total_amount'    => 100,
    'currency'        => 'BDT',
    'tran_id'         => 'TEST' . time(),
    'success_url'     => 'http://localhost/stock/guest_payment_success.php',
    'fail_url'        => 'http://localhost/stock/guest_payment_fail.php',
    'cancel_url'      => 'http://localhost/stock/guest_payment_cancel.php',
    'shipping_method' => 'NO',
    'product_name'    => 'Test Product',
    'product_category' => 'General',
    'product_profile'  => 'general',
    'cus_name'        => 'Test User',
    'cus_email'       => 'test@example.com',
    'cus_add1'        => 'Dhaka',
    'cus_city'        => 'Dhaka',
    'cus_postcode'    => '1200',
    'cus_country'     => 'Bangladesh',
    'cus_phone'       => '01234567890',
    'format'          => 'json',
];

foreach ($endpoints as $version => $url) {
    echo "<h2>Testing API v{$version}</h2>\n";
    echo "<p>URL: <code>$url</code></p>\n";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);

    $raw   = curl_exec($ch);
    $err   = curl_error($ch);
    $errno = curl_errno($ch);
    $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "<p><strong>HTTP Status:</strong> {$http}</p>\n";
    
    if ($err) {
        echo "<p style='color:red;'><strong>cURL Error:</strong> {$err} (errno: {$errno})</p>\n";
    }
    
    if (!$raw) {
        echo "<p style='color:red;'><strong>No Response:</strong> Empty response received</p>\n";
    } else {
        echo "<p><strong>Response Type:</strong> " . (strlen($raw) > 100 ? 'Large' : 'Small') . " (" . strlen($raw) . " bytes)</p>\n";
        echo "<h4>Raw Response (first 500 chars):</h4>\n";
        echo "<pre style='background:#f5f5f5; padding:10px; overflow-x:auto;'>" . htmlspecialchars(substr($raw, 0, 500)) . "</pre>\n";
        
        $json = json_decode($raw, true);
        if ($json) {
            echo "<h4>Parsed JSON:</h4>\n";
            echo "<pre style='background:#f0f0f0; padding:10px;'>" . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "</pre>\n";
        } else {
            echo "<p style='color:orange;'><strong>Not JSON:</strong> Could not parse as JSON</p>\n";
        }
    }
    
    echo "<hr/>\n";
}

echo "<h2>Credentials Check</h2>\n";
echo "<ul>\n";
echo "<li>Store ID: <code>$store_id</code></li>\n";
echo "<li>Store Pass: <code>$store_pass</code></li>\n";
echo "<li>Sandbox: <code>" . ($sandbox ? 'true' : 'false') . "</code></li>\n";
echo "</ul>\n";

echo "<p><strong>Note:</strong> This test uses sample credentials. If they're invalid, SSL Commerz will reject the request.</p>\n";
?>
