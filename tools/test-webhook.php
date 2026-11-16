<?php
// Sends a correctly signed Lemon Squeezy-style webhook to your local server.
// Usage: php tools/test-webhook.php <scenario> <email> [base-url]
// Scenarios: lifetime | monthly | expire | refund | badsig
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

use CloakPaste\Config\Env;

[$_, $scenario, $email] = array_pad($argv, 3, null);
$base = $argv[3] ?? 'http://localhost:8000';
if (!$scenario || !$email) { fwrite(STDERR, "Usage: php tools/test-webhook.php <lifetime|monthly|expire|refund|badsig> <email> [base-url]\n"); exit(2); }

$secret = Env::get('LS_WEBHOOK_SECRET', '');
$monthly = Env::get('LS_VARIANT_MONTHLY', '');
$lifetime = Env::get('LS_VARIANT_LIFETIME', '');
if ($secret === '' || $monthly === '' || $lifetime === '') { fwrite(STDERR, "Set LS_WEBHOOK_SECRET, LS_VARIANT_MONTHLY, LS_VARIANT_LIFETIME in .env first.\n"); exit(2); }

$base_attrs = ['user_email' => $email, 'customer_id' => 4242];
$events = [
    'lifetime' => ['order_created', $base_attrs + ['status' => 'paid', 'first_order_item' => ['variant_id' => (int)$lifetime]]],
    'refund'   => ['order_refunded', $base_attrs + ['status' => 'refunded', 'first_order_item' => ['variant_id' => (int)$lifetime]]],
    'monthly'  => ['subscription_created', $base_attrs + ['status' => 'active', 'variant_id' => (int)$monthly]],
    'expire'   => ['subscription_expired', $base_attrs + ['status' => 'expired', 'variant_id' => (int)$monthly]],
    'badsig'   => ['order_created', $base_attrs + ['status' => 'paid', 'first_order_item' => ['variant_id' => (int)$lifetime]]],
];
if (!isset($events[$scenario])) { fwrite(STDERR, "Unknown scenario.\n"); exit(2); }

[$name, $attrs] = $events[$scenario];
$body = json_encode(['meta' => ['event_name' => $name], 'data' => ['type' => 'orders', 'attributes' => $attrs]]);
$sig = $scenario === 'badsig' ? str_repeat('0', 64) : hash_hmac('sha256', $body, $secret);

$ctx = stream_context_create(['http' => [
    'method' => 'POST', 'ignore_errors' => true, 'content' => $body,
    'header' => "Content-Type: application/json\r\nX-Signature: $sig\r\n",
]]);
$res = file_get_contents($base . '/api/webhook.php', false, $ctx);
preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
echo "$scenario ($name) -> HTTP " . ($m[1] ?? '?') . " $res\n";
