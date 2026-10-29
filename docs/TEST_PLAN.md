# Test Plan

## Current evidence
No test framework or tests were present in the audited repository. No test command has yet been established or executed.

## Test layers
1. Static checks: PHP syntax lint, formatting and dependency/security checks.
2. Unit tests: validation, fee calculations, receipt numbering, tenant context and permission decisions.
3. Integration tests: database constraints, transactions, sessions, file authorization and import workflows.
4. Tenant isolation tests: use at least two independent test tenants.
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
