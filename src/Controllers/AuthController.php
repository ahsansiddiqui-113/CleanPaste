<?php
declare(strict_types=1);
namespace CloakPaste\Controllers;

use CloakPaste\Config\Database;
use CloakPaste\Services\BillingService;
use CloakPaste\Support\{Http, Session};

final class AuthController
{
    public function handle()
    {
        $action = $_GET['action'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'];

        if ($action === 'me' && $method === 'GET') {
            Http::json(['user' => Session::user(), 'csrf' => Session::csrf()]);
        }
        if ($method !== 'POST') Http::error('Method not allowed', 405);
        Http::requireCsrf();

        match ($action) {
            'register' => $this->register(),
            'login'    => $this->login(),
            'logout'   => $this->logout(),
            default    => Http::error('Unknown action', 404),
        };
    }

    private function credentials(): array
    {
        $b = Http::body();
        $email = strtolower(trim((string)($b['email'] ?? '')));
        $pass = (string)($b['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) Http::error('Enter a valid email address', 422);
        return [$email, $pass];
    }

    private function register()
    {
        [$email, $pass] = $this->credentials();
        // bcrypt silently truncates at 72 bytes
        if (strlen($pass) < 8 || strlen($pass) > 72) Http::error('Password must be 8-72 characters', 422);

        $db = Database::getInstance();
        try {
            $db->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)')
               ->execute([$email, password_hash($pass, PASSWORD_DEFAULT)]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') Http::error('An account with this email already exists', 409);
            throw $e;
        }
        $id = (int)$db->lastInsertId();
        (new BillingService($db))->recompute($email); // applies a purchase made before signup
        Session::login($id);
        Http::json(['user' => Session::user(), 'csrf' => Session::csrf()], 201);
    }

    private function login()
    {
        [$email, $pass] = $this->credentials();
        $st = Database::getInstance()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $st->execute([$email]);
        $u = $st->fetch();

        // Always run a verify so unknown emails take about as long as wrong passwords.
        $dummy = '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234';
        $ok = password_verify($pass, $u['password_hash'] ?? $dummy) && $u && $u['password_hash'] !== null;
        if (!$ok) {
            usleep(250000);
            Http::error('Incorrect email or password', 401);
        }
        Session::login((int)$u['id']);
        Http::json(['user' => Session::user(), 'csrf' => Session::csrf()]);
    }

    private function logout()
    {
        Session::logout();
        Http::json(['ok' => true]);
    }
}
