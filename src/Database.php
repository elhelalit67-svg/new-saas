<?php
declare(strict_types=1);

/**
 * PostgreSQL connection factory.
 *
 * Credentials are supplied only through environment variables. This class
 * deliberately has no fallback credentials and never logs connection secrets.
 */
final class Database
{
    public static function connect(): PDO
    {
        $required = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
        $config = [];

        foreach ($required as $key) {
            $value = getenv($key);
            if ($value === false) {
                throw new RuntimeException("Missing required database environment variable: {$key}");
            }
            $config[$key] = $value;
        }

        if ($config['DB_HOST'] === '' || str_contains($config['DB_HOST'], ';') ||
            str_contains($config['DB_HOST'], "\0")) {
            throw new RuntimeException('DB_HOST is invalid.');
        }

        if (!preg_match('/^[A-Za-z0-9_.-]+$/D', $config['DB_NAME'])) {
            throw new RuntimeException('DB_NAME is invalid.');
        }

        if (!ctype_digit($config['DB_PORT']) ||
            (int)$config['DB_PORT'] < 1 || (int)$config['DB_PORT'] > 65535) {
            throw new RuntimeException('DB_PORT must be an integer between 1 and 65535.');
        }

        $sslMode = getenv('DB_SSLMODE');
        $sslMode = $sslMode === false || $sslMode === '' ? 'require' : $sslMode;
        $allowedSslModes = ['disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'];
        if (!in_array($sslMode, $allowedSslModes, true)) {
            throw new RuntimeException('DB_SSLMODE is invalid.');
        }

        if (!extension_loaded('pdo_pgsql')) {
            throw new RuntimeException('The pdo_pgsql PHP extension is required.');
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
            $config['DB_HOST'],
            (int)$config['DB_PORT'],
            $config['DB_NAME'],
            $sslMode
        );

        return new PDO($dsn, $config['DB_USER'], $config['DB_PASSWORD'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
    }
}
