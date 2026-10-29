<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/TenantContext.php';

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
} finally {
    $pdo->rollBack();
}

echo "TenantContext PostgreSQL integration tests passed.\n";
