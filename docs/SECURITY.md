# Security Baseline

## Confirmed issue
- The original repository exposed a `phpinfo()` endpoint at `app/info.php`. It was removed from the development branch; confirm it is absent from any deployed artifact before release.

## Implemented foundation (not a complete security assessment)
- Tenant host resolution accepts only normalized ASCII DNS hostnames and resolves only active, verified mappings to active tenants.
- `TenantMembershipRepository::forHost()` creates a membership repository only after server-side host resolution. Its reads and writes always bind the resolved tenant ID; callers do not pass tenant IDs to membership methods.
- SQL statements use bound parameters for values.
- Database credentials come from environment configuration; PostgreSQL TLS is required by default in the connection factory.
- `SessionSecurity` provides hardened cookie/session settings, session ID rotation, random CSRF tokens with constant-time verification, and session destruction. Authenticated identities expire after 30 minutes of inactivity or 12 hours absolute.
- `AuthService` verifies stored password hashes, requires active membership in the hostname-resolved tenant, rotates the session ID and CSRF token after login, and revalidates the active user/membership and tenant host on each identity lookup.
- `LoginRateLimiter` stores tenant-scoped, keyed hashes for email/IP and IP-only buckets in PostgreSQL; raw emails and IP addresses are not stored in the rate-limit table. It requires `AUTH_RATE_LIMIT_KEY` (at least 32 characters), stable across app instances.
- `HttpApplication` is now wired to `app/index.php`. It provides initial login, logout (POST + CSRF only), and protected dashboard routes. It checks the rate limiter before password verification, records failed attempts, clears the identity/IP bucket after successful login, avoids trusting forwarded client-IP headers, sends basic security headers, and returns generic service errors. Plain HTTP is allowed only for localhost development.
- The dashboard is a placeholder only. This does not yet implement center administration, teacher/student records, role-based page permissions, account recovery, or a complete audit trail.

## Required before handling real student/financial data
- Add automated HTTP-level tests for login success/failure, CSRF rejection, logout, throttling, HTTPS enforcement, and cross-tenant session rejection. Current CI tests service primitives and static route smoke checks; this is not equivalent to an end-to-end security test.
- Configure a high-entropy `AUTH_RATE_LIMIT_KEY` outside source control and provide all database environment variables in the hosting environment.
- Verify HTTPS termination and trusted proxy configuration for the actual hosting topology before deployment. The controller intentionally does not trust forwarded protocol/IP headers.
- Password hashing via PHP's password API or a maintained framework equivalent.
- Output escaping and context-aware input validation.
- Rate limits on OTP and expensive endpoints.
- Server-side authentication and authorization on every resource operation.
- Tenant context derived from a verified active host mapping, not request parameters.
- Extend tenant-scoped access to every tenant-owned table, API, report, export, queue job, file, receipt and webhook; the membership repository is only the first vertical slice.
- Private file storage with authorization checks or short-lived signed URLs.
- Strict upload size/type validation, randomized storage names and storage outside executable web paths.
- Secrets supplied through hosting environment configuration; never commit tokens or credentials.
- Audit events for permission changes, student data changes, grades, exports, payments, refunds and platform administration.
- Dependency/security scanning and tested backup/restore procedures.

## Multi-tenant test cases
- Tenant A cannot read, modify, enumerate or export Tenant B resources by changing IDs.
- The membership integration test checks that Tenant A's listing excludes Tenant B members, Tenant B cannot change Tenant A's membership, and each tenant's own membership operations remain independent.
- Unknown/inactive hosts fail closed.
- API calls without valid tenant context fail closed.
- Queue jobs validate tenant context.
- Files, receipts, exports and webhook processing preserve tenant scope.
- Guardians can access only linked students; teachers only assigned resources.
- Platform administration is separated from center roles and audited.

## Data minimization
Do not log passwords, access tokens, OTPs, full payment credentials or unnecessary sensitive student information. Define retention and export policies before production launch.

## Status
This is a baseline checklist, not a completed security assessment. PostgreSQL integration tests run in CI. Static smoke checks do not prove the HTTP routes secure; end-to-end route tests and runtime security review remain required. No production deployment or dynamic penetration test has been performed.
