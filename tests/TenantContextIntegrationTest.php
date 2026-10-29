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

    $alphaRepo->add($alphaUserId, 'teacher');
    $betaRepo->add($betaUserId, 'teacher');

    $alphaMembers = $alphaRepo->listActive();
    if (count($alphaMembers) !== 1 || $alphaMembers[0]['id'] !== $alphaUserId) {
        throw new RuntimeException('Tenant A membership listing leaked or omitted a user.');
    }

    if ($betaRepo->setMembershipStatus($alphaUserId, 'teacher', 'inactive')) {
        throw new RuntimeException('Tenant B repository modified a membership owned by tenant A.');
    }

    if (!$alphaRepo->setMembershipStatus($alphaUserId, 'teacher', 'inactive')) {
        throw new RuntimeException('Tenant A repository could not update its own membership.');
    }

    if ($alphaRepo->listActive() !== []) {
        throw new RuntimeException('Inactive membership remained visible in active membership listing.');
    }

    $betaMembers = $betaRepo->listActive();
    if (count($betaMembers) !== 1 || $betaMembers[0]['id'] !== $betaUserId) {
        throw new RuntimeException('Tenant B membership was affected by Tenant A operations.');
    }
} finally {
    $pdo->rollBack();
}

echo "Tenant resolution and tenant-scoped membership integration tests passed.\n";
