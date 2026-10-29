#!/usr/bin/env sh
set -eu
php -l app/index.php
php -l src/TenantContext.php
php -l src/TenantMembershipRepository.php
php -l src/SessionSecurity.php
php -l src/Database.php
php -l tests/TenantContextTest.php
php -l tests/DatabaseTest.php
php -l tests/SessionSecurityTest.php
php -l tests/TenantContextIntegrationTest.php
php tests/TenantContextTest.php
php tests/DatabaseTest.php
php tests/SessionSecurityTest.php
if [ "${RUN_DB_INTEGRATION:-0}" = "1" ]; then
  php tests/TenantContextIntegrationTest.php
fi
sh tests/smoke.sh
