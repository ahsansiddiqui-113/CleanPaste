<?php
// Creates the tables from schema.sql if they don't exist yet. Safe to run repeatedly.
// Usage: php tools/migrate.php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

use CloakPaste\Config\Database;

$pdo = null;
for ($i = 1; $i <= 20; $i++) {   // the database may still be starting
    try { $pdo = Database::getInstance(); break; }
    catch (Throwable $e) { fwrite(STDERR, "waiting for database ($i/20): " . $e->getMessage() . "\n"); sleep(3); }
}
if (!$pdo) { fwrite(STDERR, "Could not connect to the database.\n"); exit(1); }

if ($pdo->query("SHOW TABLES LIKE 'users'")->fetch()) {
    echo "Tables already exist - nothing to do.\n";
    exit(0);
}

$sql = preg_replace('/--[^\n]*/', '', file_get_contents(__DIR__ . '/../schema.sql'));
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    $pdo->exec($stmt);
}
echo "Schema created.\n";
