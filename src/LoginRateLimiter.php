<?php
declare(strict_types=1);

/**
 * PostgreSQL-backed login throttling primitives.
 *
 * Store only keyed hashes of the normalized email/IP combination and IP.
 * The HTTP controller must check isBlocked() before authentication, call
 * recordFailure() after a failed attempt, and clearIdentityFailures() after
 * success. Keep the HMAC key stable across app instances and outside source.
 */
final class LoginRateLimiter
{
    private static function hmacKey(): string
    {
        $key = getenv('AUTH_RATE_LIMIT_KEY');
        if (!is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('AUTH_RATE_LIMIT_KEY must contain at least 32 characters.');
        }
        return $key;
    }

    /** @return array{0:string,1:string} */
    private static function bucketKeys(string $email, string $clientIp): array
    {
        if (filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('A valid client IP address is required.');
        }

        $key = self::hmacKey();
        $normalizedEmail = strtolower(trim($email));
        return [
            hash_hmac('sha256', "identity-ip\0" . $normalizedEmail . "\0" . $clientIp, $key),
            hash_hmac('sha256', "source-ip\0" . $clientIp, $key),
        ];
    }

    private static function validateLimits(int $tenantId, int $maxAttempts, int $windowSeconds): void
    {
        if ($tenantId < 1) {
            throw new InvalidArgumentException('Tenant ID must be positive.');
        }
        if ($maxAttempts < 1 || $maxAttempts > 100) {
            throw new InvalidArgumentException('Maximum attempts must be between 1 and 100.');
        }
        if ($windowSeconds < 30 || $windowSeconds > 86400) {
            throw new InvalidArgumentException('Window must be between 30 and 86400 seconds.');
        }
    }

    public static function isBlocked(
        PDO $pdo,
        int $tenantId,
        string $email,
        string $clientIp,
        int $maxAttempts = 5,
        int $windowSeconds = 900
    ): bool {
        self::validateLimits($tenantId, $maxAttempts, $windowSeconds);
        [$identityKey, $sourceKey] = self::bucketKeys($email, $clientIp);

        $stmt = $pdo->prepare(
            'SELECT 1
             FROM auth_login_attempts
             WHERE tenant_id = ?
               AND bucket_key IN (?, ?)
               AND window_started_at > CURRENT_TIMESTAMP - make_interval(secs => CAST(? AS integer))
               AND attempts >= ?
             LIMIT 1'
        );
        $stmt->execute([$tenantId, $identityKey, $sourceKey, $windowSeconds, $maxAttempts]);
        return $stmt->fetchColumn() !== false;
    }

    public static function recordFailure(
        PDO $pdo,
        int $tenantId,
        string $email,
        string $clientIp,
        int $windowSeconds = 900
    ): void {
        self::validateLimits($tenantId, 5, $windowSeconds);
        [$identityKey, $sourceKey] = self::bucketKeys($email, $clientIp);

        $stmt = $pdo->prepare(
            'INSERT INTO auth_login_attempts (tenant_id, bucket_key, attempts, window_started_at, updated_at)
             VALUES (?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON CONFLICT (tenant_id, bucket_key) DO UPDATE SET
               attempts = CASE
                 WHEN auth_login_attempts.window_started_at <=
                      CURRENT_TIMESTAMP - make_interval(secs => CAST(? AS integer))
                 THEN 1
                 ELSE auth_login_attempts.attempts + 1
               END,
               window_started_at = CASE
                 WHEN auth_login_attempts.window_started_at <=
                      CURRENT_TIMESTAMP - make_interval(secs => CAST(? AS integer))
                 THEN CURRENT_TIMESTAMP
                 ELSE auth_login_attempts.window_started_at
               END,
               updated_at = CURRENT_TIMESTAMP'
        );

        foreach ([$identityKey, $sourceKey] as $bucketKey) {
            $stmt->execute([$tenantId, $bucketKey, $windowSeconds, $windowSeconds]);
        }
    }

    public static function clearIdentityFailures(
        PDO $pdo,
        int $tenantId,
        string $email,
        string $clientIp
    ): void {
        self::validateLimits($tenantId, 5, 900);
        [$identityKey] = self::bucketKeys($email, $clientIp);
        $stmt = $pdo->prepare(
            'DELETE FROM auth_login_attempts WHERE tenant_id = ? AND bucket_key = ?'
        );
        $stmt->execute([$tenantId, $identityKey]);
    }
}
