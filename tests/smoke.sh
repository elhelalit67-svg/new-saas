#!/usr/bin/env sh
set -eu

php -l app/index.php
test ! -e app/info.php
grep -q 'Content-Security-Policy' app/index.php
grep -q 'lang="ar" dir="rtl"' app/index.php
grep -q 'لا تُدخل بيانات شخصية أو مالية' app/index.php
printf '%s\n' 'Smoke checks passed.'
