<?php
declare(strict_types=1);

/**
 * Tenant context resolution primitives.
 *
 * The caller must pass the HTTP Host header, then use the resolved tenant
 * object for every tenant-owned query. Never accept a tenant ID from request
 * body, query string, or client-controlled headers as authority.
 */
final class TenantContext
{
    /**
     * Normalize a hostname without trusting forwarded-host headers.
     * Call with the raw HTTP_HOST; do not use X-Forwarded-Host unless a
     * trusted reverse-proxy configuration explicitly validates it.
     */
    public static function normalizeHost(string $host): ?string
    {
        $host = trim($host);
        if ($host === '' || strlen($host) > 300 || str_contains($host, ',') ||
            str_contains($host, '/') || str_contains($host, '\\') ||
            str_contains($host, '@') || preg_match('/[\x00-\x20\x7f]/', $host)) {
            return null;
        }

        // Strip a single optional port. IPv6 literals are intentionally not
        // accepted as tenant domains; tenant domains must be DNS hostnames.
        if (substr_count($host, ':') > 1) {
            return null;
        }
        if (str_contains($host, ':')) {
            [$host, $port] = explode(':', $host, 2);
            if ($port === '' || !ctype_digit($port) || (int)$port > 65535 || (int)$port < 1) {
                return null;
            }
        }

        $host = strtolower(rtrim($host, '.'));
        if ($host === '' || strlen($host) > 253) {
            return null;
        }

        // Accept ASCII DNS names only here. Internationalized names must be
        // supplied in their IDNA/Punycode form by the domain-management flow.
        if (!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/D', $host)) {
            return null;
        }

        return $host;
    }

    /**
     * Resolve an active, verified hostname mapping. Returns null (fail closed)
     * for unknown, inactive, or unverified domains.
     *
     * @return array{id:int, slug:string, name:string}|null
     */
    public static function resolve(PDO $pdo, string $rawHost): ?array
    {
        $hostname = self::normalizeHost($rawHost);
        if ($hostname === null) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT t.id, t.slug, t.name
             FROM tenant_domains d
             JOIN tenants t ON t.id = d.tenant_id
             WHERE d.hostname = :hostname
               AND d.is_active = TRUE
               AND d.is_verified = TRUE
               AND t.status = 'active'
             LIMIT 1"
        );
        $stmt->execute(['hostname' => $hostname]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return ['id' => (int)$row['id'], 'slug' => $row['slug'], 'name' => $row['name']];
    }
}
