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

function expectDatabaseConfigError(array $values, string $expectedMessage): void
{
    foreach ($values as $key => $value) {
        putenv($key . '=' . $value);
    }

    try {
        Database::connect();
        throw new RuntimeException('Expected configuration validation to fail.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), $expectedMessage)) {
            throw new RuntimeException(
                "Expected error containing '{$expectedMessage}', got '{$exception->getMessage()}'."
            );
        }
    } finally {
        foreach ($values as $key => $_value) {
            putenv($key);
        }
    }
}

$valid = [
    'DB_HOST' => 'localhost',
    'DB_PORT' => '5432',
    'DB_NAME' => 'alq_test',
    'DB_USER' => 'test_user',
    'DB_PASSWORD' => 'test_password',
    'DB_SSLMODE' => 'require',
];

try {
    try {
        Database::connect();
        throw new RuntimeException('Expected missing database configuration to fail.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'DB_HOST')) {
            throw new RuntimeException('Missing configuration was not rejected as expected.');
        }
    }

    expectDatabaseConfigError(array_replace($valid, ['DB_PORT' => '65536']), 'DB_PORT');
    expectDatabaseConfigError(array_replace($valid, ['DB_NAME' => 'bad;dbname=other']), 'DB_NAME');
    expectDatabaseConfigError(array_replace($valid, ['DB_HOST' => 'db;sslmode=disable']), 'DB_HOST');
    expectDatabaseConfigError(array_replace($valid, ['DB_SSLMODE' => 'unsafe']), 'DB_SSLMODE');


} finally {
    foreach ($saved as $key => $value) {
        if ($value === false) {
            putenv($key);
        } else {
            putenv($key . '=' . $value);
        }
    }
}

echo "Database configuration tests passed (missing config, port, database name, host and SSL mode).\n";
