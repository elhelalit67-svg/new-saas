<?php
declare(strict_types=1);

require_once __DIR__ . '/TenantContext.php';

/**
 * Tenant-scoped access to membership records.
 *
 * IMPORTANT: actorUserId arguments must come from a server-validated,
 * authenticated session principal, never from request data. This class is a
 * repository-level authorization boundary, not a substitute for login/session
 * security or resource-specific authorization elsewhere in the application.
 */
final class TenantMembershipRepository
{
    private function __construct(
        private PDO $pdo,
        private int $tenantId
    ) {
    }

    public static function forHost(PDO $pdo, string $rawHost): ?self
    {
        $tenant = TenantContext::resolve($pdo, $rawHost);
        if ($tenant === null) {
            return null;
        }

        return new self($pdo, $tenant['id']);
    }

    /**
     * @return list<array{id:int,email:string,display_name:string,role:string}>
     */
    public function listActive(int $actorUserId): array
    {
        $this->requireManager($actorUserId);

        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.email, u.display_name, m.role
             FROM tenant_memberships m
             JOIN users u ON u.id = m.user_id
             WHERE m.tenant_id = :tenant_id
               AND m.status = 'active'
               AND u.status = 'active'
             ORDER BY u.id, m.role"
        );
        $stmt->execute(['tenant_id' => $this->tenantId]);

        /** @var list<array{id:int,email:string,display_name:string,role:string}> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
        }
        unset($row);

        return $rows;
    }

    /**
     * List active and inactive memberships for the management screen.
     *
     * @return list<array{id:int,email:string,display_name:string,role:string,status:string}>
     */
    public function listForManagement(int $actorUserId): array
    {
        $this->requireManager($actorUserId);

        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.email, u.display_name, m.role, m.status
             FROM tenant_memberships m
             JOIN users u ON u.id = m.user_id
             WHERE m.tenant_id = :tenant_id
             ORDER BY u.id, m.role"
        );
        $stmt->execute(['tenant_id' => $this->tenantId]);

        /** @var list<array{id:int,email:string,display_name:string,role:string,status:string}> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
        }
        unset($row);

        return $rows;
    }

    /**
     * Create a membership in this tenant after verifying the actor's role.
     * Only owners can grant owner/admin roles; owners and admins can grant
     * non-administrative roles. The database validates allowed role values.
     */
    public function add(int $actorUserId, int $userId, string $role): void
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('User ID must be positive.');
        }

        $actorRole = $this->requireManager($actorUserId);
        if (in_array($role, ['center_owner', 'center_admin'], true) && $actorRole !== 'center_owner') {
            throw new DomainException('Only a center owner can grant owner/admin roles.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_memberships (tenant_id, user_id, role)
             VALUES (:tenant_id, :user_id, :role)'
        );
        $stmt->execute([
            'tenant_id' => $this->tenantId,
            'user_id' => $userId,
            'role' => $role,
        ]);
    }

    /**
     * Update only a membership in this tenant after role authorization.
     * Admins cannot change owner/admin memberships. Returns false if no
     * matching membership exists in this tenant.
     */
    public function setMembershipStatus(
        int $actorUserId,
        int $userId,
        string $role,
        string $status
    ): bool {
        $actorRole = $this->requireManager($actorUserId);

        if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
            throw new InvalidArgumentException('Unsupported membership status.');
        }
        if ($actorRole !== 'center_owner' && in_array($role, ['center_owner', 'center_admin'], true)) {
            throw new DomainException('Center admins cannot change owner/admin memberships.');
        }
        if ($actorUserId === $userId && $actorRole === 'center_owner' && $role === 'center_owner' && $status !== 'active') {
            throw new DomainException('An owner cannot deactivate their own owner membership.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE tenant_memberships
             SET status = :status
             WHERE tenant_id = :tenant_id
               AND user_id = :user_id
               AND role = :role'
        );
        $stmt->execute([
            'status' => $status,
            'tenant_id' => $this->tenantId,
            'user_id' => $userId,
            'role' => $role,
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Require an active owner/admin membership in this exact tenant.
     * Returns the actor's effective membership role or throws on denial.
     */
    private function requireManager(int $actorUserId): string
    {
        if ($actorUserId < 1) {
            throw new DomainException('Authentication is required.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT m.role
             FROM tenant_memberships m
             JOIN users u ON u.id = m.user_id
             WHERE m.tenant_id = :tenant_id
               AND m.user_id = :user_id
               AND m.status = 'active'
               AND u.status = 'active'
               AND m.role IN ('center_owner', 'center_admin')
             ORDER BY CASE m.role WHEN 'center_owner' THEN 0 ELSE 1 END
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $this->tenantId, 'user_id' => $actorUserId]);
        $role = $stmt->fetchColumn();

        if (!is_string($role)) {
            throw new DomainException('Active center owner/admin membership is required.');
        }

        return $role;
    }
}
