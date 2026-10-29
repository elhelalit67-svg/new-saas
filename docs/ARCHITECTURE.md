# Architecture — Initial Decisions

This document records intended architecture, not a claim that these components are already implemented.

## Current state
The audited repository is a minimal PHP starter. No framework, database, router, authentication, queues, storage provider or test framework was detected in the repository tree.

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
