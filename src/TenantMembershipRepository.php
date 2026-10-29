<?php
declare(strict_types=1);

require_once __DIR__ . '/TenantContext.php';

/**
 * Tenant-scoped access to membership records.
 *
 * The tenant ID is never accepted from a request parameter. A repository is
 * created by resolving the request host against the verified domain registry,
 * and every read/write includes that resolved tenant ID in its SQL predicate.
 * Do not use forwarded-host headers unless a trusted proxy validates them.
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
    public function listActive(): array
    {
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
     * Create a membership in the resolved tenant only.
     * Database constraints validate the role and referenced user.
     */
    public function add(int $userId, string $role): void
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('User ID must be positive.');
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
     * Update only a membership that belongs to the resolved tenant.
     * Returns false when the membership is absent from this tenant.
     */
    public function setMembershipStatus(int $userId, string $role, string $status): bool
    {
        if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
            throw new InvalidArgumentException('Unsupported membership status.');
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
}
