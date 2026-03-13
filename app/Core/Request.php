<?php

namespace App\Core;

/**
 * Request — typed helpers for reading and sanitising HTTP input.
 *
 * Eliminates raw $_POST / $_GET access scattered across pages.
 * All values are type-cast, trimmed, and safe by default.
 *
 * Usage:
 *   $name     = Request::postString('name');
 *   $qty      = Request::postInt('quantity');
 *   $search   = Request::getString('q');
 *   $isPost   = Request::isPost();
 */
class Request
{
    // ------------------------------------------------------------------ POST

    public static function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public static function postInt(string $key, int $default = 0): int
    {
        return (int)($_POST[$key] ?? $default);
    }

    public static function postFloat(string $key, float $default = 0.0): float
    {
        return (float)($_POST[$key] ?? $default);
    }

    public static function postString(string $key, string $default = ''): string
    {
        return trim((string)($_POST[$key] ?? $default));
    }

    // ------------------------------------------------------------------ GET

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return (int)($_GET[$key] ?? $default);
    }

    public static function getString(string $key, string $default = ''): string
    {
        return trim((string)($_GET[$key] ?? $default));
    }

    // ------------------------------------------------------------------ method

    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }

    public static function isGet(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';
    }

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    // ------------------------------------------------------------------ has

    public static function has(string $key): bool
    {
        return isset($_POST[$key]) || isset($_GET[$key]);
    }

    public static function hasPost(string $key): bool
    {
        return isset($_POST[$key]);
    }

    public static function hasGet(string $key): bool
    {
        return isset($_GET[$key]);
    }
}
