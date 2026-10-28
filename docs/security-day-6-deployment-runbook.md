# Security Day 6 Deployment Runbook

## Release Identity

- Branch: `security/settings-and-payload-hardening`
- Verified commit: `4563ea7a` (`Complete security role isolation tests`)
- Base used for the security track: `10a5f68f`
- Database migration: `2026_10_27_000001_create_sensitive_setting_audits_table`
- Generated assets: build on the deployment host; do not commit or deploy unrelated local `public/build` or `public/hot` files.

This runbook covers the Phase 3 backup, deployment, credential rotation, verification, and rollback sequence. Never paste credentials into Git, command history, tickets, logs, screenshots, prompts, or this document.

## Approval Gate

Do not begin the production change until all items below are confirmed by the release owner:

- [ ] Maintenance window and responsible operator are confirmed.
- [ ] Current production commit and deployment path are recorded.
- [ ] Database backup and configuration backup locations are confirmed.
- [ ] Backup restoration has been tested or the most recent restoration test is documented.
- [ ] Replacement credentials are available in the providers' secure consoles or password manager.
- [ ] An authorized person is available to verify email, AI, OAuth login, payment, storage, and ERP workflows.
- [ ] Rollback authority and stop conditions are agreed.

## Verified Pre-deployment Results

- Focused settings, audit, authorization, and payload suites: 30 tests, 151 assertions passed.
- Six-role MySQL tenant-isolation matrix: 1 test, 11 assertions passed.
- Production frontend command `npm run build` passed (`tsc && vite build`, 3 minutes 28 seconds).
- Vite reported only the existing chunk-size performance warning; there was no build error.
- Audit migration forward execution passed on isolated MySQL.
- Audit migration rollback and reapply passed on isolated MySQL.
- PHP syntax and `git diff --check` passed during Day 5 verification.

## 1. Capture Production State

Run these read-only checks from the production application directory and save the output in the private deployment record:

```bash
git branch --show-current
git rev-parse HEAD
git status --short
php artisan about --only=environment
php artisan migrate:status
```

Stop if the production worktree has unexplained changes, the current commit is unknown, or the environment/debug configuration is unsuitable for production.

## 2. Back Up Before Deployment

Create all backups using the hosting provider's approved process:

- Full database backup with timestamp and integrity/size verification.
- Production `.env` or equivalent secret/configuration backup in encrypted private storage.
- Current deployed source commit and current built assets reference.
- Private storage backup or snapshot if deployment tooling can alter storage links or permissions.

Record only backup identifiers and locations. Do not put backup contents or credentials in the repository.

Stop if any backup fails, is empty, cannot be verified, or cannot be restored by the approved process.

## 3. Prepare the Release

Use the normal deployment mechanism to deploy exactly the reviewed security branch/commit. Do not merge unrelated accounting, import, generated-asset, or local worktree changes into this release.

On the deployment host:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If the application uses queue workers, restart them through the hosting platform's normal process after the code and caches are ready.

Verify that `2026_10_27_000001_create_sensitive_setting_audits_table` is shown as `Ran` before continuing.

## 4. Pre-rotation Smoke Test

Before changing credentials, verify the deployed code with the existing credentials:

- [ ] Superadmin and company-admin login work.
- [ ] Staff permissions still restrict settings and company data correctly.
- [ ] Customer, vendor, and production-client portals open only their intended data.
- [ ] Browser/Inertia responses contain no stored passwords, API keys, tokens, or secrets.
- [ ] Sensitive settings pages show configured indicators rather than saved secret values.
- [ ] Leaving a secret input blank preserves the existing credential.
- [ ] Quotations, invoices, bills, customer payments, vendor payments, retainers, deals, and projects open normally.
- [ ] Customer/vendor selectors contain only approved minimal fields and active-company records.
- [ ] A cross-company or wrong-party ID is rejected.

Stop and roll back the code if a critical workflow fails before rotation.

## 5. Rotate Credentials One Integration at a Time

For each integration, create or obtain the replacement in its provider console, enter it only through the authorized ERP settings screen or secure environment management process, test it, and then revoke the old value.

Use this order so failures remain isolated:

1. SMTP password: send a test email and verify delivery.
2. AI API key: run a harmless test request and confirm no credential is returned to the browser.
3. OAuth client secrets: test each configured provider in a private browser session before revoking the old secret.
4. Storage credentials: test upload, authorized preview/download, and delete using a non-sensitive test file.
5. Payment gateway secrets: use provider test mode or an approved low-risk verification; do not perform an unapproved live charge.
6. Pusher and webhook secrets: verify connection/event delivery and signature validation where configured.
7. Shared ERP login password: distribute it only through the approved password manager, never chat or email.

After each replacement:

- [ ] Confirm the integration works.
- [ ] Confirm the settings UI remains masked.
- [ ] Confirm a sensitive-setting audit event exists without the credential value.
- [ ] Record the provider, operator, time, and result in the private deployment record.
- [ ] Revoke the old credential only after successful verification.

## 6. Session Invalidation

Invalidate existing sessions after shared-login or authentication-sensitive credential changes, using the application's approved session-revocation process. Warn active users before invalidation when operationally required.

Verify that old sessions are rejected and a fresh login succeeds for the required roles.

## 7. Final Verification

- [ ] Email delivery succeeds.
- [ ] AI request succeeds.
- [ ] Each configured OAuth login succeeds.
- [ ] Storage upload/download succeeds.
- [ ] Payment configuration check succeeds without an unauthorized live charge.
- [ ] Superadmin, company admin, staff, customer, vendor, and production-client access is correct.
- [ ] Browser responses contain no secret values.
- [ ] Cross-company record and selector manipulation is rejected.
- [ ] Quotations, invoices, bills, payments, retainers, deals, projects, and production-client login work.
- [ ] Application, web-server, queue, authentication, and provider logs contain no new critical errors.
- [ ] Old credentials are revoked after the replacements are proven.

Monitor authentication failures, provider errors, queue failures, and application exceptions closely during the agreed observation window.

## Rollback

Stop and roll back when there is secret exposure, cross-company access, widespread authentication failure, unrecoverable integration failure, migration failure, or a critical ERP workflow regression.

Preferred rollback order:

1. Put the application in maintenance mode if required by the incident.
2. Restore the previously deployed code/build using the normal deployment mechanism.
3. Clear and rebuild caches for the restored release.
4. Restore the database only when required and authorized. The new audit table is additive; do not destroy valid audit history casually.
5. Restore prior provider credentials only if they remain active and the security owner approves. Otherwise issue a new credential.
6. Re-run the critical smoke tests before reopening access.

The migration rollback command for the additive audit table is:

```bash
php artisan migrate:rollback --path=database/migrations/2026_10_27_000001_create_sensitive_setting_audits_table.php --force
```

Use it only when the release owner explicitly decides that the audit table must be removed. A code rollback normally does not require dropping this table.

## Completion Record

Phase 3 is complete only when backups are verified, the reviewed commit is deployed, integrations and role workflows pass, new credentials work, old credentials are revoked, monitoring shows no critical regression, and the Word progress tracker plus `AGENTS.md` handoff are updated.
