<?php

// SSLCommerz configuration
// Sandbox docs: https://developer.sslcommerz.com/
//
// IMPORTANT:
// - For security, you can set credentials in one of these ways:
//   1) Edit this file directly (simple)
//   2) Create `includes/sslcommerz_config_local.php` to override values (recommended)
//   3) Set environment variables SSLCOMMERZ_STORE_ID / SSLCOMMERZ_STORE_PASS

// Sandbox (true) => https://sandbox.sslcommerz.com
// Live (false)   => https://securepay.sslcommerz.com
$SSLCOMMERZ_SANDBOX = true;

// DEMO MODE: Set to true for local testing (no internet required)
// For production: Set to false and use real credentials
$SSLCOMMERZ_DEMO_MODE = false;

// Defaults (will be overridden by local config file or env vars if set)
$SSLCOMMERZ_STORE_ID = 'ecomm696d82eaadc7b';
$SSLCOMMERZ_STORE_PASS = 'ecomm696d82eaadc7b@ssl';

// IMPORTANT: For localhost testing with SSL Commerz sandbox:
// You MUST set a custom callback URL that matches your registered domain
// Because SSL Commerz won't accept localhost as a callback URL
// 
// Your registered domain is: www.nayeem.com
// To make this work:
// 1. Add this line to use your registered domain for callbacks:
$SSLCOMMERZ_CALLBACK_URL = 'http://www.nayeem.com/stock'; 
// 
// 2. Then add to your Windows hosts file:
//    127.0.0.1 www.nayeem.com
//    (File location: C:\Windows\System32\drivers\etc\hosts)
//
// After setting hosts file, test:
//    ping www.nayeem.com  (should return 127.0.0.1)
//
// Alternative: You can use ngrok to tunnel localhost:
//    ngrok http 80  (get forwarding URL like https://xxxx-xx-xxx-xx.ngrok.io)
//    $SSLCOMMERZ_CALLBACK_URL = 'https://xxxx-xx-xxx-xx.ngrok.io/stock';
//
// For production: set to empty string to auto-detect
// $SSLCOMMERZ_CALLBACK_URL = '';

// Optional local override (not committed)
$localConfig = __DIR__ . '/sslcommerz_config_local.php';
if (is_file($localConfig)) {
	require $localConfig;
}

// Environment variable override
$envStoreId = getenv('SSLCOMMERZ_STORE_ID');
if (!empty($envStoreId)) {
	$SSLCOMMERZ_STORE_ID = $envStoreId;
}

$envStorePass = getenv('SSLCOMMERZ_STORE_PASS');
if (!empty($envStorePass)) {
	$SSLCOMMERZ_STORE_PASS = $envStorePass;
}

// Currency
$SSLCOMMERZ_CURRENCY = 'BDT';
