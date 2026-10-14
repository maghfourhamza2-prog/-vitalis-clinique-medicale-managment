# VITALIS — OWASP ASVS-oriented Security Checklist

This checklist maps the VITALIS hardening work to the major OWASP ASVS control areas. It is intentionally evidence-oriented rather than claiming formal certification. Automated tests/scanners must still pass before merge.

| ASVS area | VITALIS control | Evidence in repository | Status |
| --- | --- | --- | --- |
| Architecture | Central security model and documented trust boundaries | `docs/SECURITY.md`, `AppServiceProvider`, `Role.php`, `Permission.php` | Implemented |
| Authentication | Rate limiting, generic errors, inactive-account checks | `AuthController`, `AppServiceProvider`, auth security tests | Implemented |
| Multi-factor authentication | Email OTP for sensitive production roles with expiry/attempt/context binding | `config/security.php`, `AuthController::verifyMfa` | Implemented; production mail configuration required |
| Session management | Expiring Sanctum tokens, list/revoke/logout-all, revocation after password/role/status changes | `AuthController`, `EnsureAccountIsActive` | Implemented |
| Access control | Central RBAC plus object-level dossier ownership checks | `Role.php`, `Permission.php`, `PerimetreDossier.php`, controllers | Implemented |
| IDOR/BOLA resistance | Patient/doctor query scoping and object-aware Gates | patient, appointment, consultation, prescription, exam/result, hospitalization controllers/tests | Implemented |
| Input validation | Explicit validation, bounded strings/arrays, enum constraints | controllers and request validation rules | Implemented |
| Stored data protection | Password hashing, hidden password/token fields, no fixed production credentials | `User.php`, auth tests, seeder hardening | Implemented |
| Error handling | Generic auth/API service errors; no stack traces returned by hardened endpoints | auth/AI controllers, production config requirements | Implemented |
| Logging | Authenticated API audit trail without medical request/response bodies | `AuditApiActivity`, `AuditLog` | Implemented |
| Audit integrity | HMAC-signed, application-immutable audit rows | `AuditLog`, audit migration/tests | Implemented |
| Audit retention | Explicit configurable retention, disabled until clinic policy is approved | `audit:prune`, `AUDIT_RETENTION_DAYS` | Implemented framework; policy value required |
| Data protection / privacy | Secretary data minimization; AI identifier masking | exam/hospital/AI controllers | Implemented |
| Communications | HTTPS/HSTS production expectation; secure session-cookie example | security headers, production env example | Implemented/configuration required |
| HTTP security | CSP, anti-framing, nosniff, referrer and permissions policies | `SecurityHeaders`, frontend Nginx config | Implemented |
| API security | Authentication, authorization, throttling, bounded payloads | API routes/middleware/controllers | Implemented |
| Business logic | Transactional payment and hospitalization/room integrity | billing/payment/hospitalization/room controllers | Implemented |
| File/resource access | Prescription/result resources require authenticated permissions and dossier checks | routes/controllers | Implemented |
| Secrets | No fixed demo passwords, Gitleaks/Trivy, environment/secret-manager policy | seeder, CI, env examples | Implemented |
| Dependency security | Composer/npm audit and Dependabot | CI + `.github/dependabot.yml` | Implemented; CI execution pending |
| SAST | Semgrep security analysis | `.github/workflows/ci.yml` | Implemented; CI execution pending |
| Container security | Trivy filesystem and production image scans | `.github/workflows/ci.yml` | Implemented; CI execution pending |
| Deployment integrity | Pinned CI/CD actions; deploy exact SHA that passed CI | CI/deploy workflows | Implemented; CI execution pending |

## Merge acceptance criteria

The security branch is acceptable for merge only after:

1. Laravel tests pass.
2. Frontend tests pass.
3. Frontend production build passes.
4. Composer and npm audits pass or findings are reviewed.
5. Semgrep passes or findings are reviewed.
6. Gitleaks passes or historical findings are reviewed.
7. Trivy filesystem and container scans pass or findings are reviewed.
8. Production MFA mail delivery is tested.
9. `AUDIT_HMAC_KEY` is configured.
10. The clinic selects `AUDIT_RETENTION_DAYS` or explicitly accepts indefinite retention.
11. The branch is rechecked against the latest upstream `develop`.

## Out of scope / not claimed

- Formal OWASP ASVS certification.
- Multi-clinic/tenant isolation: the current schema has no reliable clinic/tenant key across all records.
- Clinical validation of the AI/ML model.
- Legal certification or a jurisdiction-specific medical-record retention opinion.
