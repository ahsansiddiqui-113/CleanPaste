<?php
declare(strict_types=1);
namespace CloakPaste\Services;

use CloakPaste\Config\Database;
use PDO;

final class BillingService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function grant(string $email, string $plan, ?string $customerId): void
    {
        $email = strtolower(trim($email));
        $key = 'CP-' . strtoupper(implode('-', str_split(bin2hex(random_bytes(8)), 4)));
        // license_key is kept on conflict so repeated webhooks stay idempotent.
        $this->db->prepare(
            'INSERT INTO entitlements (email, plan_type, active, customer_id, license_key) VALUES (?, ?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE active = 1, customer_id = VALUES(customer_id)'
        )->execute([$email, $plan, $customerId, $key]);
        $this->recompute($email);
    }

    public function revoke(string $email, string $plan): void
    {
        $email = strtolower(trim($email));
        $this->db->prepare('UPDATE entitlements SET active = 0 WHERE email = ? AND plan_type = ?')->execute([$email, $plan]);
        $this->recompute($email);
    }

    /** Derive the user's plan from active entitlements: lifetime > monthly > free. */
    public function recompute(string $email): void
    {
        $email = strtolower(trim($email));
        $st = $this->db->prepare('SELECT plan_type, customer_id, license_key FROM entitlements WHERE email = ? AND active = 1');
        $st->execute([$email]);
        $rows = array_column($st->fetchAll(), null, 'plan_type');
        $best = $rows['lifetime'] ?? $rows['monthly'] ?? null;

        $this->db->prepare('UPDATE users SET is_pro = ?, plan_type = ?, customer_id = ?, license_key = ? WHERE email = ?')
            ->execute([$best ? 1 : 0, $best['plan_type'] ?? 'free', $best['customer_id'] ?? null, $best['license_key'] ?? null, $email]);
    }
}
