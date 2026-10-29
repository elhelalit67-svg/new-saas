# Security Baseline

## Confirmed issue
- `app/info.php` invokes `phpinfo()`. This can reveal PHP configuration and runtime details if publicly reachable. Remove it from deployed application paths; use authenticated, controlled diagnostics instead.

## Required controls before handling real student/financial data
- Password hashing via PHP's password API or a maintained framework equivalent.
- Secure, HttpOnly and SameSite session cookies; session ID rotation after authentication; logout/session expiry.
- CSRF protection for state-changing browser requests.
- Output escaping and context-aware input validation.
- Parameterized database queries.
- Rate limits on authentication, OTP and expensive endpoints.
- Server-side authorization on every resource operation.
- Tenant context derived from a verified active host mapping, not request parameters.
- Private file storage with authorization checks or short-lived signed URLs.
- Strict upload size/type validation, randomized storage names and storage outside executable web paths.
- Secrets supplied through hosting environment configuration; never commit tokens or credentials.
- Audit events for permission changes, student data changes, grades, exports, payments, refunds and platform administration.
- Safe error responses without stack traces or secrets in production.
- Dependency/security scanning and tested backup/restore procedures.

## Multi-tenant test cases
- Tenant A cannot read, modify, enumerate or export Tenant B resources by changing IDs.
- Unknown/inactive hosts fail closed.
- API calls without valid tenant context fail closed.
- Queue jobs validate tenant context.
- Files, receipts, exports and webhook processing preserve tenant scope.
- Guardians can access only linked students; teachers only assigned resources.
- Platform administration is separated from center roles and audited.

## Data minimization
Do not log passwords, access tokens, OTPs, full payment credentials or unnecessary sensitive student information. Define retention and export policies before production launch.

## Status
This is a baseline checklist, not a completed security assessment. No dynamic security scan or runtime penetration test was performed during the initial repository inspection.
