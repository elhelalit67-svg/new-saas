<?php
declare(strict_types=1);

require_once __DIR__ . '/TenantContext.php';
require_once __DIR__ . '/SessionSecurity.php';

/**
 * Password authentication for the tenant resolved from the request host.
 *
 * This service deliberately does not emit HTTP responses or read request
 * globals. The eventual route must enforce CSRF on login/logout, rate-limit
 * attempts, determine HTTPS from trusted server configuration, and use the
 * returned identity only after currentUserId() revalidates it.
 */
final class AuthService
{
    // A fixed bcrypt hash keeps unknown-email attempts closer in cost to a
    // password verification. It is not a credential for any application user.
    private const DUMMY_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public static function login(PDO $pdo, string $rawHost, string $email, string $password): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('An active session is required for login.');
        }

        $tenant = TenantContext::resolve($pdo, $rawHost);
        $normalizedEmail = strtolower(trim($email));
        if (strlen($normalizedEmail) > 254 || filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            password_verify($password, self::DUMMY_PASSWORD_HASH);
            return false;
        }

        $stmt = $pdo->prepare(
            "SELECT id, password_hash
             FROM users
             WHERE email = :email
               AND status = 'active'
             LIMIT 1"
        );
        $stmt->execute(['email' => $normalizedEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($user)) {
            password_verify($password, self::DUMMY_PASSWORD_HASH);
            return false;
        }

        $storedHash = $user['password_hash'] ?? null;
        if (!is_string($storedHash) || !password_verify($password, $storedHash)) {
            return false;
        }

        if ($tenant === null) {
            return false;
        }

        $membership = $pdo->prepare(
            "SELECT 1
             FROM tenant_memberships
             WHERE tenant_id = :tenant_id
               AND user_id = :user_id
               AND status = 'active'
             LIMIT 1"
        );
        $membership->execute([
            'tenant_id' => $tenant['id'],
            'user_id' => (int)$user['id'],
        ]);
        if ($membership->fetchColumn() === false) {
            return false;
        }

        SessionSecurity::authenticate((int)$user['id'], $tenant['id']);
        return true;
    }

    /**
     * Return the authenticated user only if the session's tenant matches the
     * verified request hostname and the user still has an active membership.
     */
    public static function currentUserId(PDO $pdo, string $rawHost): ?int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('An active session is required.');
        }

        $identity = SessionSecurity::authenticatedIdentity();
        if ($identity === null) {
            return null;
        }

        $tenant = TenantContext::resolve($pdo, $rawHost);
        if ($tenant === null || $tenant['id'] !== $identity['tenant_id']) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT 1
             FROM users u
             JOIN tenant_memberships m ON m.user_id = u.id
             WHERE u.id = :user_id
               AND u.status = 'active'
               AND m.tenant_id = :tenant_id
               AND m.status = 'active'
             LIMIT 1"
        );
        $stmt->execute([
            'user_id' => $identity['user_id'],
            'tenant_id' => $identity['tenant_id'],
        ]);

        return $stmt->fetchColumn() === false ? null : $identity['user_id'];
    }
}
