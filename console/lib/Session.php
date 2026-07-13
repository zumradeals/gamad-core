<?php

declare(strict_types=1);

namespace Gamad\Console\Lib;

/**
 * Server-side session handling for the Console d'Exploitation (ADR-0016).
 * The Core API bearer token lives only here — never in a cookie value,
 * never sent to the browser, never in localStorage.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_name('gamad_console_session');
        session_start();
    }

    public static function token(): ?string
    {
        self::start();

        return $_SESSION['core_token'] ?? null;
    }

    public static function setToken(string $token): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['core_token'] = $token;
    }

    public static function isAuthenticated(): bool
    {
        return self::token() !== null;
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    public static function csrfToken(): string
    {
        self::start();
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function checkCsrf(?string $submitted): bool
    {
        self::start();
        $expected = $_SESSION['csrf_token'] ?? null;

        return $expected !== null && $submitted !== null && hash_equals($expected, $submitted);
    }

    public static function flash(string $key, ?string $message = null): ?string
    {
        self::start();
        if ($message !== null) {
            $_SESSION['flash'][$key] = $message;
            return null;
        }

        $value = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);

        return $value;
    }
}
