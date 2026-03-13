<?php

namespace App\Services;

/**
 * SSLCommerzService — OOP wrapper around the SSLCommerz payment gateway.
 *
 * Converts the procedural helper functions from includes/sslcommerz_helper.php
 * into a clean service object with dependency injection.
 *
 * Usage:
 *   $ssl = new SSLCommerzService($storeId, $storePass, $sandbox);
 *   $result = $ssl->initPayment($payload);
 *   if ($result['ok']) header('Location: ' . $result['gateway_url']);
 */
class SSLCommerzService
{
    private string $storeId;
    private string $storePass;
    private bool   $sandbox;
    private bool   $demoMode;

    public function __construct(string $storeId, string $storePass, bool $sandbox = true, bool $demoMode = false)
    {
        $this->storeId   = $storeId;
        $this->storePass = $storePass;
        $this->sandbox   = $sandbox;
        $this->demoMode  = $demoMode;
    }

    // ------------------------------------------------------------------ endpoints

    public function baseUrl(): string
    {
        $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = $https ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';

        $basePath = (strpos($script, '/stock/') !== false)
            ? '/stock'
            : rtrim(dirname($script), '/\\');

        return $scheme . '://' . $host . $basePath;
    }

    private function apiEndpoint(int $version = 3): string
    {
        $base = $this->sandbox
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
        return "{$base}/gwprocess/v{$version}/api.php";
    }

    private function validatorEndpoint(): string
    {
        $base = $this->sandbox
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
        return "{$base}/validator/api/validationserverAPI.php";
    }

    // ------------------------------------------------------------------ init

    /**
     * Initialise a payment session with SSLCommerz.
     *
     * $payload must include all required SSLCommerz fields
     * (store_id, store_passwd, total_amount, currency, tran_id, etc.)
     * This method injects format=json automatically.
     *
     * @return array{ok: bool, gateway_url?: string, data?: array, error?: string}
     */
    public function initPayment(array $payload): array
    {
        $payload['store_id']     = $this->storeId;
        $payload['store_passwd'] = $this->storePass;
        $payload['format']       = 'json';

        // Try v3 first, fall back to v4 on sandbox timeout
        $result = $this->_postToGateway($this->apiEndpoint(3), $payload);

        if (!$result['ok'] && $this->sandbox) {
            $result = $this->_postToGateway($this->apiEndpoint(4), $payload);
        }

        return $result;
    }

    // ------------------------------------------------------------------ validate

    /**
     * Validate a transaction with the SSLCommerz IPN validator endpoint.
     *
     * @return array{ok: bool, data?: array, error?: string}
     */
    public function validateTransaction(string $valId): array
    {
        $url = $this->validatorEndpoint() . '?' . http_build_query([
            'val_id'      => $valId,
            'store_id'    => $this->storeId,
            'store_passwd' => $this->storePass,
            'v'           => 1,
            'format'      => 'json',
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => !$this->sandbox,
            CURLOPT_SSL_VERIFYHOST => $this->sandbox ? 0 : 2,
        ]);

        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $raw === '') {
            return ['ok' => false, 'error' => $err ?: 'Empty response from validator', 'http' => $http];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'Invalid JSON from validator', 'http' => $http];
        }

        return ['ok' => true, 'data' => $data];
    }

    // ------------------------------------------------------------------ DB helpers

    /**
     * Ensure the customer_payments table exists before writing to it.
     * Call once at the top of any payment-callback file.
     */
    public function ensurePaymentsTable(\mysqli $conn): void
    {
        $conn->query("
            CREATE TABLE IF NOT EXISTS customer_payments (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                customer_id     INT            NOT NULL,
                order_id        INT            NOT NULL,
                tran_id         VARCHAR(100)   NOT NULL,
                val_id          VARCHAR(100)   DEFAULT NULL,
                amount          DECIMAL(12,2)  NOT NULL,
                currency        VARCHAR(10)    DEFAULT 'BDT',
                card_type       VARCHAR(50)    DEFAULT NULL,
                status          VARCHAR(30)    NOT NULL DEFAULT 'pending',
                bank_tran_id    VARCHAR(100)   DEFAULT NULL,
                created_at      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tran_id    (tran_id),
                INDEX idx_order_id   (order_id),
                INDEX idx_customer   (customer_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    // ------------------------------------------------------------------ private cURL helper

    /**
     * POST payload to a gateway URL and return a normalised result array.
     *
     * @return array{ok: bool, gateway_url?: string, data?: array, error?: string, http?: int}
     */
    private function _postToGateway(string $url, array $payload): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_NOSIGNAL       => true,
            CURLOPT_DNS_CACHE_TIMEOUT => 600,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'Expect:',
            ],
        ]);

        if (defined('CURLOPT_TCP_KEEPALIVE')) {
            curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
            curl_setopt($ch, CURLOPT_TCP_KEEPIDLE,  30);
            curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 15);
        }

        $raw   = curl_exec($ch);
        $err   = curl_error($ch);
        $errno = curl_errno($ch);
        $http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $raw === '') {
            return [
                'ok'    => false,
                'error' => 'Gateway not responding: ' . ($err ?: 'Connection timeout'),
                'http'  => $http,
            ];
        }

        if ($http >= 500) {
            return ['ok' => false, 'error' => "Gateway error (HTTP {$http}). Try again later.", 'http' => $http];
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            // Log the raw response for debugging
            error_log("SSLCommerz Response (not JSON): " . substr($raw, 0, 500));
            
            // Try URL-encoded fallback
            $parsed = [];
            parse_str($raw, $parsed);
            if (isset($parsed['GatewayPageURL'])) {
                return ['ok' => true, 'gateway_url' => $parsed['GatewayPageURL'], 'data' => $parsed];
            }

            // Last-resort regex
            if (preg_match('/GatewayPageURL\s*[:=]\s*(https?:\/\/[^\s"\']+)/i', $raw, $m)) {
                return ['ok' => true, 'gateway_url' => $m[1], 'data' => ['GatewayPageURL' => $m[1]]];
            }

            return [
                'ok' => false, 
                'error' => 'Invalid response from gateway (HTTP ' . $http . '). Check test-sslcommerz.php for details.',
                'http' => $http,
                'raw_response' => substr($raw, 0, 200),  // Include first 200 chars for debugging
            ];
        }

        if (!empty($data['GatewayPageURL'])) {
            return ['ok' => true, 'gateway_url' => $data['GatewayPageURL'], 'data' => $data];
        }

        return [
            'ok'    => false,
            'error' => $data['failedreason'] ?? ($data['desc'] ?? 'Gateway did not return a redirect URL'),
            'data'  => $data,
        ];
    }
}
