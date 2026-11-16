<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';

use CloakPaste\Controllers\WebhookController;
use CloakPaste\Support\Http;

try {
    (new WebhookController())->handle();
} catch (Throwable $e) {
    error_log((string)$e);
    Http::error('Server error', 500);
}
