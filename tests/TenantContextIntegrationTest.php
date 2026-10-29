<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/TenantContext.php';
require_once __DIR__ . '/../src/TenantMembershipRepository.php';

$migrationPath = __DIR__ . '/../database/migrations/001_platform_foundation.sql';
$migration = file_get_contents($migrationPath);
if ($migration === false) {
    throw new RuntimeException('Could not read the platform foundation migration.');
}

$pdo = Database::connect();
$pdo->exec($migration);

$pdo->beginTransaction();
try {
    $insertTenant = $pdo->prepare(
        'INSERT INTO tenants (slug, name, status) VALUES (:slug, :name, :status) RETURNING id'
    );
    $insertDomain = $pdo->prepare(
        'INSERT INTO tenant_domains (tenant_id, hostname, is_verified, is_active)
         VALUES (:tenant_id, :hostname, :is_verified, :is_active)'
    );

    $insertTenant->execute(['slug' => 'it-alpha', 'name' => 'Integration Alpha', 'status' => 'active']);
    $alphaId = (int)$insertTenant->fetchColumn();
    $insertTenant->execute(['slug' => 'it-beta', 'name' => 'Integration Beta', 'status' => 'active']);
    $betaId = (int)$insertTenant->fetchColumn();
    $insertTenant->execute(['slug' => 'it-paused', 'name' => 'Integration Paused', 'status' => 'inactive']);
    $pausedId = (int)$insertTenant->fetchColumn();

    $domains = [
        [$alphaId, 'alpha.example.test', true, true],
        [$betaId, 'beta.example.test', true, true],
        [$alphaId, 'pending.example.test', false, true],
        [$alphaId, 'disabled.example.test', true, false],
        [$pausedId, 'paused.example.test', true, true],
    ];
    foreach ($domains as [$tenantId, $hostname, $verified, $active]) {
        $insertDomain->execute([
            'tenant_id' => $tenantId,
            'hostname' => $hostname,
            'is_verified' => $verified,
            'is_active' => $active,
        ]);
    }

    $alpha = TenantContext::resolve($pdo, 'ALPHA.EXAMPLE.TEST:443');
    if ($alpha === null || $alpha['id'] !== $alphaId) {
        throw new RuntimeException('Active verified hostname did not resolve to its tenant.');
    }

    $beta = TenantContext::resolve($pdo, 'beta.example.test');
    if ($beta === null || $beta['id'] !== $betaId || $beta['id'] === $alpha['id']) {
        throw new RuntimeException('Different tenant hostname did not resolve independently.');
    }

    foreach (['missing.example.test', 'pending.example.test', 'disabled.example.test', 'paused.example.test'] as $host) {
        if (TenantContext::resolve($pdo, $host) !== null) {
            throw new RuntimeException("Resolver should fail closed for {$host}.");
        }
    }

    // Seed one account per tenant and exercise the host-bound repository.
    $insertUser = $pdo->prepare(
        'INSERT INTO users (email, password_hash, display_name)
         VALUES (:email, :password_hash, :display_name) RETURNING id'
    );
    $insertUser->execute([
        'email' => 'alpha.integration@example.test',
        'password_hash' => password_hash('integration-only-secret', PASSWORD_DEFAULT),
        'display_name' => 'Alpha Member',
    ]);
    $alphaUserId = (int)$insertUser->fetchColumn();

    $insertUser->execute([
        'email' => 'beta.integration@example.test',
        'password_hash' => password_hash('integration-only-secret', PASSWORD_DEFAULT),
        'display_name' => 'Beta Member',
    ]);
    $betaUserId = (int)$insertUser->fetchColumn();

    $alphaRepo = TenantMembershipRepository::forHost($pdo, 'alpha.example.test');
    $betaRepo = TenantMembershipRepository::forHost($pdo, 'beta.example.test');
    if ($alphaRepo === null || $betaRepo === null) {
        throw new RuntimeException('Could not create repositories for valid tenant hosts.');
    }

    // Seed owner memberships directly as trusted fixture setup.
    $insertMembership = $pdo->prepare(
        'INSERT INTO tenant_memberships (tenant_id, user_id, role)
         VALUES (:tenant_id, :user_id, :role)'
    );
    $insertMembership->execute(['tenant_id' => $alphaId, 'user_id' => $alphaUserId, 'role' => 'center_owner']);
    $insertMembership->execute(['tenant_id' => $betaId, 'user_id' => $betaUserId, 'role' => 'center_owner']);

    $insertUser->execute([
        'email' => 'alpha.staff@example.test',
        'password_hash' => password_hash('integration-only-secret', PASSWORD_DEFAULT),
        'display_name' => 'Alpha Staff',
    ]);
    $alphaStaffId = (int)$insertUser->fetchColumn();

    $alphaRepo->add($alphaUserId, $alphaStaffId, 'teacher');
    $betaRepo->add($betaUserId, $betaUserId, 'teacher');

    $alphaMembers = $alphaRepo->listActive($alphaUserId);
    $alphaIds = array_column($alphaMembers, 'id');
    if (!in_array($alphaUserId, $alphaIds, true) || !in_array($alphaStaffId, $alphaIds, true) ||
        in_array($betaUserId, $alphaIds, true)) {
        throw new RuntimeException('Tenant A membership listing leaked or omitted a user.');
    }

    // A manager in Tenant B is not authorized to operate on Tenant A, even if
    // they know the IDs. Authentication/session code must supply this actor ID.
    $denied = false;
    try {
        $alphaRepo->listActive($betaUserId);
    } catch (DomainException) {
        $denied = true;
    }
    if (!$denied) {
        throw new RuntimeException('Tenant B manager was authorized in Tenant A.');
    }

    // Tenant A's owner cannot mutate Tenant B membership through Tenant A's repo.
    if ($alphaRepo->setMembershipStatus($alphaUserId, $betaUserId, 'teacher', 'inactive')) {
        throw new RuntimeException('Tenant A repository modified a membership owned by Tenant B.');
    }

    if (!$alphaRepo->setMembershipStatus($alphaUserId, $alphaStaffId, 'teacher', 'inactive')) {
        throw new RuntimeException('Tenant A owner could not update its own membership.');
    }

    $alphaActive = $alphaRepo->listActive($alphaUserId);
    if (count($alphaActive) !== 1 || $alphaActive[0]['id'] !== $alphaUserId) {
        throw new RuntimeException('Inactive membership remained visible or Tenant A data was leaked.');
    }

    $betaMembers = $betaRepo->listActive($betaUserId);
    $betaIds = array_column($betaMembers, 'id');
    if (!in_array($betaUserId, $betaIds, true)) {
        throw new RuntimeException('Tenant B membership was affected by Tenant A operations.');
    }
} finally {
    $pdo->rollBack();
}

echo "Tenant resolution and tenant-scoped membership integration tests passed.\n";
