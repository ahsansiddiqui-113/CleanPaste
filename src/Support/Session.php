<?php
declare(strict_types=1);
namespace CloakPaste\Support;

use CloakPaste\Config\Database;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        // Behind Railway/Render the proxy terminates TLS and forwards plain HTTP.
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_name('cloakpaste');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }

    public static function csrf(): string
    {
        self::start();
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function login(int $userId): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['uid'] = $userId;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    /** Read fresh from the DB each call so webhook plan changes apply immediately. */
    public static function user(): ?array
    {
        self::start();
        if (empty($_SESSION['uid'])) return null;
        $st = Database::getInstance()->prepare('SELECT id, email, is_pro, plan_type FROM users WHERE id = ?');
        $st->execute([$_SESSION['uid']]);
        $u = $st->fetch();
        if (!$u) return null;
        return ['id' => (int)$u['id'], 'email' => $u['email'], 'is_pro' => (bool)$u['is_pro'], 'plan_type' => $u['plan_type']];
    }
}
