# Deployment Runbook — Initial

## Current deployment
The project README describes Wasmer Edge and suggests running PHP's built-in server. The actual deployment configuration, runtime version, persistent storage, database, environment variables and current live health have not been verified.

## Local smoke test (existing starter only)
```sh
php -v
php -l app/index.php
php -t app -S 127.0.0.1:8080
```
Then request `http://127.0.0.1:8080/`. This only smoke-tests the starter, not the future SaaS.

## Before production
1. Confirm PHP runtime and required extensions match the application.
2. Configure a persistent managed database or another verified durable database option.
3. Configure secrets via the host's environment secret facility.
4. Ensure uploads and application logs do not use ephemeral public paths.
5. Configure HTTPS, host/domain mapping, health checks, error reporting and rate limits.
6. Run migrations with a backup and rollback plan.
7. Run automated tests and security checks.
8. Verify tenant isolation and private file access.
9. Test backup restoration and document recovery targets.
10. Obtain explicit owner approval before production deployment or sensitive DNS changes.

## Rollback
Keep deployable immutable revisions. For schema changes, prefer backward-compatible migrations and define rollback or forward-fix procedures before release. Do not restore or alter production data destructively without approval.

## Status
No deployment was performed by this audit. Production readiness is not established.
