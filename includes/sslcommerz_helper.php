<?php

require_once __DIR__ . '/sslcommerz_config.php';
require_once dirname(__DIR__) . '/app/bootstrap.php';
use App\Services\SSLCommerzService;

class SSLCommerzHelper
{
    public static function baseUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

        if (strpos($scriptName, '/stock/') !== false) {
            $basePath = '/stock';
        } else {
            $basePath = rtrim(dirname($scriptName), '/\\');
            if ($basePath === '' || $basePath === '.') {
                $basePath = '';
            }
        }

        return $scheme . '://' . $host . $basePath;
    }

    public static function apiEndpoint(bool $sandbox): string
    {
        return $sandbox
            ? 'https://sandbox.sslcommerz.com/gwprocess/v3/api.php'
            : 'https://securepay.sslcommerz.com/gwprocess/v3/api.php';
    }

    public static function apiEndpointV4(bool $sandbox): string
    {
        return $sandbox
            ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'
            : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php';
    }

    public static function validatorEndpoint(bool $sandbox): string
    {
        return $sandbox
            ? 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php'
            : 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php';
    }

    /** @return array<string,mixed> */
    public static function initPayment(array $payload, bool $sandbox): array
    {
        global $SSLCOMMERZ_STORE_ID, $SSLCOMMERZ_STORE_PASS;
        $service = new SSLCommerzService((string)$SSLCOMMERZ_STORE_ID, (string)$SSLCOMMERZ_STORE_PASS, $sandbox);
        return $service->initPayment($payload);
    }

    /** @return array<string,mixed> */
    public static function validateTransaction(string $valId, bool $sandbox, string $storeId, string $storePass): array
    {
        $service = new SSLCommerzService($storeId, $storePass, $sandbox);
        return $service->validateTransaction($valId);
    }

    public static function ensureCustomerPaymentsTable(mysqli $conn): void
    {
        global $SSLCOMMERZ_STORE_ID, $SSLCOMMERZ_STORE_PASS, $SSLCOMMERZ_SANDBOX;
        $service = new SSLCommerzService((string)$SSLCOMMERZ_STORE_ID, (string)$SSLCOMMERZ_STORE_PASS, (bool)$SSLCOMMERZ_SANDBOX);
        $service->ensurePaymentsTable($conn);
    }
}

function sslcommerz_base_url(): string
{
    return SSLCommerzHelper::baseUrl();
}

function sslcommerz_api_endpoint(bool $sandbox): string
{
    return SSLCommerzHelper::apiEndpoint($sandbox);
}

function sslcommerz_api_endpoint_v4(bool $sandbox): string
{
    return SSLCommerzHelper::apiEndpointV4($sandbox);
}

function sslcommerz_validator_endpoint(bool $sandbox): string
{
    return SSLCommerzHelper::validatorEndpoint($sandbox);
}

function sslcommerz_init_payment(array $payload, bool $sandbox): array
{
    return SSLCommerzHelper::initPayment($payload, $sandbox);
}

function sslcommerz_validate_transaction(string $valId, bool $sandbox, string $storeId, string $storePass): array
{
    return SSLCommerzHelper::validateTransaction($valId, $sandbox, $storeId, $storePass);
}

function ensure_customer_payments_table(mysqli $conn): void
{
    SSLCommerzHelper::ensureCustomerPaymentsTable($conn);
}
