# VITALIS — Security Architecture and Operations

## Scope

This document describes the security controls implemented on the VITALIS medical-management application. It covers the Laravel API, Vue frontend, authentication, authorization, medical-record isolation, auditability, AI integrations, CI security checks, and production requirements.

The current application model is **single-clinic**. The repository does not yet contain a clinic/tenant identifier on the business entities. Multi-clinic isolation must therefore be implemented and re-tested if the backend later introduces multi-clinic data.

## Security principles

VITALIS follows these rules:

- deny by default when a role or ownership relation is unknown;
- enforce authorization on the backend, never only in the UI;
- apply least privilege by role;
- isolate patient/doctor records at object level to prevent IDOR/BOLA;
- do not persist bearer tokens in localStorage;
- revoke access immediately when an account is disabled or privileges change;
- minimize medical data returned to administrative roles;
- never store request or response bodies in the audit trail;
- never commit production secrets or fixed demo credentials;
- block production deployment when high-risk dependency/security checks fail.

## Authentication controls

Public authentication endpoints are rate-limited. Password creation and reset enforce a minimum length with letters and numbers and a maximum input size.

Sanctum bearer tokens have a bounded lifetime. The frontend stores bearer tokens in sessionStorage only. Legacy localStorage tokens are purged.

Sensitive roles are configured for email-based MFA in production. By default:

- administrateur — MFA required;
- medecin — MFA required.

The MFA challenge:

- expires after a short configured lifetime;
- has a maximum number of verification attempts;
- is bound to the originating IP and user agent;
- stores only a keyed hash of the OTP;
- does not issue a bearer token before successful verification;
- refuses insecure production mail transports such as the log/array mailers.

Production must configure a real SMTP or supported mail provider before enabling sensitive-role access.

Public self-registration is disabled by default in production. Accounts are expected to be provisioned by an administrator.

## Session security

Authenticated users can list their own API sessions, revoke a selected session, or revoke all sessions.

A disabled account is blocked even if an old valid token already exists. Existing tokens are deleted when a disabled account attempts access.

Privilege changes revoke all tokens for the affected account so that devices cannot continue with stale authorization context.

Password reset revokes existing API tokens.

## Authorization model

Permissions are declared centrally in:

- `backend/app/Enums/Permission.php`
- `backend/app/Enums/Role.php`

Routes use Laravel `can:` middleware. Object-level authorization is additionally enforced through `PerimetreDossier` and resource-aware Gates.

Protected domains include:

- patients;
- consultations;
- prescriptions;
- appointments;
- exam requests;
- clinical results;
- hospitalizations;
- rooms;
- invoices;
- payments;
- users and roles;
- dashboards/statistics;
- AI endpoints;
- audit logs.

Clinical result permissions are separate from exam-request permissions. This prevents administrative staff who coordinate exams from automatically receiving access to detailed clinical results.

## Record-level isolation

Patients are restricted to their own records.

Doctors are restricted to records linked to their own doctor identity where the business rule requires ownership.

For updates that can change ownership, authorization is checked before and after applying the proposed foreign keys. This prevents an attacker from reassigning a resource into or out of their permitted scope.

Restricted users receive a consistent denial for inaccessible or unknown records where appropriate, reducing identifier enumeration.

## Data minimization

Administrative access does not automatically imply access to every clinical field.

The secretary role receives minimized hospitalization and exam-request responses. Detailed clinical results are not available to that role.

Exam-request endpoints do not embed the related clinical result object; result access is handled by its own protected endpoint.

AI doctor recommendations do not expose doctor email, telephone, or unrelated financial fields.

## AI privacy controls

The external conversational AI integration removes common direct identifiers before sending user text to the external provider, including:

- email addresses;
- common Moroccan telephone formats;
- common identifier/CIN-like patterns.

External AI configuration is read through Laravel configuration rather than direct runtime `env()` access in controllers.

AI endpoints have an explicit `ai.use` permission and remain behind authentication and global API throttling.

The AI assistant must not be presented as a validated diagnostic system. Medical decisions remain with qualified professionals.

## Audit trail

Authenticated API activity is recorded without request or response bodies.

Audit entries include:

- actor user ID;
- HTTP method;
- API path;
- response status;
- source IP;
- truncated user agent;
- request duration;
- timestamp.

Denied authorization attempts and server-side failures are also captured when they pass authenticated middleware.

Every audit row is signed with an HMAC. The API verifies the signature and exposes an `integrity_valid` flag to administrators while keeping the signature itself hidden. Application-level updates and deletes of audit rows are rejected.

Audit-log reading is restricted to administrators through `audit-logs.read`.

The actor ID is intentionally retained independently of the users-table foreign key, so deleting a user does not rewrite historical evidence. Production should configure a dedicated `AUDIT_HMAC_KEY`.

Retention is explicit and conservative: `AUDIT_RETENTION_DAYS=0` keeps logs indefinitely. Once the clinic approves a retention period, a positive value enables the scheduled `audit:prune` job, which is the only controlled deletion path for expired audit rows.

## Financial integrity

Invoice and payment routes have explicit permissions.

Payment creation, modification, and deletion use database transactions and row locks around the affected invoice. This reduces race conditions that could otherwise allow concurrent payments to exceed the remaining balance.

Invoice/payment destructive operations remain limited to administrators.

## Hospitalization integrity

Room admission uses a transaction and row-level lock on the room before checking capacity. This prevents simultaneous admissions from both seeing the same free place and overbooking the room.

The legacy generic creation endpoint is routed through the same admission logic. Active room transfers are checked against destination capacity, room status is derived from current occupancy, and room capacity cannot be reduced below active occupancy.

## Browser and API hardening

API responses include defensive headers such as:

- `X-Content-Type-Options: nosniff`;
- `X-Frame-Options: DENY`;
- `Referrer-Policy: no-referrer`;
- restrictive `Permissions-Policy`;
- restrictive API CSP;
- `Cache-Control: no-store` for API responses;
- HSTS on secure requests.

The production frontend Nginx configuration adds browser security headers and a CSP suitable for the Vue application.

CORS is restricted to the configured frontend origin.

## Secrets and seed data

Known fixed seed passwords were removed. Development seed passwords are supplied through environment variables or generated randomly.

Production secrets must be stored in the deployment platform/secret manager and must never be committed.

Demo seeding is disabled outside local/testing environments by default. Production must keep `ALLOW_DEMO_SEEDING=false` so demonstration patients, doctors and accounts cannot be inserted accidentally.

Sanctum tokens use a recognizable prefix to improve secret-scanning detection.

## CI / DevSecOps controls

The VITALIS CI workflow is configured to run:

1. backend dependency installation;
2. Laravel tests;
3. Composer security audit;
4. frontend dependency installation;
5. frontend security/RBAC tests;
6. frontend production build;
7. npm security audit;
8. Gitleaks secret scanning;
9. Trivy source-tree vulnerability, secret, and configuration scanning;
10. Semgrep SAST using a pinned scanner version;
11. production backend/frontend container builds;
12. Trivy HIGH/CRITICAL scans of both production container images;
13. Dependabot monitoring for Composer, npm, GitHub Actions and Docker.

A failing test or configured high/critical security finding blocks the CI job.

**Important:** GitHub Actions must be enabled for the repository/fork. A workflow file in the repository does not prove that the checks have executed.

## Threat model summary

Primary assets:

- patient identity and medical records;
- prescriptions and examination results;
- credentials and active sessions;
- billing/payment data;
- audit records;
- infrastructure secrets;
- AI-provider credentials.

Primary threats and controls:

| Threat | Main controls |
| --- | --- |
| Credential brute force | login rate limiting, generic errors, MFA |
| Stolen bearer token | expiry, session-only browser storage, revocation, inactive-account middleware |
| IDOR/BOLA | object-level Gates, ownership filtering, before/after authorization |
| Privilege escalation | central RBAC, separate role-management endpoint, role changes revoke sessions |
| Excessive data exposure | role-specific minimization, separate result permissions |
| Secret leakage | removed fixed credentials, Gitleaks/Trivy, env/secret-manager policy |
| API scraping/DoS | global authenticated API throttling |
| Stored stale privilege | token revocation on role/status/password changes |
| Financial race condition | transactions and row locks |
| Room overbooking race | transactions and row locks |
| External AI privacy leak | identifier minimization and permission control |
| Clickjacking/content sniffing | response security headers |
| Untraceable misuse | authenticated audit trail |

## Security verification checklist

Before merge to the team repository:

- all backend tests pass;
- all frontend tests pass;
- frontend build succeeds;
- Composer audit passes;
- npm audit has no blocking high/critical finding;
- Gitleaks passes;
- Trivy passes or findings are reviewed and documented;
- no production secret exists in Git history;
- MFA email delivery is tested using the real production mail provider;
- `APP_DEBUG=false` in production;
- production HTTPS is active;
- production database/Redis credentials are not development defaults;
- backup and restore procedure is tested;
- a dedicated `AUDIT_HMAC_KEY` is configured and protected;
- audit-log integrity checks return valid signatures;
- audit-log retention is agreed with the project owner;
- the final backend is re-audited after teammates merge their changes.

## Penetration-style regression scenarios

The final review should explicitly test:

- patient changes an ID to request another patient's consultation, prescription, result, hospitalization, invoice, or appointment;
- doctor requests another doctor's restricted record;
- secretary attempts to open clinical result endpoints;
- a user sends a role field through the normal user-update endpoint;
- a demoted user reuses an old token;
- an inactive user reuses an old token;
- expired/revoked tokens are rejected;
- repeated login and MFA attempts are rate-limited/blocked;
- a payment race cannot exceed the invoice balance;
- simultaneous admissions cannot exceed room capacity;
- malicious redirect values cannot escape the VITALIS origin;
- API errors do not expose stack traces, SQL, secrets, or medical payloads.

## Multi-clinic note

The current schema does not contain a reliable `clinic_id` / tenant boundary across the medical and financial entities. It would be misleading to claim tenant isolation before that data model exists.

If multi-clinic support is added, the security implementation must add a tenant identifier to every tenant-owned entity, derive tenant context from the authenticated user, apply tenant scopes at query level, reject cross-tenant foreign keys, and add automated cross-tenant IDOR tests before deployment.

## Incident response

For a suspected compromised account:

1. mark the user inactive;
2. revoke all sessions/tokens;
3. inspect audit records for abnormal paths/statuses/IPs;
4. reset credentials and MFA delivery channel if necessary;
5. rotate any exposed application or provider secrets;
6. review affected patient/financial records;
7. document the incident and remediation;
8. re-enable the account only after validation.

For a leaked production secret, rotate the secret immediately. Removing it from the current source tree is not sufficient if it existed in Git history.
