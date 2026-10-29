#!/usr/bin/env sh
set -eu
php -l app/index.php
php -l src/TenantContext.php
php -l tests/TenantContextTest.php
php tests/TenantContextTest.php
sh tests/smoke.sh
