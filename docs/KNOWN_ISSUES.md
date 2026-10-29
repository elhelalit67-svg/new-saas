# Known Issues

| ID | Priority | Issue | Evidence / impact | Next action | Status |
|---|---|---|---|---|---|
| K-001 | P0 | Public diagnostic endpoint | `app/info.php` calls `phpinfo()`; may expose runtime/configuration information if publicly accessible. | Remove endpoint from application tree and verify it returns 404 after deployment. | IN PROGRESS |
| K-002 | P0 | No authentication or tenant isolation in inspected code | Repository contains only a sample PHP script and diagnostic file. It is not safe for real student or financial data. | Build auth/RBAC and centrally enforced tenant context before business records. | TODO |
| K-003 | P1 | No persistence/business modules | No database schema, migrations, or business models found. | Confirm host/database options and add migration strategy. | TODO |
| K-004 | P1 | No automated tests/CI found | No test files or workflows in the audited tree. | Add lint/test commands and CI after establishing app structure. | TODO |
| K-005 | P1 | Live deployment not verified | The available web inspection tool could not fetch the provided Wasmer URL. | Verify via hosting dashboard or a direct runtime request. | BLOCKED |
| K-006 | P1 | Runtime/deployment configuration unknown | No Wasmer config or dependency manifest in the audited tree. | Inspect hosting configuration and runtime before choosing framework/dependencies. | BLOCKED |

No issue above should be considered resolved without the stated verification evidence.
