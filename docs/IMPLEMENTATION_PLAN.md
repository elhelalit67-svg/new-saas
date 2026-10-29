# Implementation Plan

Status labels: TODO, IN PROGRESS, BLOCKED, DONE, VERIFIED, DEPLOYED. DONE means code changed; VERIFIED and DEPLOYED require separate evidence.

## Phase 0 — Audit and safe baseline
- [x] Inventory repository tree and initial commit.
- [x] Record confirmed stack and current limitations.
- [x] Remove public PHP diagnostics endpoint from the development branch.
- [x] Replace sample output with a minimal informational entry point.
- [x] Add PHP lint/smoke workflow.
- [ ] Verify Wasmer deployment configuration and live response.

## Phase 1 — Application foundation
- [x] Draft PostgreSQL schema for tenants, verified domains, users, memberships and platform audit events.
- [x] Add fail-closed hostname normalization and verified-domain resolution primitive.
- [x] Add hostname normalization tests.
- [x] Add environment-only PDO PostgreSQL connection factory with input validation and TLS-required default.
- [x] Add database configuration tests for missing/invalid settings.
- [x] Add PostgreSQL integration test for active/verified domain resolution against a disposable CI database.
- [ ] Add a production-safe migration runner after confirming the database environment and migration policy.
- [ ] Add login, secure sessions, CSRF protection and password-reset policy.
- [ ] Add centralized tenant-scoped data-access layer and resource authorization.
- [ ] Add integration tests against PostgreSQL and tenant boundary tests.
- [ ] Confirm GitHub Actions results for the latest branch commit and verify PHP runtime/extensions.
- [ ] Verify database connectivity and migration against an explicitly designated development/test database.

## Phase 2 — Core center operations
- [ ] Academic years/terms, branches, rooms, subjects, teachers, groups and students.
- [ ] Parent relationships and resource-level authorization.
- [ ] Search, filtering, pagination, CSV/XLSX import preview, validation and export.
- [ ] Group enrollment history and schedule conflict checks.

## Phase 3 — Attendance and reception
- [ ] Session generation, attendance states, duplicate prevention and change history.
- [ ] Reception workflow, QR/barcode tokens and privacy-conscious kiosk mode.
- [ ] Attendance reporting and optional notifications.

## Phase 4 — Student finance
- [ ] Fee plans, installments, discounts, credits and arrears.
- [ ] Idempotent payment recording, receipt numbering and printable receipts.
- [ ] Refund/reversal workflows and immutable transaction history.
- [ ] Financial reconciliation and tests.

## Phase 5 — Grades and exams
- [ ] Gradebook and publication/review states.
- [ ] Extensible question bank, exam attempts, autosave and server-side timing.
- [ ] Automatic/manual grading, result release and audit history.

## Phase 6 — Portals and communication
- [ ] Role-scoped manager, teacher, student and parent views.
- [ ] Notification outbox and delivery status tracking.
- [ ] WhatsApp Business Cloud API adapter only after owner approval and credentials.

## Phase 7 — Center websites and learning content
- [ ] Tenant-branded public pages, enrollment requests and verified domain mapping.
- [ ] Course/lesson/file entities and access checks.
- [ ] Provider-neutral video metadata; no paid video service without approval.

## Phase 8 — Production hardening
- [ ] Security review, tenant-boundary tests and dependency scanning.
- [ ] Performance/load testing with recorded environment and results.
- [ ] Backup/restore rehearsal, operational alerts and deployment runbook.
- [ ] Production deployment only with explicit approval.

## Architecture decision principles
- A shared PostgreSQL database is the current candidate, not a final hosting decision. Validate Wasmer support, backups, connection limits and cost before provisioning.
- Tenant context is resolved from an active, verified hostname mapping; never accept client-supplied tenant context as authority.
- Tenant-owned reads/writes must be scoped centrally. The hostname resolver alone does not guarantee complete tenant isolation.
- Financial records should use append-only payment/refund events and transactional updates; never silently overwrite confirmed transactions.
- External services remain disabled/configuration-free until approved.

## Current priority
Finish the safe application foundation and test it before implementing student or financial modules. Do not claim a feature is production-ready without automated tests and deployment evidence.
