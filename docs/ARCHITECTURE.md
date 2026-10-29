# Architecture — Initial Decisions

This document records intended architecture, not a claim that these components are already implemented.

## Current state
The audited repository is a minimal PHP starter. No framework, database, router, authentication, queues, storage provider or test framework was detected in the repository tree.

## Implemented foundation on the development branch
- `database/migrations/001_platform_foundation.sql` defines PostgreSQL tables for tenants, domain mappings, users, tenant memberships and platform audit events. This migration has not been applied to any database.
- `src/TenantContext.php` normalizes DNS hostnames and resolves only active, verified domain mappings using a parameterized query. It is not yet wired into an HTTP router, login flow, or tenant-scoped repository layer.
- `src/Database.php` creates a PDO PostgreSQL connection from environment variables only, validates connection parameters, defaults to TLS-required mode, and disables emulated prepares. No credentials are committed and no connection is opened during normal page requests yet.
- `tests/TenantContextTest.php` covers hostname normalization edge cases.
- `tests/DatabaseTest.php` covers missing configuration and invalid host, database, port and SSL-mode values without contacting a database.
- GitHub Actions is configured to lint PHP files and run smoke/configuration tests. Passing status for the latest branch commit has not yet been confirmed.

## Database environment
The connection factory requires `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` and `DB_PASSWORD`. `DB_SSLMODE` is optional and defaults to `require`; accepted values are `disable`, `allow`, `prefer`, `require`, `verify-ca` and `verify-full`. Production should use TLS with certificate verification where the database provider supports it. Never commit credentials. The PHP runtime must have `pdo_pgsql` installed.

The connection factory is not a migration runner and does not prove that a database is reachable or that the schema is installed.

## Proposed initial shape
- PHP application with a small front controller and explicit routes.
- Environment-based configuration; no secrets committed.
- Relational persistence with versioned migrations.
- Service layer for business rules; centralized tenant-aware repository/data-access layer.
- RBAC plus resource-level checks in server-side handlers.
- Audit events for sensitive and financial actions.
- File storage abstraction separating public assets from private student/finance files.
- Outbox/queue abstraction for notifications and external integrations.
- Automated unit/integration tests and CI before feature expansion.

## Multi-tenancy
Use a shared database with `tenant_id` on tenant-owned entities as the simplest initial candidate, subject to validation against the available host/database. Tenant context must be resolved from an active, verified hostname mapping; never accept client-supplied tenant context as authority. All reads and writes must be scoped centrally. Unique keys and foreign-key relationships should prevent cross-tenant associations where the database supports them. Background jobs must carry and validate tenant context explicitly.

## Core domain boundaries
- Platform: tenants, domains, plans, subscriptions and platform audit events.
- Center operations: users, role grants, academic years, branches, rooms, teachers, students, guardians, subjects, groups and schedules.
- Attendance: scheduled sessions and attendance records.
- Student finance: fee schedules, dues, payments, allocations, refunds/reversals and receipts.
- Learning: courses, lessons, files, assessments, attempts, answers and grades.
- Communications: notification intents, templates, delivery attempts and provider webhooks.

Platform subscriptions are distinct from student fees and center income.

## Hosting considerations
Wasmer remains the current target, but production suitability depends on persistent database availability, filesystem persistence, process/queue support, scheduled tasks, environment secrets, observability and backup/restore options. These have not been verified. Do not rely on ephemeral local disk for production records or private uploads.
