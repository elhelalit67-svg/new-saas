#!/usr/bin/env sh
set -eu
php -l app/index.php
php -l src/TenantContext.php
php -l src/Database.php
php -l tests/TenantContextTest.php
php -l tests/DatabaseTest.php
php tests/TenantContextTest.php
php tests/DatabaseTest.php
sh tests/smoke.sh
