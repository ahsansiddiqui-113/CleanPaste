<?php
declare(strict_types=1);
namespace CloakPaste\Controllers;

use CloakPaste\Config\Env;
use CloakPaste\Services\BillingService;
use CloakPaste\Support\Http;

/** Lemon Squeezy webhooks, authenticated by HMAC-SHA256 of the raw body (X-Signature). */
final class WebhookController
{
    public function handle()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Http::error('Method not allowed', 405);

        $secret = Env::get('LS_WEBHOOK_SECRET', '');
        if ($secret === '') Http::error('Webhook not configured', 500);

        $raw = file_get_contents('php://input') ?: '';
        $sig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
        if (!is_string($sig) || !hash_equals(hash_hmac('sha256', $raw, $secret), $sig)) {
            Http::error('Invalid signature', 401);
        }

        $p = json_decode($raw, true);
        if (!is_array($p)) Http::error('Invalid payload', 400);

        $event = (string)($p['meta']['event_name'] ?? '');
        $a = $p['data']['attributes'] ?? [];
        $email = strtolower(trim((string)($a['user_email'] ?? '')));
        $customer = isset($a['customer_id']) ? (string)$a['customer_id'] : null;
        if ($email === '') Http::json(['status' => 'ignored']);

        $monthly  = Env::get('LS_VARIANT_MONTHLY', '');
        $lifetime = Env::get('LS_VARIANT_LIFETIME', '');
        $billing = new BillingService();

        switch ($event) {
            case 'order_created':
                $variant = (string)($a['first_order_item']['variant_id'] ?? '');
                if ($lifetime !== '' && $variant === $lifetime && ($a['status'] ?? '') === 'paid') {
                    $billing->grant($email, 'lifetime', $customer);
                }
                break;
            case 'order_refunded':
                $variant = (string)($a['first_order_item']['variant_id'] ?? '');
                if ($lifetime !== '' && $variant === $lifetime) $billing->revoke($email, 'lifetime');
                break;
            case 'subscription_created':
            case 'subscription_updated':
            case 'subscription_resumed':
            case 'subscription_unpaused':
                if ($monthly === '' || (string)($a['variant_id'] ?? '') !== $monthly) break;
                // 'cancelled' = still paid up until ends_at; 'subscription_expired' ends access.
                if (in_array($a['status'] ?? '', ['active', 'on_trial', 'cancelled', 'past_due'], true)) {
                    $billing->grant($email, 'monthly', $customer);
                } elseif (in_array($a['status'] ?? '', ['expired', 'unpaid'], true)) {
                    $billing->revoke($email, 'monthly');
                }
                break;
            case 'subscription_expired':
                $billing->revoke($email, 'monthly');
                break;
            default:
                Http::json(['status' => 'ignored']);
        }
        Http::json(['status' => 'ok']);
    }
}
