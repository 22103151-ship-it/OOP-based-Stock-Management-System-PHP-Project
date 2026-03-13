<?php
/**
 * Health Check Endpoint
 * Path: /api/health.php
 * Purpose: Monitor application status
 * Usage: curl http://localhost/api/health.php
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$health = [
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'uptime' => php_uname(),
    'environment' => $_ENV['APP_ENV'] ?? 'development',
];

$errors = [];

// ===================================
// Check Database Connection
// ===================================
try {
    require_once __DIR__ . '/../config.php';
    
    if (!$conn) {
        throw new Exception('Database connection not initialized');
    }
    
    if ($conn->connect_error) {
        throw new Exception('Database error: ' . $conn->connect_error);
    }
    
    // Test query
    $result = $conn->query("SELECT 1");
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    $health['database'] = 'ok';
    $health['database_version'] = $conn->server_version;
    
} catch (Exception $e) {
    $health['status'] = 'error';
    $health['database'] = 'failed';
    $errors[] = 'Database: ' . $e->getMessage();
}

// ===================================
// Check Critical Files
// ===================================
$critical_files = [
    'app/autoload.php',
    'config.php',
    'index.php',
    'README.md',
];

$health['files'] = [];
foreach ($critical_files as $file) {
    $path = __DIR__ . '/../' . $file;
    $health['files'][$file] = file_exists($path) ? 'ok' : 'missing';
    
    if (!file_exists($path)) {
        $errors[] = "File missing: $file";
    }
}

// ===================================
// Check Writable Directories
// ===================================
$writable_dirs = [
    'assets/images',
    'tmp',
    'logs',
];

$health['directories'] = [];
foreach ($writable_dirs as $dir) {
    $path = __DIR__ . '/../' . $dir;
    
    // Create if doesn't exist
    if (!is_dir($path)) {
        @mkdir($path, 0777, true);
    }
    
    $is_writable = is_writable($path);
    $health['directories'][$dir] = $is_writable ? 'ok' : 'not-writable';
    
    if (!$is_writable) {
        $errors[] = "Directory not writable: $dir";
    }
}

// ===================================
// Check PHP Extensions
// ===================================
$required_extensions = ['mysqli', 'json', 'curl'];
$health['php_extensions'] = [];

foreach ($required_extensions as $ext) {
    $health['php_extensions'][$ext] = extension_loaded($ext) ? 'ok' : 'missing';
    
    if (!extension_loaded($ext)) {
        $errors[] = "PHP extension missing: $ext";
    }
}

// ===================================
// Check PHP Configuration
// ===================================
$health['php'] = [
    'version' => phpversion(),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
];

// ===================================
// Set HTTP Status Code
// ===================================
if ($health['status'] === 'error' || !empty($errors)) {
    http_response_code(503);  // Service Unavailable
    $health['status'] = 'error';
    $health['errors'] = $errors;
}

// ===================================
// Output Response
// ===================================
echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
