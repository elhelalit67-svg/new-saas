<?php
declare(strict_types=1);

/**
 * Small session/CSRF primitives for the future HTTP authentication layer.
 *
 * This helper is not wired to routes yet. Call start() only after determining
 * HTTPS from the actual server configuration; do not trust arbitrary
 * X-Forwarded-Proto headers. Authentication and authorization still belong in
 * the eventual request middleware.
 */
final class SessionSecurity
{
    private const SESSION_NAME = 'alq_session';
    private const CSRF_KEY = '_csrf_token';

    public static function start(bool $secureTransport): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            throw new RuntimeException('Session must not already be active when security settings are applied.');
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        session_name(self::SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secureTransport,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (!session_start()) {
            throw new RuntimeException('Could not start a secure session.');
        }
    }

    public static function regenerateId(): void
    {
        self::requireActiveSession();
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Could not rotate the session identifier.');
        }
    }

    /**
     * Establish a tenant-bound authenticated identity after successful login.
     * Rotates the session ID and CSRF token to prevent session fixation.
     */
    public static function authenticate(int $userId, int $tenantId): void
    {
        self::requireActiveSession();
        if ($userId < 1 || $tenantId < 1) {
            throw new InvalidArgumentException('Authenticated user and tenant IDs must be positive.');
        }

        self::regenerateId();
        unset($_SESSION[self::CSRF_KEY]);
        $_SESSION['auth_user_id'] = $userId;
        $_SESSION['auth_tenant_id'] = $tenantId;
        $_SESSION['auth_at'] = time();
        self::csrfToken();
    }

    /** @return array{user_id:int,tenant_id:int,authenticated_at:int}|null */
    public static function authenticatedIdentity(): ?array
    {
        self::requireActiveSession();
        $userId = $_SESSION['auth_user_id'] ?? null;
        $tenantId = $_SESSION['auth_tenant_id'] ?? null;
        $authenticatedAt = $_SESSION['auth_at'] ?? null;

        if (!is_int($userId) || $userId < 1 ||
            !is_int($tenantId) || $tenantId < 1 ||
            !is_int($authenticatedAt) || $authenticatedAt < 1) {
            return null;
        }

        return [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'authenticated_at' => $authenticatedAt,
        ];
    }

    public static function csrfToken(): string
    {
        self::requireActiveSession();

        $token = $_SESSION[self::CSRF_KEY] ?? null;
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::CSRF_KEY] = $token;
        }

        return $token;
    }

    public static function verifyCsrfToken(mixed $submittedToken): bool
    {
        self::requireActiveSession();
        $expected = $_SESSION[self::CSRF_KEY] ?? null;

        return is_string($submittedToken)
            && is_string($expected)
            && strlen($expected) === 64
            && hash_equals($expected, $submittedToken);
    }

    public static function destroy(): void
    {
        self::requireActiveSession();

        $_SESSION = [];
        $params = session_get_cookie_params();
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] !== '' ? $params['path'] : '/',
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        if (!session_destroy()) {
            throw new RuntimeException('Could not destroy the session.');
        }
    }

    private static function requireActiveSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('An active session is required.');
        }
    }
}
