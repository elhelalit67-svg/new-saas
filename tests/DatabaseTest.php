<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';

$keys = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_SSLMODE'];
$saved = [];
foreach ($keys as $key) {
    $value = getenv($key);
    $saved[$key] = $value;
    putenv($key);
}

try {
    Database::connect();
    fwrite(STDERR, "FAIL: missing DB configuration should be rejected.\n");
    exit(1);
} catch (RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'DB_HOST')) {
        fwrite(STDERR, "FAIL: unexpected configuration error: " . $exception->getMessage() . "\n");
        exit(1);
    }
} finally {
    foreach ($saved as $key => $value) {
        if ($value === false) {
            putenv($key);
        } else {
            putenv($key . '=' . $value);
        }
    }
}

echo "Database configuration tests passed (missing-secret fail-closed case).\n";
