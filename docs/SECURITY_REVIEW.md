# VITALIS — Security Review

Review date: 2026-10-14  
Security branch: `feature/security-hardening-final`  
Target branch: `develop`

## Executive status

The security hardening implementation is complete on the dedicated final security branch. It has not been merged into `develop`.

GitHub Actions is enabled and the complete VITALIS CI pipeline has passed on the hardened code: backend tests and Composer audit, frontend tests/build and npm audit, Gitleaks, Semgrep SAST, Trivy filesystem scanning, and production-container scanning all completed successfully.

The branch remains unmerged intentionally. Merge/deployment still requires project-owner approval and the production-only configuration listed below.

## High-risk findings addressed

### Broken access control

Sensitive modules that previously depended only on authentication now have explicit RBAC permissions:

- invoices and payments;
- hospitalizations and rooms;
- examination requests and clinical results;
- AI endpoints;
- audit logs.

Clinical results use permissions separate from examination coordination so administrative staff do not automatically receive detailed result access.

Duplicate user-management write routes outside the authenticated group were removed.

### IDOR / BOLA

Object-level authorization is enforced for patient-linked medical records. Doctors and patients are restricted to their permitted dossiers, including:

- patients;
- appointments;
- consultations;
- prescriptions and prescription lines;
- examination requests;
- clinical results;
- hospitalizations.

Ownership-changing updates are checked against the prospective object state to prevent reassignment attacks.

### Authentication and account lifecycle

Implemented controls include:

- login throttling;
- generic authentication errors;
- inactive-account login denial;
- middleware that blocks already-issued tokens after account deactivation;
- token revocation when an account is disabled, deleted, demoted or otherwise changes role;
- session inventory;
- single-session revocation;
- logout-all;
- password-reset token revocation;
- production MFA for sensitive roles;
- production public registration disabled by default;
- bearer tokens kept out of persistent localStorage.

### MFA

Production-sensitive roles default to:

- administrator;
- doctor.

The email OTP challenge has a short lifetime, attempt limit, keyed hash storage, IP/user-agent binding, and no bearer token is issued before successful verification.

Production refuses MFA delivery through development-only mail transports.

### Audit logging

Authenticated API activity records:

- actor;
- method;
- path;
- status;
- source IP;
- truncated user agent;
- duration;
- timestamp.

Request and response bodies are deliberately excluded to avoid copying medical data into the audit trail.

Audit rows are append-only at application level and HMAC-signed. The administration endpoint verifies the signature and exposes only an integrity-valid flag, not the HMAC itself.

### Financial integrity

Invoice/payment protections include:

- explicit permissions;
- invoice payment status derived by the server;
- transaction and row locks around payment changes;
- prevention of overpayment;
- prevention of invoice edits that reduce the amount below money already paid;
- prevention of cancellation/deletion when validated payments are attached.

### Hospitalization and room integrity

Admission and room management now use database transactions and row locks.

The legacy generic hospitalization creation endpoint is routed through the same capacity-safe admission logic, preventing it from being used as a bypass.

Room transfers cannot move an active patient into a full room. Room status is derived from real occupancy rather than arbitrary client input. Capacity cannot be reduced below current occupancy.

### Data minimization

The secretary receives minimized examination-request and hospitalization data. Detailed clinical result endpoints are unavailable to that role.

AI doctor recommendations expose only the fields required for the recommendation.

External conversational AI text has common direct identifiers masked before leaving the application.

### Secrets and demo data

Fixed seeded passwords were removed.

The main database seeder contains demonstration patients and accounts, so demo seeding is now disabled outside local/testing environments unless explicitly opted in.

Production secrets remain environment/secret-manager values and are not placed in source.

### Browser / API hardening

Security response headers include anti-sniffing, anti-framing, referrer restrictions, permissions policy, cache restrictions, CSP, and HSTS where applicable.

Frontend action visibility and route guards mirror the backend RBAC matrix. Backend authorization remains authoritative.

### CI / DevSecOps

The security branch adds or strengthens:

- Laravel test execution;
- frontend test execution;
- frontend production build;
- Composer audit;
- npm audit;
- Gitleaks;
- Trivy filesystem scan;
- Trivy production-container scans;
- Semgrep SAST;
- immutable SHA pinning for CI/CD actions;
- Dependabot monitoring for Composer, npm, GitHub Actions and Docker.

Production deployment is tied to the exact `develop` commit that successfully passed CI rather than rebuilding an unrelated newer commit.

## Required production configuration

Before deploying this branch:

1. apply all database migrations, including the audit-log migration;
2. configure a dedicated random `AUDIT_HMAC_KEY`;
3. configure a real production mail provider for MFA;
4. keep `ALLOW_PUBLIC_REGISTRATION=false`;
5. keep `ALLOW_DEMO_SEEDING=false`;
6. keep `APP_DEBUG=false`;
7. keep HTTPS enabled and configure production DB/Redis secrets outside Git;
8. ensure the frontend origin used by CORS/password-reset links is correct.

## External repository controls

These controls are repository settings rather than application code and must be configured by a repository administrator:

- GitHub Actions is enabled on the fork and validation is passing;
- protect `develop` on the upstream repository;
- require the CI checks before merge;
- require pull-request review;
- block direct pushes to `develop` where the team workflow permits.

## Residual design limitations

The current data model is single-clinic. There is no reliable tenant/clinic foreign key across all patient, medical and financial entities. Therefore multi-clinic isolation is intentionally not claimed.

If multi-clinic support is added, every tenant-owned entity must receive a tenant identifier and all queries/foreign-key changes must be tested for cross-tenant isolation.

The AI/ML functionality is an assistance feature and is not treated as a clinically validated diagnostic system.

## Merge gate

Automated code/security validation is satisfied:

- backend tests pass;
- frontend tests pass;
- frontend build passes;
- Composer and npm dependency audits pass;
- Semgrep passes;
- Gitleaks passes;
- Trivy filesystem and production-container scans pass;
- the final branch is synchronized with the latest upstream `develop`.

Before production deployment, the project owner must still provide the environment-specific MFA mail configuration and a protected `AUDIT_HMAC_KEY`, then apply the audit migration.

No merge into `develop` should occur without explicit project approval.

## Dependency remediation

The final security branch refreshes the vulnerable Composer lockfile entries identified by CI:

- `laravel/framework` -> `v13.36.0` (past the affected <13.30.0 range);
- `league/flysystem` -> `3.36.0` (past the affected <=3.35.2 range).

The one-off write-enabled refresh workflow was removed immediately after producing the reviewed lockfile so ongoing updates remain handled by the normal CI/Dependabot process.
