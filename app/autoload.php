<?php

/**
 * PSR-4 compatible autoloader for the App\ namespace.
 * Maps App\Core\Auth  →  app/Core/Auth.php
 *      App\Models\User →  app/Models/User.php
 *      App\Services\*  →  app/Services/*.php
 */
spl_autoload_register(function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = __DIR__ . '/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file          = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
