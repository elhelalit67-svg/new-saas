# AlQuma Education — القمة التعليمية

A PHP-based starting point for building a multi-tenant SaaS platform for educational centers.

> **Current status:** foundation/audit stage. This repository is not yet a production-ready student, attendance, finance, or learning management system. Do not submit real student or financial data.

## Current implementation
- A minimal Arabic RTL informational page with conservative response security headers.
- Initial project audit, architecture, security, implementation, test, deployment, and known-issues documents under `docs/`.
- The publicly reachable `phpinfo()` diagnostic file has been removed from this development branch.

## Requirements
- PHP CLI (verify the version and extensions available in your target environment before deployment).

## Run locally
```sh
php -v
php -l app/index.php
php -t app -S 127.0.0.1:8080
```
Open `http://127.0.0.1:8080/`.

## Wasmer
The original starter README described Wasmer Edge and PHP's built-in development server. Actual runtime configuration, persistent database availability, filesystem persistence, queues, scheduled tasks, secrets, domain routing, and production health have not yet been verified. See [Deployment Runbook](docs/DEPLOYMENT.md) before any deployment.

## Project documentation
- [Project audit](docs/PROJECT_AUDIT.md)
- [Implementation plan](docs/IMPLEMENTATION_PLAN.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Security baseline](docs/SECURITY.md)
- [Test plan](docs/TEST_PLAN.md)
- [Deployment runbook](docs/DEPLOYMENT.md)
- [Known issues](docs/KNOWN_ISSUES.md)

## Safety note
Authentication, authorization, database persistence, tenant isolation, backups, and financial integrity are not implemented in this baseline. Production deployment requires those controls, automated tests, and explicit release approval.
