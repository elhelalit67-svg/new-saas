# Implementation Plan

Status labels: TODO, IN PROGRESS, BLOCKED, DONE, VERIFIED, DEPLOYED. DONE means code changed; VERIFIED and DEPLOYED require separate evidence.

## Phase 0 — Audit and safe baseline
- [x] Inventory repository tree and initial commit.
- [x] Record confirmed stack and current limitations.
- [ ] Remove public PHP diagnostics endpoint.
- [ ] Replace sample output with a minimal, testable application entry point.
- [ ] Add runtime instructions and a repeatable local smoke test.
- [ ] Verify Wasmer deployment configuration and live response.

## Phase 1 — Application foundation
- [ ] Select/confirm supported PHP runtime and dependency strategy.
- [ ] Add environment-based configuration and fail-safe defaults.
- [ ] Select a supported relational database based on actual hosting capabilities.
- [ ] Add migrations, request routing, error handling, structured logs and health checks.
- [ ] Add authentication, session protections, RBAC and audit logging.
- [ ] Implement tenant resolution from verified hostnames and centralized tenant scoping.
- [ ] Add automated tests and CI.

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
- Prefer a shared relational database with mandatory tenant scoping as the initial default only if hosting supports it; enforce scope in a central data-access/service layer and test it. Revisit database-per-tenant if isolation/compliance/scale requirements justify the operational cost.
- Never trust a tenant ID supplied by a browser. Resolve tenant from a verified host mapping and bind tenant context server-side.
- Financial records should use append-only payment/refund events and transactional updates; never silently overwrite confirmed transactions.
- External services remain disabled/configuration-free until approved.

## Current priority
Address the diagnostic endpoint and establish a real, testable baseline before implementing student or financial modules. Do not claim a feature is production-ready without automated tests and deployment evidence.
