<?php
declare(strict_types=1);
namespace CloakPaste\Support;

final class Http
{
    public static function json(array $data, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $status)
    {
        self::json(['error' => $message], $status);
    }

    public static function body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) return [];
        $d = json_decode($raw, true);
        if (!is_array($d)) self::error('Invalid JSON body', 400);
        return $d;
    }

    /** State-changing requests must carry the session's CSRF token. */
    public static function requireCsrf(): void
    {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($sent) || !hash_equals(Session::csrf(), $sent)) {
            self::error('Invalid CSRF token', 403);
        }
    }
}
