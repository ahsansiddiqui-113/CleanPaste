<?php
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'CloakPaste\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require $file;
});

CloakPaste\Config\Env::load(dirname(__DIR__) . '/.env');
