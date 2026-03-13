<?php

namespace App\Core;

/**
 * Auth — static helper for authentication and session management.
 *
 * Centralises all login/logout/role-check logic so every page
 * calls Auth::requireRole() instead of copy-pasting session checks.
 *
 * Usage:
 *   Auth::requireRole('admin');          // guard an admin page
 *   Auth::requireRole('customer','staff'); // multiple allowed roles
 *   $result = Auth::login($conn, $email, $password, 'customer');
 *   Auth::logout();
 */
class Auth
{
    // ------------------------------------------------------------------ guards

    /** True when a user is logged in. */
    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /** Current user's role, or null if not logged in. */
    public static function role(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Redirect to index.php unless the current session role is one of the
     * accepted roles.  Pass multiple role strings as separate arguments.
     *
     * Works from both root-level and sub-folder pages — uses relative path
     * matching current folder depth.
     */
    public static function requireRole(string ...$roles): void
    {
        if (!self::check() || !in_array(self::role(), $roles, true)) {
            // Detect whether we are one level deep (admin/, customer/, etc.)
            $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
            $root   = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR;
            $rel    = str_replace($root, '', realpath($script) ?: $script);
            $depth  = substr_count($rel, DIRECTORY_SEPARATOR);

            $redirect = $depth > 0 ? '../index.php' : 'index.php';
            header("Location: $redirect");
            exit;
        }
    }

    // ------------------------------------------------------------------ login

    /**
     * Validate credentials, populate $_SESSION, and return a result array.
     *
     * @param  \mysqli      $conn       Active DB connection
     * @param  string       $email
     * @param  string       $password   Plain-text password (project stores plain currently)
     * @param  string|null  $roleFilter If set, login is rejected unless role matches
     * @return array{success: bool, error?: string, role?: string}
     */
    public static function login(
        \mysqli $conn,
        string  $email,
        string  $password,
        ?string $roleFilter = null
    ): array {
        $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            return ['success' => false, 'error' => 'No user found with that email!'];
        }

        $stmt->bind_result($id, $name, $dbPassword, $role);
        $stmt->fetch();

        if ($roleFilter !== null && $role !== $roleFilter) {
            return ['success' => false, 'error' => 'This account is not registered as a ' . ucfirst($roleFilter) . '.'];
        }

        if ($password !== $dbPassword) {
            return ['success' => false, 'error' => 'Invalid password!'];
        }

        // Populate session
        $_SESSION['user_id']   = $id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_role'] = $role;

        // Customers also need customer_id
        if ($role === 'customer') {
            $stmt2 = $conn->prepare("SELECT id FROM customers WHERE user_id = ? LIMIT 1");
            $stmt2->bind_param('i', $id);
            $stmt2->execute();
            $stmt2->bind_result($customerId);
            if ($stmt2->fetch()) {
                $_SESSION['customer_id'] = $customerId;
            }
            $stmt2->close();
        }

        return ['success' => true, 'role' => $role];
    }

    // ------------------------------------------------------------------ logout

    /** Destroy the session completely and clear the session cookie. */
    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']
            );
        }
        session_destroy();
    }

    // ------------------------------------------------------------------ accessors

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function customerId(): ?int
    {
        return isset($_SESSION['customer_id']) ? (int)$_SESSION['customer_id'] : null;
    }

    public static function userName(): ?string
    {
        return $_SESSION['user_name'] ?? null;
    }

    /**
     * Populate session values for an authenticated user.
     * Useful for custom login/registration flows that already validated credentials.
     */
    public static function establishSession(
        int $userId,
        string $userName,
        string $role,
        ?int $customerId = null,
        ?string $customerType = null
    ): void {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $userName;
        $_SESSION['user_role'] = $role;

        if ($customerId !== null) {
            $_SESSION['customer_id'] = $customerId;
        }
        if ($customerType !== null) {
            $_SESSION['customer_type'] = $customerType;
        }
    }

    // ------------------------------------------------------------------ redirect helper

    /**
     * Redirect to the dashboard matching the current session role.
     * Call after a successful login instead of duplicating the if/elseif chain.
     */
    public static function redirectByRole(): void
    {
        $map = [
            'admin'    => 'admin/dashboard.php',
            'staff'    => 'staff/dashboard.php',
            'supplier' => 'supplier/dashboard.php',
            'customer' => 'customer/dashboard.php',
        ];
        header('Location: ' . ($map[self::role()] ?? 'index.php'));
        exit;
    }
}
