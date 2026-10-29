#!/usr/bin/env sh
set -eu

php -l app/index.php
php -l src/HttpApplication.php
test ! -e app/info.php
! grep -R -n 'phpinfo[[:space:]]*(' app src
grep -q 'HttpApplication::run' app/index.php
grep -q 'verifyCsrfToken' src/HttpApplication.php
grep -q 'LoginRateLimiter::isBlocked' src/HttpApplication.php
grep -q 'LoginRateLimiter::recordFailure' src/HttpApplication.php
grep -q "action=logout" src/HttpApplication.php
grep -q "Content-Security-Policy" src/HttpApplication.php
printf '%s\n' 'Smoke checks passed.'
