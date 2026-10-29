# Test Plan

## Current evidence
- Unit tests cover hostname normalization and database configuration validation.
- A PostgreSQL integration test applies the foundation migration and checks verified/active tenant resolution plus tenant-scoped membership reads and writes using two independent tenants.
- The local environment used for this audit has PHP but no `pdo_pgsql` driver or local PostgreSQL service, so the database integration test has not been executed locally. Do not mark it verified until CI reports a successful run.

## Test layers
1. Static checks: PHP syntax lint, formatting and dependency/security checks.
2. Unit tests: validation, fee calculations, receipt numbering, tenant context and permission decisions.
3. Integration tests: database constraints, transactions, sessions, file authorization and import workflows.
4. Tenant isolation tests: use at least two independent test tenants; test reads and mutations, not just domain resolution.
5. End-to-end smoke tests: login, create student/group, record attendance, record payment, print receipt, parent access and result publication.
6. Failure/retry tests: idempotent payment requests, queue retries, webhook replay, database rollback and partial imports.
7. Performance tests: realistic student/group data volumes with environment and methodology recorded.
8. Recovery tests: restore a backup into a separate test environment and validate record counts/integrity.

## Acceptance criteria
- Every protected endpoint rejects unauthenticated or unauthorized requests.
- Tenant A cannot read or mutate Tenant B data, including via files, reports, exports and background jobs.
- Confirmed financial operations are not duplicated by request retries and are never silently overwritten.
- Invalid imports produce row-level errors and do not partially commit unless explicitly designed and documented.
- Exam answers and keys remain hidden until the configured release policy allows them.
- Test outcomes, commands, environment and known limitations are recorded; never mark VERIFIED based only on code inspection.
