# ERP Parallel Agent Coordination

This file is the shared source of truth for every AI agent and developer working in this repository. Read it before inspecting or changing code. Update it before ending any session that changes code, data scripts, migrations, configuration, tests, branches, or deployment state.

## Primary Rule

Do not assume another agent's work is absent just because it is not on the current branch. Review this file, `git status`, the current branch, and recent commits before starting. Never overwrite, revert, reformat, or delete another track's changes without explicit approval.

## Active Workstreams

| Track | Owner | Status | Target branch | Scope |
| --- | --- | --- | --- | --- |
| Data correction | Claude | Active | No code branch. SQL scripts live outside the repo in `E:\Doha Workplace\ERP Data Fixes\` (repo stays on `bill-line-expense-accounts` @ `10a5f68f`, already deployed) | Review ERP records against Zoho, correct approved discrepancies, preserve backups, and document every changed record. |
| Zoho import redesign | Codex (app foundation), Claude (mapping/reconciliation) | Active - Phase 2 foundation | `zoho-import-redesign` | Import Zoho-exported Excel files directly into Wazely ERP with mapping, validation, duplicate detection, error reporting, and reconciliation. |
| Security hardening | Codex | Active - Phase 3 pre-deployment passed; production window pending | `security/settings-and-payload-hardening` | Complete Phase 1 secret protection, Phase 2 payload/access hardening, and Phase 3 rotation, testing, and deployment. Total estimate: 6 working days. |

## Security Plan

### Phase 1: Critical Secret Protection - Days 1-3

- Inventory secrets and capture baseline exposure without printing secret values.
- Remove SMTP passwords, AI keys, OAuth secrets, payment secrets, storage credentials, webhook secrets, and similar values from browser/Inertia payloads.
- Replace broad shared settings with explicit context-specific allowlists.
- Keep full secrets server-side only.
- Mask settings forms; blank input keeps the existing secret, while replace and clear are explicit actions.
- Restrict sensitive settings and record changes in an audit trail.

### Phase 2: User Data and Access Hardening - Days 4-5

- Audit quotations, invoices, payments, retainers, deals, projects, and all party selectors.
- Replace full user/customer/vendor records with minimal option resources, normally `id` and `name` only.
- Include email or company name only when the workflow requires it.
- Enforce tenant ownership and correct party type on reads and submitted IDs.
- Test superadmin, company admin, staff, customer, vendor, and production-client access.

### Phase 3: Rotation and Deployment - Day 6

- Back up the production database and configuration.
- Rotate exposed SMTP, AI, OAuth, and shared login credentials.
- Invalidate sessions when required.
- Deploy, clear caches, test integrations and critical ERP workflows, monitor logs, then revoke old credentials.

The security scope covers both editions of the audit in `E:\Doha Workplace\ERP\Security Updates`. Product and UX recommendations are outside this six-day security estimate.

## Workstream Boundaries

### Claude: Data and Import

- Own data-audit worksheets, correction scripts, Zoho column mapping, import parsing, validation, duplicate handling, dry runs, reconciliation, and import reports.
- Never modify production data without an approved correction list, a backup, and a verified dry run.
- Prefer repeatable, idempotent correction/import scripts over manual database edits.
- Never include real credentials or sensitive customer data in this file, commits, logs, screenshots, or prompts.

### Codex: Security

- Own shared settings exposure, secret storage/display behavior, minimal browser payloads, authorization, tenant isolation, security tests, credential-rotation checklist, and security deployment verification.
- Do not change accounting values, Zoho mappings, or correction decisions unless the user explicitly moves that work into the security track.

### Shared Files

Potential overlap includes controllers that build customer/vendor options, import controllers, `HandleInertiaRequests.php`, settings services, user models/resources, tests, and `CHANGELOG.md`. Before editing a shared file, claim it in the Current Claims table. If another active claim exists, coordinate first or work in a different file.

## Current Claims

| Owner | Branch | Files or area claimed | Started | Status |
| --- | --- | --- | --- | --- |
| Claude | `release/2026-11-16` | Petty cash posting (step 1 of 2, Sithari approved 2026-11-18): `packages/workdo/PettyCashManagement` (category expense account + migration, request/reimbursement approval and delete, fund selection, category UI), `JournalService` petty cash journals, `BankTransactionsService::approvePettyCash`, Account `EventServiceProvider` petty cash listeners, smoke test. Step 2 (bring Zoho petty cash history into the section, journals untouched) follows as a data change. | 2026-11-18 | Released - both steps live; import applied on live 2026-11-18 (balance 1,176.75 = ledger) |
| Claude | `security/helpdesk-notifications-phase3-2026-11-16` | Settings scope Phase 3 (Helpdesk email notifications): `app/Services/HelpdeskEmailService.php`, new `HelpdeskNotificationSettings` service and email content renderer, `app/Jobs/SendHelpdeskEmail.php`, Helpdesk notification endpoint/props in `SettingController.php`, `routes/web.php`, new `helpdesk-notification-settings.tsx`, superadmin settings menu/registry, Helpdesk/settings tests, `CHANGELOG.md`, handoff. No migrations or data changes. | 2026-11-15 | Released - committed and pushed without a PR (see handoff). |
| Codex | `security/settings-scope-phase2-2026-11-16` | Settings scope Phase 2: central AI settings/readers, company AI toggles, platform/company Brand and System separation, settings menus/payloads, optional company SMTP fallback and mail metadata, permission migration/seeding, focused tests, deployment runbook, changelog and handoff. | 2026-11-16 | Released - taken over and completed by Claude on 2026-11-15 after Codex hit its usage limit; committed and pushed without a PR (see handoff). |
| Codex | `security/settings-scope-2026-11-15` | Phase 1 follow-up: `SettingController.php`, settings payload compatibility/security tests, `docs/security-settings-scope-deployment.md`, `CHANGELOG.md` and handoff. Preserve the released Phase 1 controls and exclude generated/runtime files. | 2026-11-15 | Released - verified; push recorded below |
| Codex | `security/settings-scope-2026-11-15` | Settings scope and security Phase 1: `HandleInertiaRequests.php`, `SettingController.php`, `Helper.php` (`setSetting` only), `AIAssistantSettingsController.php`, related security tests, deploy data-step documentation, changelog and handoff. | 2026-11-15 | Released - pushed without PR |
| Codex | `fix/email-notification-toggles-2026-11-15` | Superadmin email notification toggles: persist/reload private notification settings correctly, enforce authorized keys, make Helpdesk dispatch respect switches, focused tests/build, changelog and handoff. | 2026-11-15 | Released - pushed without PR |
| Codex | `UI-upgrade-2026-11-14` | Ticket Records summary correction: retain the five KPI cards while removing only the three breakdown panels; frontend build and local verification. | 2026-11-15 | Released - pushed without PR |
| Codex | `UI-upgrade-2026-11-14` | Helpdesk Ticket Records UI simplification: rename the client submenu to Tickets, remove records summary panels, add the permission-aware create-ticket action, frontend/local verification, changelog and handoff. | 2026-11-15 | Released - pushed without PR |
| Codex | `UI-upgrade-2026-11-14` | Client requester inline ticket status: expose the existing header dropdown to the ticket's client requester, preserve tenant isolation, focused tests, changelog and handoff. | 2026-11-14 | Released - pushed without PR |
| Codex | `UI-upgrade-2026-11-14` | Helpdesk ticket header status layout: move the existing authorized status dropdown directly below Requester; frontend verification, changelog and handoff. | 2026-11-14 | Released - pushed without PR |
| Codex | `UI-upgrade-2026-11-14` | Helpdesk client navigation and Ticket Records permission: grouped Helpdesk submenu, enforce existing `view-helpdesk-records`, default role grants, focused tests, changelog and handoff. | 2026-11-14 | Released - pushed without PR |
| Codex | `UI-upgrade-2026-11-14` | Inline Helpdesk ticket status control: support-only status endpoint, ticket detail selector, shared lifecycle/history behavior, focused smoke coverage, changelog and handoff. Preserve all completed Helpdesk Steps 1-7. | 2026-11-14 | Released - pushed without PR |
| Codex | `UI-upgrade-2026-11-09` | Helpdesk Step 5 pop-up notifications: database notifications for ticket creation, assignment, replies and resolution; recipient-safe polling/read-dismiss endpoints; persistent global top-right stack; focused tests, changelog and handoff. Claude's Steps 1/3 and completed Steps 2/4 are preserved. | 2026-11-10 | Taken over by Claude on 2026-11-10 after Codex hit its usage limit; Steps 5-7 complete locally, not committed |
| Claude | `UI-upgrade-2026-11-09` | Helpdesk security fixes and private reply attachments: HelpdeskTicketController, HelpdeskReplyController, reply request, Helpdesk models, reply form and message UI, new attachment table, routes and tests. Agents, requester and pop-ups are not included. | 2026-11-09 | Complete - locally verified; not committed or pushed |
| Codex | `feature/zoho-ui-upgrade-2026-11-04` | Salesperson workspace and Create Quotation UX: clickable directory rows, tenant-scoped overview/quotations/payments data, quotation/invoice drill-through, two-row details layout, and editable template-prefilled Notes/Terms persisted on quotations and PDFs. Focused tests, migration, changelog and handoff included. Existing statement and salesperson work is preserved; generated `public/build` and `public/hot` remain excluded. | 2026-11-05 | Complete - locally migrated and verified; not committed or pushed |
| Codex | `feature/zoho-ui-upgrade-2026-11-04` | Salesperson management submenu/page and required quotation salesperson selection: Quotation routes/controller/menu/forms/validation, focused tests, changelog and handoff. Existing statement-template changes are preserved; generated `public/build` and `public/hot` remain excluded. | 2026-11-05 | Complete - locally verified; not committed or pushed |
| Codex | `feature/zoho-ui-upgrade-2026-11-04` | Customer Statement document-template type and branded Customer 360 statement PDF: template catalogue/form/preview, dedicated filtered PDF route/controller/page, focused regression coverage, changelog and handoff. Generated `public/build` and `public/hot` remain excluded. | 2026-11-05 | Complete - locally verified; not committed or pushed |
| Codex | `feature/zoho-ui-upgrade-2026-11-04` | Customer 360 statement PDF download: make Statement-tab and overflow actions trigger the existing PDF download flow, preserve date filters, add focused regression coverage, changelog and handoff. Generated `public/build` and `public/hot` remain excluded. | 2026-11-05 | Complete - locally verified; not committed or pushed |
| Codex | `feature/zoho-ui-upgrade-2026-11-04` | Guarded Sales/Purchase Invoice Revert to Draft workflow: permissions, routes/controllers, lifecycle and journal reversal services, inventory reversal, dependency checks, status history migration/model/UI, focused tests, changelog and handoff. Generated `public/build` and `public/hot` remain excluded. | 2026-11-04 | Complete - committed locally; not pushed |
| Codex | `feature/zoho-ui-upgrade-2026-11-04` | Package the verified local Customer 360 plus document UX integration: reliable default-off multi-currency switch, quotation salesperson selector/manager, single-scroll item/product dialogs, stable editable item popover, catalog-optional manual lines, and shared tax management for Sales Invoice, Purchase Invoice and Quotation. Exclude generated `public/build`, `public/hot`, and unrelated workspace files; publish the branch without a PR. | 2026-11-04 | Released - pushed; no PR |
| Codex | `release/zoho-and-security` | Zoho-style searchable line-item selector for Sales Invoice, Purchase Invoice and Quotation create/edit; bottom Add New Row/Add Multiple Items controls; quick-create footer flow, tests/build, changelog and handoff. | 2026-11-03 | Complete - locally verified; ready to commit and push |
| Codex | `release/zoho-and-security` | Ensure the shared Multi-Currency Transaction pill defaults off by resolving the correct company base currency across document contexts; focused regression test/build, changelog and handoff. | 2026-11-03 | Complete - locally verified; ready to commit and push |
| Codex | `release/zoho-and-security` | Split Product & Service Items into permission-controlled Products and Services submenus, type-locked list/create/edit flows, permission migration/seeder, tests, changelog and handoff. | 2026-11-03 | Complete - locally verified; ready to commit and push |
| Codex | `release/zoho-and-security` | Replace the shared Multi-Currency Transaction checkbox with an accessible pill toggle across document forms; focused test/build, changelog and handoff. | 2026-11-03 | Complete - locally verified; ready to commit and push |
| Codex | `release/zoho-and-security` | Move Sales Invoice, Purchase Invoice and Quotation multi-currency controls into their upper details cards on create/edit; preserve currency behavior, focused tests/build, changelog and handoff. | 2026-11-03 | Complete - locally verified; ready to commit and push |
| Codex | `release/zoho-and-security` | Normalize legacy base-currency edit state and standardize separate white multi-currency cards across Sales Invoice, Purchase Invoice and Quotation create/edit; shared picker behavior, tests, changelog and handoff. | 2026-11-03 | Complete - locally verified; ready to commit and push |
| Codex | `feature/customer-360-workspace-2026-11-03` | Customer directory currency filter: replace free-text entry with the system currency catalogue, preserve advanced filter state, and run focused test/build verification. | 2026-11-03 | Complete - searchable system currency dropdown; focused tests, TypeScript, and production build passed |
| Codex | `feature/customer-360-workspace-2026-11-03` | Customer directory effective-currency filtering: include legacy customers without an explicit preferred currency when the selected currency is the company base currency; focused regression tests and build verification. | 2026-11-03 | Complete - inherited base-currency matching added; PHP syntax and 6 focused tests passed |
| Codex | `feature/customer-360-workspace-2026-11-03` | Customer 360 header overflow: truncate long company names without displacing desktop action controls; preserve responsive wrapping and add focused regression coverage. | 2026-11-03 | Complete - responsive truncation and stable desktop actions; 6 focused tests, TypeScript, and production build passed |
| Codex | `feature/customer-360-ui-upgrade-2026-11-03` | Package the complete Customer 360 workspace and customer directory UI on top of the latest colleague release branch; exclude unrelated font and application-wide calendar changes, verify, commit, push, and provide live deployment commands. | 2026-11-03 | Complete - based on `e8db2fb5`; customer-only commit verified and ready to push |
| Codex | `feature/customer-360-ui-upgrade-2026-11-03` | Application-wide typography release: Instrument Sans interface text, Archivo headings, JetBrains Mono technical text, dependency lockfile, production build verification, and same-branch deployment handoff. | 2026-11-03 | Complete - font assets bundled successfully; production build passed |
| Codex | `feature/customer-360-ui-upgrade-2026-11-03` | Customer create/edit usability redesign: guided create steps, section-based edit navigation, progressive optional details, payment-term presets, portal guidance, responsive modal, review summary, focused tests and build verification. | 2026-11-03 | Complete - shared responsive form; 7 focused tests, TypeScript, and production build passed |
| Codex | `feature/customer-360-workspace-2026-11-03` | Application-wide calendar UI consistency: shared DatePicker behavior and remaining native date controls across Customer 360, Account, HR attendance, and Zoho migration setup; regression test/build/browser verification. | 2026-11-03 | Complete - native date audit clean; 8 focused tests, TypeScript and production build passed |
| Codex | `feature/customer-360-workspace-2026-11-03` | Customer 360 follow-up: remove remaining hardcoded USD currency fallbacks and make workspace directory search live with clear-to-reset behavior; focused tests/build/browser verification. | 2026-11-03 | Complete - local QAR data verified; focused tests, TypeScript and production build passed |
| Codex | `feature/customer-360-workspace-2026-11-03` | Customer 360 directory pagination and company-selected currency propagation across directory/workspace amounts; focused tests/build and handoff. | 2026-11-03 | Complete - browser verified; focused tests, TypeScript and production build passed |
| Codex | `release/zoho-and-security` | Correct Add Products search-icon containment and add balanced vertical spacing to Sales Invoice multi-currency controls; focused tests/build, changelog and handoff. | 2026-11-02 | Complete - locally verified; ready to commit and push |
| Codex | `release/zoho-and-security` | Remove category filtering from the shared Add Products browser everywhere it is used; preserve product-creation category assignment, focused test/build, changelog and handoff. | 2026-11-02 | Complete - locally verified; ready to commit and push |
| Codex | `release/zoho-and-security` | Transaction-currency line entry and dynamic currency-symbol Unit Price headings across Sales Invoice, Purchase Invoice and Quotation create/edit; backward-compatible server handling, tests, changelog and handoff. | 2026-11-02 | Complete - locally verified; ready to commit and push |
| Codex | `release/zoho-and-security` | Normalize the Unit Price heading in Sales Invoice, Purchase Invoice, and Quotation create/edit forms; focused frontend verification, changelog and handoff. | 2026-11-02 | Complete - locally verified; ready to commit and push |
| Codex | `release/zoho-and-security` | Purchase Invoice multi-currency layout: contain collapsed/expanded currency controls in a white card on create/edit, focused regression coverage, changelog and handoff. | 2026-11-02 | Complete - locally verified; ready to commit and push |
| Codex | `release/zoho-and-security` | Purchase Invoice product-picker parity: shared product browser/create flow in create/edit, purchase-price and replenishment behavior, controller catalogue payload, focused tests, changelog and handoff. | 2026-11-02 | Complete - locally verified; ready to commit and push |
| Codex | `release/zoho-and-security` | Vendor Payment creation follow-up: preserve a valid base-currency exchange rate when selecting a vendor, verify full and partial bill allocations, focused regression test, changelog and handoff. | 2026-11-02 | Complete - locally verified; ready to commit and push |
| Codex | `release/zoho-and-security` | Live security smoke-test follow-up: customer/vendor dashboard scoping, quotation-conversion feedback, credit-note/purchase-invoice refresh behavior, purchase-invoice currency defaults, vendor-payment submission/partial allocations, focused tests and changelog. Generated `public/build`, `public/hot`, and unrelated workspace changes excluded. | 2026-11-02 | Complete - locally verified; ready to commit and push |
| Codex | `release/zoho-and-security` | Claude follow-up audit fixes for migration template colours and alternative/conditional column guidance; focused tests, changelog and handoff only. | 2026-11-01 | Complete - ready to commit and push |
| Codex | `release/zoho-and-security` | Audit every migration-template column against actual import mappings; classify required/supported/ignored fields, color XLSX headers safely, repair manual-journal template completeness, update UI/tests/changelog. | 2026-11-01 | Complete - ready to commit and push |
| Codex | `release/zoho-and-security` | Generic ERP migration templates for every file-based journey step: visible accepted columns, XLSX/CSV downloads, upload-guide wording, focused tests and changelog. | 2026-11-01 | Released - pushed `4d12b100` |
| Codex | current workspace | Knowledge-base redesign refresh: sanitized ERP screenshots, updated menu paths, guided Bulk Import documentation, and local link/privacy verification under `knowledge-base/`. | 2026-11-01 | Complete - local files updated and verified; not committed |
| Codex | `release/zoho-and-security` | Fix Payments Made index 500 caused by selecting nonexistent `vendors.primary_email`; controller relation, focused regression test, changelog, and verification. | 2026-11-01 | Released - pushed `b2f53743` |
| Codex | `release/zoho-and-security` | Backward-compatible product upload repair for old generated `General`/`Pcs` placeholders when the company has one category/unit; regression tests, changelog and push. | 2026-10-31 | Released - committed in this handoff changeset |
| Codex | `release/zoho-and-security` | Product/service spreadsheet template tenant-aware example category, unit and tax; focused upload regression test, changelog and push. | 2026-10-31 | Released - committed in this handoff changeset |
| Codex | `release/zoho-and-security` | Restore Categories and Units to manual-entry-only by removing the spreadsheet import option and its supporting changes. | 2026-10-31 | Released - committed in this handoff changeset |
| Codex | `release/zoho-and-security` | Remove default-warehouse fields and setup-time creation from migration workspace configuration; preserve Step 6 warehouse workflow, controller/UI/changelog verification, push. | 2026-10-31 | Released - committed in this handoff changeset |
| Codex | `release/zoho-and-security` | Make migration default warehouse optional as a complete section while preserving conditional warehouse validation/creation; controller, migration UI/types, changelog, focused verification, push. | 2026-10-31 | Released - committed in this handoff changeset |
| Codex | `release/zoho-and-security` | Replace the migration base-currency free-text input with the shared searchable currency dropdown in `resources/js/pages/zoho-migrations/index.tsx`; changelog, TypeScript verification, push. | 2026-10-31 | Released - committed in this handoff changeset |
| Codex | `release/zoho-and-security` | Remove the vendor name from the visible full-ledger accounting-mode option in `resources/js/pages/zoho-migrations/index.tsx`; changelog and focused TypeScript verification. | 2026-10-31 | Released - committed in this handoff changeset |
| Codex | `zoho-import-redesign` | Enforce base-to-transaction conversion for sales/purchase invoice create/update line amounts, focused regression verification, `CHANGELOG.md`. | 2026-10-30 | Released - committed `e65fd6d8` |
| Codex | `zoho-import-redesign` | Correct invoice-list base-currency fallback for legacy/missing snapshots: shared dual-currency renderer, sales/purchase total fallbacks, focused frontend verification, `CHANGELOG.md`. | 2026-10-30 | Released - committed `e3fc821f` |
| Codex | `zoho-import-redesign` | Base-first dual-currency display on sales and purchase invoice list/table/card amounts: shared amount renderer, invoice indexes, focused frontend verification, `CHANGELOG.md`. | 2026-10-30 | Released - committed `1d9ca63d` |
| Codex | `zoho-import-redesign` | Migration-configuration default warehouse location/contact fields, placeholder repair, validation/UI, focused verification, `CHANGELOG.md`. | 2026-10-30 | Released - committed `93ef6ad9` |
| Codex | `zoho-import-redesign` | Base-currency product/service price input with transaction-currency invoice conversion across sales/purchase create/edit, backend conversion contract, focused tests/build, `CHANGELOG.md`. | 2026-10-30 | Released - implementation complete; commit recorded below |
| Codex | `zoho-import-redesign` | QAR symbol formatting and base-equivalent visibility across sales/purchase create/edit invoice summaries, TypeScript verification, `CHANGELOG.md`. | 2026-10-30 | Released - implementation complete; commit recorded below |
| Codex | `zoho-import-redesign` | Sales and purchase create/edit transaction-currency display: item totals, summaries, base equivalent, formatter utility, TypeScript verification, `CHANGELOG.md`. | 2026-10-30 | Released - implementation complete; commit recorded below |
| Codex | `zoho-import-redesign` | Purchase invoice line vertical alignment in `resources/js/pages/Purchase/components/InvoiceItemsTable.tsx`, build verification, `CHANGELOG.md`. | 2026-10-30 | Released - implementation complete; commit recorded below |
| Codex | `zoho-import-redesign` | Searchable currency combobox in `resources/js/components/transaction-currency-fields.tsx`, focused build verification, `CHANGELOG.md`. | 2026-10-30 | Released - implementation complete; commit recorded below |
| Codex | `zoho-import-redesign` | Wazely required changes: multi-currency snapshots and realized gain/loss across sales/purchases/payments/credits, customer/vendor preferred currency, purchase item descriptions and document references, sales-order reference, invoice download permissions, visible Double Entry-to-Reports naming, focused migrations/tests, `CHANGELOG.md`. Existing generated `public/build`, `tmp`, `graphify-out`, cache, and unrelated `storage/installed` changes are excluded. | 2026-10-30 | Released - implementation complete; commit recorded below |
| Codex | `zoho-import-redesign` | Plan-assignment initialization reliability: `packages/workdo/Hrm/src/Models/HrmUtility.php`, focused HRM regression test, `CHANGELOG.md`. | 2026-10-29 | Released - committed `7a302951` |
| Codex | `zoho-import-redesign` | Clean-install compatibility for the Purchase Order invoice-link index migration and local `erp_local` initialization verification. | 2026-10-29 | Released - committed `c15c6aa4` |
| Claude | none (outside repo) | Data-correction scripts and worksheets in `E:\Doha Workplace\ERP Data Fixes\` (folders: `Botanico`, `Je Invoices - Phase 1`, `Invoice Balances`, `Invoices - Phase 2`, `INV-000142`, `Vendor Bills`, `Payment Accounts`, `Zoho Import`). No repo files are being edited for data correction. | 2026-10-25 | Active |
| Codex | `zoho-import-redesign` | Zoho migration workspace redesign: stage catalogue/progress, models/migrations/services/controllers/jobs/routes, navigation, `resources/js/pages/zoho-migrations/**`, removal of legacy page import buttons, focused tests, docs and `CHANGELOG.md`. Current work adds automatic inspection/validation, progress feedback, correction review, and guarded Chart of Accounts execution. Existing direct-import definitions remain unmodified. | 2026-10-27 | Active - Chart of Accounts execution and review UX |
| Claude | `zoho-import-redesign` | Mapping/reconciliation collaboration: sanitized Zoho samples, versioned column catalogue, expected-result fixtures, and reconciliation review. No application files claimed while Codex builds the foundation. | 2026-10-25 | Active - data contract support |
| Claude | `bill-line-expense-accounts` | Released - committed `10a5f68f`, pushed, deployed to live 2026-10-25 (bill line accounts, journal line contacts, line discount display, 3 migrations) | 2026-10-22 | Released |
| Codex | `security/settings-and-payload-hardening` | Phase 1 development work is committed through `58e5652f`. No Phase 1 files remain claimed. Live credential checks and backup remain Phase 3 deployment tasks; Phase 2 paths will be claimed before editing. | 2026-10-26 | Active - Phase 2 inventory next |
| Codex | `security/settings-and-payload-hardening` | Day 4 payload/access hardening: Account customer/vendor payment controllers and store requests; Retainer controllers and store/update/payment requests; purchase-invoice tenant validation; focused tenant/type/payload tests; `CHANGELOG.md`. | 2026-10-28 | Released - committed `3b4b6764` |
| Codex | `security/settings-and-payload-hardening` | Day 5 role/permission and tenant-isolation testing: security setting routes/controllers, party/document selectors, production-client boundaries, focused automated tests, and narrowly required fixes. | 2026-10-28 | Released - committed `4563ea7a` |
| Codex | `security/settings-and-payload-hardening` | Day 6 pre-deployment gate and runbook: security branch delta/migration inventory, local build and focused regression verification, backup/rotation/deployment/rollback checklist, `docs/security-day-6-deployment-runbook.md`, and progress tracker update. No production access or credential values. | 2026-10-28 | Released - committed `94cbcd24` |
| Codex | `security/live-20261028-verified` | Day 6 live integration from exact production commit `34cdca12`: security commits, conflict resolution limited to affected application files, focused security tests, production build, deployment notes, `CHANGELOG.md`, and `AGENTS.md`. Generated `public/build` assets are excluded from the commit. | 2026-10-28 | Verified locally - push and deployment pending |
| Codex | `fix/account-dashboard-ledger-bank-balance` | Separate accounting fix: `packages/workdo/Account/src/Http/Controllers/DashboardController.php`, focused dashboard balance tests, `CHANGELOG.md`. This work does not include the security commit or generated `public/build` assets. | 2026-10-26 | Pushed - ready for user verification |
| Codex | `fix/overdue-bill-payment-selection` | `packages/workdo/Account/src/Http/Controllers/VendorPaymentController.php`, `packages/workdo/Account/src/Services/ReportService.php`, `OutstandingDocumentService.php`, focused Account tests, `CHANGELOG.md`. No data changes or generated assets. | 2026-10-28 | Released - pushed at `da58a92f` |

Remove or mark a claim `Released` as soon as that work is committed and handed off.

## Claude Track Status

Last updated 2026-10-25.

### Data correction

Company: Brill Creations (company user id 664). Source of truth for correct values: the Zoho ledger imported into Wazely on 2026-10-20 as `ZOHO-<zoho txn id>-<yyyymmdd>` journals, verified against Zoho for every account and year. Wazely Accounts Receivable (account 1100) = 127,395.90 and is correct; only invoice/payment/credit-note screens are wrong.

Root cause (9 Aug 2026 Zoho document import, files in `E:\Doha Workplace\Import data\ERP-Ready-Output-LIVE\`):

1. Invoice `paid_amount` was imported as a typed number (Zoho's settled amount incl. credits), not derived from payment records.
2. Invoice line discounts were missing and added later, so paid/balance were recalculated wrongly.
3. Customer payments that covered several invoices were imported with a blank invoice number, so they are linked to no invoice (52 payments, 432,038.69).
4. Credit notes were imported without discounts, invoice links or refunds (25 credit notes, 154,207.50 shown as open credit).

Completed on live (each with phpMyAdmin backup, local dry run, before/after check, journals unchanged):

| Date | Fix | Records changed | Scripts |
| --- | --- | --- | --- |
| 2026-10-06 | Al Koot: merged duplicate customer, linked payments | see memory/scripts | `ERP Data Fixes\Al Koot\` |
| 2026-10-14 | DOC Medical: merged duplicate customer, re-dated payment | see scripts | `ERP Data Fixes\DOC Medical\` |
| 2026-10-14 | Oishi Sushi: merged duplicate customer, linked 5 payments | see scripts | `ERP Data Fixes\Oishi Sushi\` |
| 2026-10-25 | Botanico INV-000533: payment 1088 pending -> cleared, invoice recalculated (no journal; Zoho journal already existed) | 1 payment, 1 invoice | `ERP Data Fixes\Botanico\` |
| 2026-10-26 | Phase 2: 144 payment links, 24 CNA credit applications, 25 credit notes closed, CN-00031 -> 2,000, BRINV-000071 -> 3,000, INV-000365 deleted, 192 invoices recalculated | live check after: unlinked 0, mismatches 1 (INV-000142), credit notes open 0; journals 3,577 and AR 129,645.90 unchanged by the fix (live had 5 new entries since the backup - SI-2026-10-006, CP-2026-10-006 and 3 manual journals - verified unrelated) | `ERP Data Fixes\Invoices - Phase 2\` |
| 2026-10-25 | Je phase 1: INV-000021/131/272/352/6875 recalculated (2 allocations added for Zoho payments #221 and #1), INV-000388 deleted (rejected per Je), pending hand-entered payments CP-2026-10-006/007/008 deleted | 5 invoices updated, 1 invoice + 1 item deleted, 3 payments + 4 allocations deleted, 2 allocations added; journals 3,572 before and after | `ERP Data Fixes\Je Invoices - Phase 1\` |

Deployment-side data change: migration `2026_10_24_000002_mark_imported_amount_discounts_as_fixed` marked 83 imported invoice lines (discount type `amount`, 0%, amount > 0) as fixed discounts; totals unchanged (verified locally: total discount 97,892.90 and invoice totals 2,634,651.11 identical before/after).

Open (phase 2), waiting for reviewers:

- `ERP Data Fixes\Invoice Balances\Invoice Recheck.xlsx` sent to Je on 2026-10-25: 73 invoices whose Wazely balance differs from the books - 45 "will be fixed" (correct balance proven from the ledger, all 0), 25 credit-note invoices, 3 to check (BRINV-000071 total 6,000 vs Zoho 3,000; INV-000142 6,420 vs 6,300; INV-000365 not in the Zoho ledger). Je fills "Your review" / "Your comment".
- Sithari asked for the Zoho credit note export (applied invoices, amounts, refunds) - needed for the 25 credit-note invoices, e.g. INV-000395 / CN-00031.
- Internal full list (not for sending): `Invoice Balances - Wazely vs Zoho.xlsx`.
- Not yet decided by accounts: monthly depreciation from Aug 2026 (manual journals vs automatic button).

Tests / dry runs done: every live fix was first run on `erp_live_copy` (latest live backup `erpsystem (14).sql`, 25 Oct 2026) and, for Je phase 1, also on a throwaway copy including a second run to prove idempotency. Invoice comparison ties out: invoice balances 276,453.40 + non-invoice AR lines -149,057.50 = 127,395.90.

Generators (session scratchpad, rebuild if lost): `invoice_recon.php` (exact document match = text after the last " - " in ZOHO journal line descriptions), `recon_xlsx.py`. Lesson: never extract invoice numbers with a loose regex (`INV-\d+` turned BRINV-000029 into INV-000029).

2026-10-26 update - Sithari's Zoho exports received (copies in session scratchpad `sithari_files/`; originals in Downloads): `Creditnotes_Invoice.xlsx` (28 applications, 24 credit notes, 149,057.50 applied) and `Invoice Details - 2021-2026.xlsx` (552 Zoho invoices with status, total, balance). Read-only comparison against `erp_live_copy` (= live after phase 1):

- All 45 "will be fixed" invoices: Zoho status paid, balance 0 - confirms the ledger-based result exactly.
- 25 credit-note invoices: Zoho balance 0 for 24; INV-000438 shows 2,200 overdue in Zoho but was paid in Wazely after go-live (CP-2026-09-003), so 0 is correct.
- Credit notes: every Wazely credit note total equals Zoho except CN-00031 (Wazely 2,700, Zoho 2,000). Unapplied amounts equal the Zoho refunds exactly: CN-00001 2,250, CN-00018 1,200, CN-00031 1,000.
- Needs-check answered by Zoho: BRINV-000071 total 3,000 paid; INV-000142 total 6,300 paid; INV-000365 Zoho status "approved" (never posted, balance 3,000) - still needs a decision.
- INV-000512/538/540/541/542/543 are "overdue" in Zoho but were paid in Wazely after go-live - Wazely is correct.
- Zoho drafts INV-000358/359/361/362/488 are drafts in Wazely too (362 and 488 totals differ, drafts only).
- 12 SI-2026-* invoices exist only in Wazely (created after go-live) - expected.

Je's review returned 2026-10-26 (`Downloads\Invoice Recheck.xlsx`): no dropdown values; 4 comments - BRINV-000071 "Okay", INV-000142 "Okay" (accept Zoho totals 3,000 / 6,300), INV-000365 "Void this", INV-000444 "Balance have been paid: Sept 8, 2025" (matches ledger: 700 on 20 Jul + 700 on 8 Sep 2025). 69 rows left blank (no objection recorded).

Phase 2 built and dry-run 2026-10-26 (`ERP Data Fixes\Invoices - Phase 2\`: `1-check-phase-2.sql`, `2-fix-phase-2.sql`, README). Approved correction list = Sithari's Zoho exports + Je's comments. Changes: 144 payment allocations (52 payments, 419,978.69); 24 credit-application payments `CNA-<CN>-<date>` (0 cash, bank 33 QIB) with 28 allocations and 24 `credit_note_applications`; 25 credit notes closed (refunds noted on CN-00001/18/31); CN-00031 total 2,700 -> 2,000 (item discounts 500 + 200); BRINV-000071 total 6,000 -> 3,000 (line discount 3,000); INV-000365 deleted (void per Je; Wazely has no void status and Zoho voids were never imported); 192 invoices recalculated. INV-000142 excluded (120 discount line unknown - needs Zoho PDF). Four Zoho payments not in Wazely by Zoho id are represented by other Wazely payments (CP-2026-10-005, CP-2026-09-002, CP-MIG-2026-08-05-ALKOOT) and are untouched.

Dry run on a throwaway copy (live backup 25 Oct + Botanico + phase 1): unlinked payments 52 -> 0; paid-vs-links mismatches 192 -> 1 (INV-000142); credit notes open 154,207.50 -> 0; journals 3,572 and AR 127,395.90 unchanged; all 507 Zoho-era invoices equal Zoho balance except INV-000142; second and third runs identical (idempotent). Generators (scratchpad): `phase2_plan.py`, `phase2_sim.py`, `phase2_sql.py`.

PHASE 2 RUN ON LIVE 2026-10-26 and verified by the user (backup taken; local test passed first).

INV-000142: Zoho PDF received from Je 2026-10-26 - 120 discount is on line 4 (item 729, 2,520 -> 2,400); the two free add-on lines stay (in Zoho's ledger, hidden on its PDF). Fix `ERP Data Fixes\INV-000142\fix-inv-000142.sql` tested on `erp_live_copy` inside a rolled-back transaction: total 6,300 / paid 6,300 / balance 0, check B -> 0. RUN ON LIVE 2026-10-26 and verified (local first): INV-000142 6,300 / paid 6,300 / balance 0; check B = 0 on live. All customer invoices now match Zoho.

Blockers: none for customer side.

Vendor bills phase 3 (2026-10-26): Sithari's Zoho exports in `ERP Data Fixes\Vendor Bills\` (31 bills, 33 vendor payments, 3 vendor credits). Wazely has the same 31 bills / 33 payments (VP-<zoho payment id>). Found: 5 multi-bill payments unlinked (13 links, 32,941.25), VC-01/02/03 not applied (62,535), bills' paid not recalculated; INV 163/2026 and INV 293/2026 paid in Zoho by Mr. Danush's journals BJV937/BJV978 (no payment record). Fix `2-fix-vendor-bills.sql` + `1-check-vendor-bills.sql` + README: links, 0-cash `DNA-VC-0x-20230101` credit applications + `debit_note_applications`, mark 163/293 paid with a note, recalc 29 bills. Dry run in a rolled-back transaction on `erp_live_copy`: all 31 bills = Zoho balance and status (only INV 359/2026 500 open), credits open 0, unlinked payments 1 (RAYBEAM 3,630 unused in Zoho too), journals/AP 9,144 unchanged, idempotent. AP books 9,144 = bill 359 500 + Newoon journal balance 8,644 (BJV908 +9,644, BJV938 -1,000). Generators (scratchpad): `vz_load.py`, `vz_cmp.py`, `vz_phase3.py`. PHASE 3 RUN ON LIVE 2026-10-26 and verified (local first, backup taken): 31 paid / 0 open + 1 overdue (INV 359/2026, 500, as in Zoho); bills paid by journal only: INV 163/2026, INV 293/2026, PI-2026-10-001; vendor credits applied, open 0; unlinked payments 1 (RAYBEAM 3,630); journals 3,578 (+1 = Sithari's new bill) and AP 9,144.

2026-10-26 follow-ups: live AP was 8,644 (not 9,144) because manual journal JE-2026-171 (6 Oct) debited AP 500 "Accounts payment - Sep 2026"; Sithari confirmed it pays Newoon's September bill BL09 2026, which was not in the system - she is adding it (posted, no payment). Sithari added it as PI-2026-10-001 (id 88, 30 Sep 2026, 500, posted). `ERP Data Fixes\Vendor Bills\3-mark-bl09-paid.sql` (guarded by id/number/vendor/500/posted/journal/no links) RUN ON LIVE 2026-10-26: paid 500, balance 0, note "Newoon invoice BL09 2026 ... paid by journal JE-2026-171". INV 359/2026 stays open as in Zoho. Feature request for Codex (not data): bill lines need a free-text Description like sales invoice lines - today bill lines can only pick saved Product & Service items (`purchase_invoice_items.description` column exists; form lacks the input).

Payment accounts (2026-10-27): payment screens' "Bank Account" list = active `bank_accounts`; clearing posts to `bank_accounts.gl_account_id` (`JournalService::createVendorPaymentJournal` throws if missing). Only QIB (and "Bank Transfer", also 1010) were linked. CORRECTION (re-checked): the bank account form DOES have a required "Gl Account" field, but `BankAccountController::index` only offers accounts 1000-1099 (`chartofaccounts` whereBetween account_code 1000..1099); requests only validate `exists`. Sithari's list of paying accounts: QIB, Credit Card, Petty Cash-Jeanne, Cash in hand, Current Account-Dhanusha Tharanga, Current Account-Sarath Kumara, Pasindu Payable. `ERP Data Fixes\Payment Accounts\link-payment-accounts.sql`: links ids 25->1000, 28->2050, 29->1005, 30->1332; adds "Current Account - Sarath Kumara" (1334) and "Pasindu Payable" (2411); sets inactive the unlinked unused ids 27 Petty Cash, 31 Projects on Process, 32 Salary Payable. Tested in a rolled-back transaction on `erp_live_copy` (idempotent). RE-VERIFIED 2026-10-27 (bank-account files identical to deployed `10a5f68f`): the Edit dialog initialises `gl_account_id` from the saved record (`BankAccounts/Edit.tsx` line 36) and only changes it on a user selection (line 141), so links outside 1000-1099 are KEPT on edit (the box just shows blank). The earlier "link lost on edit" concern was wrong; the script is safe and is the only way today to link 2050/1332/1334/2411. Alternative plan if preferred: Sithari links Cash In Hand -> 1000 and Petty Cash - Jeanne -> 1005 herself in Banking -> Accounts -> Edit (and can untick Is Active on unused entries); Codex widens the bank-account GL list (e.g. also credit card 2050, partner current accounts 1332/1334, employee/partner payables 2010-2499 as approved by accounts) so the remaining accounts can be added and linked in the UI. SITHARI CONFIRMED 2026-10-27: add BOTH Danush accounts (1332 current account + 2010 "Accounts Payable - Danusha Tharanga" as a new payment account) and hide the 3 unused entries. Script updated (third insert for 2010) and re-tested twice in a rolled-back transaction: 12 entries, 9 active and linked, 3 inactive, second run changes 0 rows. RUN ON LIVE 2026-10-27 and verified (after: ids 25-36, new 34 Sarath 1334, 35 Pasindu 2411, 36 Danusha AP 2010; 27/31/32 inactive; QIB + Bank Transfer unchanged). `erp_live_copy` reloaded 2026-10-27 from live backup `127_0_0_1 (1).sql` (10:38 UTC; its CREATE DATABASE/USE erpsystem lines stripped on import).

Exact next step: user runs phase 2 locally then on live (backup first); then a small INV-000142 fix once the PDF arrives; then release the data-correction claim and start the Zoho import redesign.

Account Dashboard check (2026-10-26, read-only, local copy of live 25 Oct; Sithari reported Bank Balance card 53,040 vs QIB account 3,001.99 which matches the bank):

- Bank Balance card (`packages/workdo/Account/src/Http/Controllers/DashboardController.php` financialStats) sums `bank_accounts.current_balance` - the Banking module's running total. For QIB it starts at 0 on 1 Aug 2026 and holds only 15 Wazely bank transactions (56,000 in - 5,210 out = 50,790 on the backup); Zoho history is not in it. Wrong.
- Cash Balance card sums `chart_of_accounts.current_balance` for 1000/1005: 666.41 = Zoho part only; books 153.75 (Wazely petty-cash entries -512.66 missing). Wrong.
- Accounts Receivable card (`ReportService::getInvoiceAging`) counts only invoices with status posted/partial: excludes 21 "overdue" invoices (81,600.90 open) and "paid" invoices that still show balances (phase 2 fixes those). Understated.
- Accounts Payable card (`getBillAging`, posted/partial only) shows 0, but books AP (2000) = 9,144.00; 29 bills are "paid" with 19,204.00 balance and 2 "overdue" 1,000.00 - same import pattern on the purchase side, not yet reconciled.
- The QIB account page and all reports read the books (journal lines) and are correct.

User decision 2026-10-26: the dashboard fix is a code change for Codex, to ship with the security update (not Claude's track). Claude stays on data correction. Proposed fix for Codex: dashboard cards read balances from the books (journal lines of the linked GL accounts; AR/AP from accounts 1100/2000 or from invoices once reconciled, including "overdue"); and a phase 3 reconciliation of vendor bills/payments like phase 2.
2026-11-01 (Je): test purchase order PO-2026-10-001 (The Proscreen, 200, cancelled, unbilled; id 2) deleted on live with `ERP Data Fixes\Purchase Orders\delete-test-po-2026-10-001.sql` (guarded, tested twice in a rolled-back transaction; live before-check matched: 1 item, 2 histories, 0 links). Purchase orders post no journals/stock. Feature request for Codex: Delete button for draft and cancelled-unbilled POs (today destroy() allows draft only and no screen has a Delete button), LPO PDF/preview for purchase orders (none exists), and format PO list dates (raw ISO shown). Same day: Je's payment for old invoices was blocked ("All invoices must use the payment currency") because pre-multi-currency documents have NULL currency_code (516 Brill sales invoices); fixed in `14ec361d`, deployed on dasherp.brillcrew.com; Je's Oishi 6,000 recorded as CP-2026-11-002 (3,000 to SI-2026-09-001, 3,000 to SI-2026-10-005), pending.
2026-10-28 (Sithari): Newoon bill INV 359/2026 (500, status overdue, data correct = Zoho open bill) does not appear under Outstanding Invoices in Create Vendor Payment. CODE cause for Codex: `packages/workdo/Account/src/Http/Controllers/VendorPaymentController.php` getOutstandingInvoices (line ~209) uses `whereIn(status, [posted, partial])`, so overdue bills can never be paid; the customer side (CustomerPaymentController) uses `whereNotIn(status, [draft, cancelled])` and works. Same overdue exclusion in `ReportService::getInvoiceAging` (line ~20) and `getBillAging` (line ~146), so aging reports omit overdue documents. No data change needed.

### Zoho import redesign

Status: planning only, no code written. Starts after the data corrections.

Goal: users export from Zoho Books and import the file into Wazely unchanged (no rebuilt Excel templates).

Existing code to build on: `app/Services/BulkImport/` (`BulkImportRegistry`, `EntityDefinition`, `SpreadsheetService`, 24 definitions in `Definitions/`), `app/Http/Controllers/BulkImportController.php`. Earlier remote branches to review first: `feat/zoho-style-bulk-import`, `zoho-books-bulk-import`, `credit-note-vendor-credit-imports`, `quotation-import`, `petty-cash-bulk-import`, `bank-transfer-journal-entry-imports`.

Requirements learned from the 9 Aug import (must be handled by the redesign):

- Never import a typed `paid_amount`; derive invoice paid/balance from imported payments and credit applications.
- Payments that cover several invoices must create one allocation per invoice (Zoho exports a row per applied invoice).
- Credit notes must keep discounts, the invoices they were applied to with amounts, and refunds.
- Document-level (whole-invoice) discounts must be kept, not spread across lines.
- Match document numbers exactly (e.g. `BRINV-000029` vs `INV-000029`, `RAPPINV-006726`).
- Customer matching must not create duplicates (Al Koot, DOC Medical, Oishi Sushi each had duplicates).

To be recorded here as the design is made: supported document types, Excel column mapping per type, validation rules, duplicate rules, rejected-row behaviour, reconciliation results, unsupported fields.

Zoho export inventory (Claude, 2026-10-28, read-only). Full detail with exact paths and headers: `E:\Doha Workplace\ERP Data Fixes\Zoho Import\Zoho Export Inventory.md`. Originals for Brill Creations (company user 664):

- Available: Chart of Accounts (`Import data\Sithari\Chart_of_Accounts.xlsx`, 144); Customers (`Import data\Sithari\Revenue Dump\Customer Contacts.xlsx`, 244); Vendors (`Import data\Import-Claude\2-Vendors\Vendors.xls`, 13); Estimates (`Import data\Import-Claude\9-Quotations\Estimate.xls`, 661 lines / 478, 2021-10-27..2026-07-19); Detailed General Ledger (`Import data\Sithari\Detailed General Ledger - Brill creation.xlsx`, 7,721 lines / 3,417 txns, 2021-05-10..2026-08-20); Trial Balances (yearly 2021-2025, 2026 to 31 Jul, Aug 2026, latest 01 Jan-31 Aug 2026 in `ERP Data Fixes\Supporting Dump -  August\`); Invoice Details report (`Downloads\Invoice Details - 2021-2026.xlsx`, 552) + invoice export (`Import data\Sithari\Revenue Dump\Revenue Invoice.xlsx`, 706 lines / 552); credit-note applications (`Downloads\Creditnotes_Invoice.xlsx`, 28 / 24 CNs); customer payments (563 lines / 460, per applied invoice); vendor payments (41 lines / 33, per bill).
- Partial: Expenses (`Import data\Import-Claude\11-Expense\Expense - Brill Creations.xlsx`, 1,567 lines / 1,360, ends 2026-07-27 while the ledger runs to 2026-08-20); Manual journals (no module export; 845 journals only inside the GL / Journal Reports); Bill Details (no report; Bills export `ERP Data Fixes\Vendor Bills\1 - Bills list.xlsx` has Balance + Status, 39 lines / 31); credit-note refunds (only 3 `creditnote_refund` txns in the GL).
- Missing: Items/products/services master (only names + Zoho Product IDs on document lines); AR and AP Aging Details; Inventory Valuation Summary (CoA "Inventory Asset" has no code and no ledger lines); vendor-credit -> bill applications (VC export "Associated Bill Number" is free text); vendor-credit refunds (none in the GL).
- Derivable safely: currency (all QAR; only INV-000168/169 USD, both Void), exchange rate (per document; 1.0 except 3.640998 on those 2), taxes (none - zero/blank on every line, no tax columns on sales exports), expense categories (Expense Account), payment accounts (Deposit To / Paid Through with Zoho codes present in the CoA). NOT derivable: units (blank on 704/706 invoice lines), item categories (no column; only income account), warehouse/location (no column, no stock postings) - these need user input or a Zoho Items export.
- 2026-10-31 `d4a81887` (release/zoho-and-security): upload guides offer XLSX/CSV templates (`ZohoExportTemplate`, route `zoho-migrations.stages.export-template`) whose headers/order/sheet names are copied from the real Zoho exports listed above. Follow-up on 2026-11-01 added Wazely structures for Refund History, Manual Journals and AR/AP Aging, so every file-based guide entry now has a template and visible accepted columns for clients migrating from other ERP systems.
- 2026-10-31 `9c1e2459` (release/zoho-and-security): currency display. Cause found on local Test COMPANY (950): new companies inherit the superadmin default currency (USD/$) and the migration's base currency (QAR) was never written to the company, so QAR documents showed as foreign with a $ line. Fix: migration setup syncs defaultCurrency/currencySymbol/currencyName when the company has no journal entries, else refuses with a message (`ZohoMigrationBaseCurrencyTest`); payment lists/views use each payment's `base_currency_code`; `formatTransactionMoney` uses the company currency instead of hardcoded QAR; `formatCurrency` reads the seeded `left`/`right` position as before/after (moves the symbol before the amount for every company whose setting is `left`, incl. live 664).
- No Zoho data exists here after 30 Sep 2026; per-export end dates differ (bills 30 Jun, vendor payments 2 Jun, estimates 19 Jul, expenses 27 Jul, payments 8 Aug, invoices 15 Aug, GL 20 Aug). Repo folders `.sithari-review`, `.tmp-ledger-review`, `outputs\invoice-comparison` hold byte-identical copies, not new data. Generated/import-ready/reconciliation files must not be used as sources (list in the inventory file).
- Second pass (complete search of C:\Users\acer incl. OneDrive/Desktop/Downloads at all depths, E:\ files since May 2026, all of GIT incl. storage uploads and worktrees; 468 files opened, Zoho ID columns checked): no new Zoho export type. Zoho column sets differ by export date - newer exports add Accounts Receivable / Accounts Payable / Adjustment Account / Shipping Charge Account (invoices 116->119, credit notes 80->83, contacts 80->81, bills 56->58, vendor credits 42->44), common columns in the same order -> import must map by header name, accept extra columns, not require optional ones. A Zoho .xls renamed .xlsx exists (`OneDrive\Desktop\Invoice (1).xlsx`) -> detect format by content. No sample at all of: Items, Manual Journals export, Purchase/Sales Orders, Recurring, Retainer, Transfer Fund/bank transactions, Tax rates, Projects, Inventory Adjustments, Price Lists, AR/AP Aging, Inventory Valuation, Bill Details, CN refunds, VC applications. This org has no VAT, so sales exports have no tax columns; a VAT org's tax columns are unseen.

## Required Session Workflow

1. Read this file completely.
2. Run `git status --short`, `git branch --show-current`, and `git log -5 --oneline`.
3. Fetch remote state when network access is available. Do not pull, merge, or rebase automatically.
4. Add or update your claim with branch and exact files/areas before editing.
5. Inspect related changes from the other workstream before modifying shared code.
6. Make narrowly scoped changes. Do not include generated `public/build` assets or `public/hot` unless explicitly requested.
7. Run relevant tests and record exact results below.
8. Update `CHANGELOG.md` when the change affects users, security, data behavior, imports, or deployment.
9. Commit only your work. Do not create a pull request unless explicitly requested.
10. Update the Handoff Log before finishing, even if work is incomplete or blocked.

## Data Safety Rules

- Production corrections require: approved source evidence, pre-change backup, dry-run output, affected-record count, transaction or rollback plan, post-change reconciliation, and reviewer sign-off.
- Imports must support validation before commit and must report accepted, rejected, skipped, and duplicate rows.
- Never silently coerce invalid financial values, dates, currencies, account references, customer/vendor links, invoice numbers, payment allocations, or credit-note applications.
- Security work must never print or store plaintext credentials in tests or logs.
- Database migrations must be backward-safe for deployment and reviewed for rollback behavior.

## Definition of Done

A work item is not complete until its code is committed, tests are recorded, affected workflows are manually checked when applicable, migrations and deployment steps are documented, the changelog is updated when required, and this coordination file contains a handoff entry.

## Handoff Log

### 2026-11-21 Asia/Colombo - Claude - URGENT: journal numbers stuck at JE-2026-1000 (all companies)

- Live log: `Duplicate entry 'JE-2026-1000' for key 'journal_entries_journal_number_unique'`. Cause: `JournalEntry::generateJournalNumber()` sorted the numbers as text and read only the last 3 digits, so after JE-2026-999 it kept generating JE-2026-1000, and every new journal for every company failed (500 on manual journals). It was triggered by the Charger demo backfill (596 POS sales) pushing the global counter past 999.
- Fix: take MAX of the numeric part (`CAST(SUBSTRING(...) AS UNSIGNED)`). Test `tests/Smoke/JournalNumberTest.php` fails on the old code and passes on the new one. Pushed to `release/2026-11-21` as an emergency fix (user approved).
- POS `store()` swallows listener exceptions (`catch (\Throwable)` → success), so demo sales from 23 Oct (partly) to 11 Nov were saved without their revenue/COGS journals. Backfill after the deploy.

### 2026-11-21 Asia/Colombo - Claude - Open demo-check findings 1-4 fixed

- Branch: `feature/petty-cash-documents`. Not deployed yet, no PR.
- Payments (customer, vendor, retainer) `updateStatus`: only `cleared`/`cancelled` accepted, and only from `pending`. The payment row is locked and re-checked inside one DB transaction, so a second click or tab can't post the journal, bank entry and invoice/bill/retainer balances twice. Credit/debit notes are applied only on clear (cancel used to apply them too). The customer credit note lookup is scoped to the company.
- Budget `active()`: recalculates spent/remaining for the period via `BudgetService::updateBudgetSpending` (before, it only refreshed when a new journal posted).
- Appointment public `store()`: server-side overlap check under a lock on the appointment; time formats validated, end after start; disabled appointments refused. A customer cancel saves status `cancel` but the booked-slot list only freed `cancelled`. Both now free `cancel`, `cancelled` and `reject`.
- Placeholder `@import.local` emails hidden (shared `displayEmail`) in the Convert Lead to Deal client picker and the Quotation Create/Edit customer pickers. Convert to Deal no longer sends the "New User" welcome email when an existing client is picked.
- Tests: `tests/Smoke/OpenFindingsFixesTest.php` (5; all fail on the old code). Full smoke suite 84 pass. Deploy needs `npm run build` (no migration).
- Still open: decisions (P&L per cafe, POS stock check, salary posting) and the Codex security list.
- 2026-11-21: branch `release/2026-11-21` (= aa3297ae) created and DEPLOYED to live. Verified on the live demo: re-clearing cleared vendor/customer/retainer payments is refused and nothing is posted; double booking is refused; a rejected booking frees its slot; the placeholder filter is in the live build; 119 demo pages open. The budget activation fix was not exercised on live (an active test budget can't be deleted); it is covered by the local test.
- From now on (user's rule): keep new work local. No new branch, commit or push until the user says all work is done.

### 2026-11-19 Asia/Colombo - Claude - Petty cash supporting documents + draft fund edit/delete

- Branch: `feature/petty-cash-documents` (from `release/2026-11-16` @ 343f5a01). Not deployed, no PR.
- New table `petty_cash_attachments` (package migration `2026_11_19_000001`), with `attachable_type` = `request`|`fund`, files on the private `local` disk under `petty-cash/{company}/...`. There are up to 5 files per record (PDF/JPG/PNG/Word/Excel/ZIP, 5 MB each). Routes: `petty-cash-management.attachments.store|download|destroy`, scoped to company + own/any permission. Files can be attached on the create form of requests and funds, and added, previewed, downloaded or removed in each View dialog.
- Petty Cashes: edit/delete were shown only on the newest-dated fund, so a draft dated earlier than an approved fund (Brill: PC-2026-11-001 dated 30 Sep vs PCJ-030 06 Oct) had no edit/delete. That was Sithari's report. Now any draft fund can be edited or deleted. Server side, approved funds are refused, a fund with spending can't be deleted, and the fund must belong to the company. Requests: pending ones already had edit/delete on live (checked). The server now refuses editing approved/rejected requests (it used to reset them to pending).
- Tests: `tests/Smoke/PettyCashDocumentsTest.php` (8). Full smoke suite 79 pass. Deploy needs `php artisan migrate` + `npm run build`.
- DEPLOYED to live 2026-11-19 (server folder now on `feature/petty-cash-documents` @ 01699713, migrated, built). Verified on live: both Create forms show the documents section, upload + download from View works, draft fund shows edit/delete. Test request Req-2026-11-002 deleted (its file removed with it).

### 2026-11-19 Asia/Colombo - Claude - CRM dashboard counts + timesheet whole hours

- Branch: `release/2026-11-16`. Lead `DashboardController`: Clients and Users counted with separate queries (the shared builder made Clients always 0); Users = company staff via `User::emp([], ['hr'])`, so vendors/clients are no longer counted. Timesheet Store/Update requests: minutes 0-60 allowed, plus a check that hours+minutes > 0 ("Enter a time greater than 0."); Create/Edit minute dropdowns now 0-60 (was 1-60, so whole hours and attendance auto-fill on the hour failed).
- Tests: 2 new in `tests/Smoke/DemoCheckFixesTest.php`; full smoke suite 71 pass. Deploy needs `npm run build` (timesheet pages).

### 2026-11-19 Asia/Colombo - Claude - Charger Cafe demo: remaining sections filled (no code change)

- erp.wazely.io (= dasherp site/DB), company "Charger cafe". Added: CRM (4 sources, 3 labels, 5 leads, 3 deals, 1 won, 1 converted from a lead), Assets (4 categories, 3 locations, 7 assets, 2 assignments, 3 maintenance, 3 depreciation), Budget (Q4 period + operating budget 453,000, 6 allocations; monitoring 64,005 spent), Appointments (hours, 3 questions, 3 booking types, 6 public bookings, 1 callback), Projects (2 projects, 4 milestones, 8 tasks), Timesheet (5), Contracts (4 types, 4 contracts, 1 renewal), Goals (3 categories, 3 goals, 3 milestones, 3 contributions), Retainers (2, one paid), quotations (2, both converted via the real UI - old "no feedback" bug no longer happens), product invoice + sales return + 2 credit notes, revenue entry, proposal. Helpdesk skipped on purpose (tickets go to real Wazely agents). TB balanced; 119 pages load, 28 new screens render without console errors.
- Bugs found, not fixed yet: CRM dashboard "Clients" always 0 (reused `$users` builder) and "Users" counts vendors; Timesheet rejects whole hours (`minutes` min:1); budget spending not recalculated when a budget is activated (only on the next journal); public appointment booking has no server-side double-booking check; retainer/vendor payment "cleared" has no guard against clearing twice; customers without portal show `client.<id>.<random>@import.local` in CRM client pickers.
- For Codex (security): `crm/leads/order` updates `Lead::where('id', $item)` unscoped; `crm/deals/{deal}/change-status`, budget approve/active have no company check; `StoreSalesProposalRequest` customer/product ids not company-scoped.

### 2026-11-19 Asia/Colombo - Claude - Demo check fixes (payslip, dashboards, HR lists, POS till)

- Branch: `release/2026-11-16`. (1) `JournalService::createPayrollJournal` checks the payroll's bank account (own company) and GL link before writing anything; clear message instead of "glAccount on null"; payslip stays unpaid. (2) Account dashboard Recent Expenses: newest by `expense_date`, shows that date, base currency; Recent Revenue amounts also in base currency; all dates sent as plain `Y-m-d` (date casts serialized with a timezone could show a day early). (3) POS dashboard today/week/month, last-10-days and recent sales use `pos_date`. (4) HRM employee pickers (Add/Edit Employee, leave, attendance, awards, warnings, complaints, promotions, transfers, resignations, terminations) include `hr`-type users via `User::emp([], ['hr'])`; other modules unchanged. (5) POS till (`pos.products`, `pos.create` categories) hides items with sale price 0.
- Tests: `tests/Smoke/DemoCheckFixesTest.php` (5) + `PosSalesReportTest` (2); full smoke suite 69 pass. Deploy needs `npm run build` (Sales.tsx).
- Not changed (needs a decision): P&L per cafe, POS stock check, salary journal posts net pay on click date.

### 2026-11-19 Asia/Colombo - Claude - POS sales report filters + dashboard recent revenue

- Branch: `release/2026-11-16`. `PosReportController::sales`: date from/to (default this month) and warehouse filters; all figures are net of discount (`pos_payments.discount_amount`) and dated by `pos_date`, not `created_at`; adds summary (net, count, discounts, average) and a paginated sales list tab. `Sales.tsx` gets the filter bar, summary cards and list.
- Account `DashboardController`: "Recent Revenue" now merges revenue entries, posted sales invoices (base currency) and POS sales, newest by document date, top 5.
- Tests: `tests/Smoke/PosSalesReportTest.php` (2 pass). Needs `npm run build` on deploy (frontend changed).

### 2026-11-18 Asia/Colombo - Claude - Charger Cafe demo company check (erp.wazely.io)

- No code changed. Filled demo data for `chargercafe@test.com` on erp.wazely.io (COO request: HR, procurement, POS for 2 cafes, admin, revenue): 3 branches, 10 departments, 14 designations, 4 roles, 12 users/employees, attendance, leave, payroll (Oct, 3 payslips paid), 4 POs -> bills -> payments + 1 return, transfers, 49+1 POS sales over 2 cafes, catering invoice + receipt, 2 expenses, petty cash, opening-balance journal. Base currency set USD -> QAR; company details filled. TB balances; bank/cash match the ledger.
- Found, not fixed: POS sales report ignores discounts (4,906 vs 4,836), has no date/cafe filter, and charts use `created_at` not `pos_date`; paying a payslip on a payroll with no bank account shows raw PHP error (`glAccount` on null); account dashboard "Recent Revenue" ignores POS/invoices and "Recent Expenses" shows created date; P&L cannot split by cafe; journal numbers are global (new company got JE-2026-338).
- 2026-11-19 recheck: all sections have data. Working days were Mon-Fri, so 15 Nov (Sunday) attendance was rejected; set all 7 days and added it (36 records). HRM employee dropdowns list 11 of 12 (the `hr` role user is left out).
- For Codex (security): `product-service/stock` store and journal `items.*.account_id` use `exists` without `created_by` scope; POS store trusts client `items.*.price` and does not check stock; `expense_categories.category_code` unique across all companies.

### 2026-11-18 Asia/Colombo - Claude - Petty cash history import (step 2 of 2)

- Branch: `release/2026-11-16`. New `php artisan petty-cash:import-ledger` (dry run unless `--apply`): reads the journals of a petty cash account and creates top-up funds (`PCJ-nnn`) per money-in line and an approved request + expense (`PCJ-R-nnnn`) per spend line, split by expense account, with categories per account. No journals are created or changed (expenses are saved without dispatching `CreatePettyCashExpense`). Refuses a second run with the same prefix; checks money in/out/balance and journal line count equal the ledger before saving.
- Rehearsed on the 2026-11-18 live backup (local DB `erp_live_20261118`): 30 top-ups, 293 spend lines, 12 categories, balance 1,176.75 = ledger, 316 journal lines unchanged; rollback SQL restored PC-JEANNE exactly.
- Live steps, backup and rollback: `docs/petty-cash-history-import.md`. Applied on LIVE 2026-11-18 by the user after a dry run identical to the rehearsal (20,530.00 in / 19,353.25 out / 1,176.75 balance, 316 journal lines unchanged). Backup table `petty_cashes_backup_20261118` on live - drop once accepted.
- Tests: `tests/Smoke/PettyCashLedgerImportTest.php` + posting tests, 8 pass.

### 2026-11-18 Asia/Colombo - Claude - Petty cash spending now posts to the accounts (step 1 of 2)

- Branch: `release/2026-11-16`. Sithari agreed (2026-11-18): keep the imported Zoho petty cash journals; make the petty cash section usable and later show the history there.
- Categories have an expense account (`petty_cash_categories.account_id`, migration `2026_11_18_000001`, same choices as bill lines); required on create/edit when Account is active.
- Approving a request or reimbursement posts Dr category account / Cr 1005 on the approval date (`CreatePettyCashExpenseListener` -> `JournalService::pettyCashExpenseJournal`), inside one transaction with the fund and expense rows; a category without an account blocks approval. Deleting an approved one removes the journal, returns the amount to the fund and deletes the expense (`PettyCashLedger::reverse`).
- Top-up journal was reversed (Dr bank / Cr petty cash) and dated now; now Dr 1005 / Cr bank GL on the fund date (bank transaction dated the same).
- Fund used for spending/new top-ups is the newest by date (`PettyCashLedger::currentFund`); `latest()` picked arbitrarily between Brill's PC-JEANNE and PC-MAIN (same created_at).
- Live after deploy: run the migration; set expense accounts on existing categories before approving. PC-JEANNE closing 179.41 is the Zoho July figure, not today's ledger 1,176.75 - step 2 must reconcile it.
- Tests: `tests/Smoke/PettyCashPostingTest.php` 7 pass; `npm run build` passes.

### 2026-11-17 Asia/Colombo - Claude - Helpdesk emails no longer need a queue worker

- Branch: `release/2026-11-16`. Live had no queue worker, so every Helpdesk email stayed `pending` in `helpdesk_email_deliveries` until someone ran `queue:work` by hand (done once on 2026-11-16; all sent).
- `HelpdeskEmailService` now sends each delivery in an `app()->terminating()` callback (after the response, so the user does not wait) instead of `SendHelpdeskEmail::dispatch()`. The same callback retries up to 5 earlier `pending` deliveries (attempts below the job's `tries`, last tried 2+ minutes ago, created in the last 24 hours). `SendHelpdeskEmail::handle()` marks a delivery `failed` after the last try, else leaves it `pending` with `last_error`.
- Tests: Phase 3 + Helpdesk security + settings Phase 2 smoke tests, 48 pass (new: sends without a worker and retries a failed one).

### 2026-11-16 Asia/Colombo - Claude - Create did nothing for customers made in the ERP

- Branch: `release/2026-11-16`. User report from live: Sales Invoice Create did nothing for a customer they created; imported customers worked.
- Cause: `CustomerController`/`VendorController` save every new party with `preferred_currency_code` (the company currency). Sales Create/Edit, Credit Note, Customer Payment and Debit Note forms apply that currency on selection and blank `exchange_rate`, while the Multi-Currency section stays hidden; the server rejects the empty rate and the error renders inside the hidden section. Not SMTP: the email is sent only after the invoice is saved.
- Fix: `resources/js/components/transaction-currency-fields.tsx` - base currency with no valid rate gets 1.00; a foreign currency opens the Multi-Currency section so the rate and its error are visible. Needs `npm run build` on live.
- Also confirmed: on email failure `SalesInvoiceController::store` returns `back()` to the Create form although the invoice was saved (duplicate risk on re-click) - not changed.

### 2026-11-16 Asia/Colombo - Claude - All Account reports converted to base currency

- Branch: `release/2026-11-16` (uncommitted). User report from live: Vendor Detail showed USD amounts with a QAR symbol.
- `packages/workdo/Account/src/Services/ReportService.php`: new `rate()`, `base()`, `sumBase()` helpers; Invoice Aging, Bill Aging, Tax Summary, Customer Balance, Vendor Balance, Customer Detail and Vendor Detail now multiply every amount (invoices, returns, credit/debit notes, payments, item taxes) by its own document's exchange rate. Same field names, so the screens and print pages are unchanged.
- Checked and already correct: DoubleEntry reports (General Ledger, Trial Balance, P&L, Balance Sheet, Cash Flow, Expense) read journal lines, and `JournalService` posts amount x rate.
- Tests: `tests/Smoke/VendorBalanceMultiCurrencyTest.php` now 2 tests / 18 assertions (vendor balance + detail + bill aging; customer balance + detail + invoice aging) - pass.

### 2026-11-16 Asia/Colombo - Claude - AI Assistant back to per-company keys; central AI page removed

- Branch: `security/settings-scope-2026-11-15` (uncommitted). User decision: the platform must not pay for company AI usage.
- `AIAssistantSettingsController.php` and `ai-assistant-settings.tsx` restored byte-for-byte from `63a976bd8^` (provider, model, masked key, Clear, audit).
- `CentralAISettingsService` now reads only the company's own `ai_*` / `ai_agent_*` settings (no `admin_setting`); `availability()` still drives the AI button. `BrowserSettingsPayloadService`: `ai_provider`/`ai_model` and `ai_api_key_configured` on the company settings page; `ai_assistant_enabled` dropped.
- Removed: super admin "AI Settings" menu, `platform-ai-settings.tsx`, route `settings.platform-ai.update`, `SettingController::updateCentralAISettings`, `SETTING_KEYS['central_ai']`. The `manage/edit-platform-ai-settings` permissions (migration + seeder) are left in place, unused.
- Runbook: no Phase 2 data step (company AI keys are kept); the audited company keys should be rotated by those companies.
- Tests: SettingsScopePhase2Test 10/10, Phase2Security 4/4, FormPayloadCompatibility 16/16, SettingsScopeSecurity 6/6, BrowserSettingsPayload 2/2.

### 2026-11-16 Asia/Colombo - Claude - SMTP is super admin only

- Branch: `security/settings-scope-2026-11-15` (uncommitted). User decision: companies must not see or set SMTP; they keep sending email through the central mailer.
- Company settings menu: Email Settings removed (`company-setting.ts`). `updateEmailSettings` and `testEmail` now require `canManagePlatformSetting` (super admin).
- `SetConfigEmail()`: any non-super-admin owner always uses the central super admin SMTP (company name as sender name unless `company_email_from_name`, company email as reply-to); saved company SMTP rows are ignored, not deleted. `EmailTemplate::sendEmailTemplate` always uses the central mailer; `CommonEmailTemplate` no longer falls back to a company's own from-address; VideoProduction report email checks the central mailer instead of company SMTP.
- `BrowserSettingsPayloadService::settingsPage`: `email_*` and `email_password_configured` only for the super admin.
- Tests: SettingsScopePhase2Test 10/10 (new: company cannot save/test SMTP), plus unit settings tests and HelpdeskNotificationsPhase3Test pass. Runbook updated.

### 2026-11-16 Asia/Colombo - Claude - Multi-currency purchase invoice view and Vendor Balance

- Branch: `security/settings-scope-2026-11-15` (uncommitted).
- `resources/js/pages/Purchase/View.tsx`: unit price, line tax/discount/total, subtotal, discount, tax and paid show in the invoice currency (like Print.tsx); both Balance Due figures show balance x exchange_rate in the base currency (like the list).
- `ReportService::getVendorBalanceSummary`: billed, returns and balance summed as amount x exchange_rate (NULL/0 rate = 1) and cast to float, which also fixes the Total row showing 0.00 for Total Billed (string sums were concatenated in JS).
- New `tests/Smoke/VendorBalanceMultiCurrencyTest.php` (pass). Local data: all purchase invoices are at rate 1 (3 Brill USD bills to Newoon at 1.00), so existing report numbers are unchanged.
- Not changed (same pattern, not asked): Customer Balance report, Vendor/Customer Detail reports.

### 2026-11-15 Asia/Colombo - Claude - AI Agent back to per-company keys

- Branch: `security/settings-scope-2026-11-15` (uncommitted). User decision: clients pay for their own AI Agent usage.
- `ai-agent-settings.tsx` restored to the pre-Phase 2 form (provider, model, masked key with Clear). `SettingController::updateAIAgentSettings` restored (key audited, never returned); `central_ai` now holds AI Assistant keys only; `platform-ai-settings.tsx` shows the AI Assistant only.
- `CentralAISettingsService::agentConfig()` reads the company's own `ai_agent_*` settings (never the super admin's). `BrowserSettingsPayloadService::settingsPage` returns `ai_agent_provider`/`ai_agent_model` and `ai_agent_api_key_configured` to the company's settings page; the shared payload still carries no AI keys.
- Runbook Phase 2 data step now clears company `ai_api_key` only (local: 3 rows); the 1 company `ai_agent_api_key` row is kept, and that company should rotate it.
- Tests updated: SettingsScopePhase2Test 9/9, Phase2Security 4/4, FormPayloadCompatibility 17/17, BrowserSettingsPayload 2/2, SettingsScopeSecurity 6/6.

### 2026-11-15 Asia/Colombo - Claude - Brand Settings: logos are super admin only

- Branch: `security/settings-scope-2026-11-15` (uncommitted).
- `brand-settings.tsx`: Logos tab and section shown only to the super admin (like Text); companies open on Theme and no longer submit logo keys.
- `SettingController::SETTING_KEYS['brand_company']`: removed `logo_dark`/`logo_light`, so a company post with logos is rejected.
- Test added in `SettingsScopePhase2Test` (8/8 pass). Existing company logo rows are left as they are (not deleted).

### 2026-11-15 Asia/Colombo - Claude - Settings scope Phase 4: release preparation and rehearsal

- Branch: `security/helpdesk-notifications-phase3-2026-11-16` (release branch for Phases 1-3); runbook commit pushed without a PR. No code changes in this step.
- Release checks: branch contains Phase 1 (`07e7a2b75`, `3d8891823`), Phase 2 (`63a976bd8`) and Phase 3 (`a77dfcec2`); `origin/main` is an ancestor and the merge is clean; only `public/` path vs main is the intended `public/hot` deletion. The branch is 129 commits / 621 files ahead of `main`, so the deploy brings all work since the commit live runs.
- Migrations: 16 since `release/zoho-and-security` (22 since `10a5f68f`), including 3 module migrations; all already applied on local `erp_live_copy`, none pending.
- Dependencies: composer unchanged; package.json/lock changed (fonts) so the server needs `npm ci` before `npm run build`.
- Data-step rehearsal on `erp_live_copy` inside a rolled-back transaction (temporary tables): Phase 1 110 company brand rows backed up/updated/0 left public, super admin keeps 11 public brand rows for the login page; Phase 2 4 company AI key rows backed up/cleared; both rollback statements restored everything; settings checksum unchanged afterwards.
- `php artisan security:check` locally: only the expected local failures (APP_ENV local, APP_DEBUG on, an untracked dump in `backups/`); live must report all checks passed.
- New: `docs/security-settings-scope-release-runbook.md` (pre-flight, backup, code, migrations, both data steps with counts, caches/queue, post-deploy configuration, verification, rollback, cleanup).
- Next (user/server operator): confirm live commit, prepare rotated credentials, back up, run the runbook, verify, revoke old credentials.

### 2026-11-15 Asia/Colombo - Claude - Settings scope Phase 3: Helpdesk notifications

- Branch: `security/helpdesk-notifications-phase3-2026-11-16` (from Phase 2 `63a976bd8`); pushed without a PR.
- Problem found: the two Helpdesk email templates are customer wording ("your ticket has been created", "your request has been received") but were used for every event, so super admins and agents received customer text. Support had no mailbox (all super admin logins), and one "Helpdesk Ticket Reply" switch drove assignment, reply and status emails.
- Implemented:
  - `app/Services/HelpdeskNotificationSettings.php`: 7 events (`ticket_created_support`, `ticket_created_confirmation`, `ticket_created_company`, `ticket_assigned`, `reply_to_customer`, `reply_to_support`, `status_changed`), private super admin switches `helpdesk_mail_<event>`, support mailbox `helpdesk_support_email` (max 5). Unsaved switches inherit the legacy `Helpdesk Ticket` / `Helpdesk Ticket Reply` values; the two new events start off. No migration or data change.
  - `app/Services/HelpdeskEmailContent.php`: subject and body per event; ticket text stays escaped through `HelpdeskTicket::emailText()`.
  - `app/Services/HelpdeskEmailService.php`: per-event switches and recipients; support mailbox (fallback super admin logins) for new tickets and unassigned customer replies; optional customer confirmation; optional company notification only when a client/staff (not the company itself) raised the ticket; customer emails reply to the first mailbox address. Delivery keys unchanged; template stored as `helpdesk:<event>`.
  - `app/Jobs/SendHelpdeskEmail.php`: renders `helpdesk:` deliveries through `CommonEmailTemplate` on the central SMTP; older deliveries keep the template path.
  - `SettingController`: `updateHelpdeskNotificationSettings` (super admin type + `manage-email-notification-settings`, unknown keys rejected, addresses validated) at `settings.helpdesk-notifications.update`; `helpdeskNotificationSettings` page prop; legacy switches removed from Email Notification Settings listing and save.
  - `resources/js/pages/settings/components/helpdesk-notification-settings.tsx`, super admin menu and registry.
  - Tests: new `tests/Smoke/HelpdeskNotificationsPhase3Test.php` (7); `HelpdeskSecurityTest` legacy-switch test rewritten for the new section.
  - Docs: `docs/security-settings-scope-phase3-deployment.md`; `CHANGELOG.md`.
- Verification: Phase 3 smoke 7/7; Helpdesk smoke 30/30; Phase 2 smoke 8/8; SecurityRoleMatrixTest 1/1; full unit suite 196 with the same 5 pre-existing unrelated failures; PageSmokeTest 333 pages, 0 server errors; tsc clean for changed files; `npm run build` passed; `git diff --check` clean. Not seen in a browser.
- Deploy: no migration. Clear caches, keep the `emails` queue worker running, then set the support mailbox and switches.
- Next: user browser check of Settings → Helpdesk Notifications and one test ticket; deploy Phases 1-3 in order.

### 2026-11-15 Asia/Colombo - Claude - Settings scope Phase 2 takeover and completion

- Branch: `security/settings-scope-phase2-2026-11-16` (from `security/settings-scope-2026-11-15` @ `3d8891823`); pushed without a PR.
- Takeover: Codex hit its usage limit mid-Phase 2 with 28 files edited and nothing committed. Claude reviewed every Codex change against the Phase 2 prompt and kept them: `CentralAISettingsService`, super admin AI Settings endpoint/page/permissions (migration `2026_11_16_000001` + seeder), company AI Agent/AI Assistant reduced to on/off switches, AI readers on central credentials, Brand/System company vs platform allowlists, optional company SMTP with fallback envelope in `EmailTemplate::sendEmailTemplate`/`CommonEmailTemplate`, settings menus, tests and runbook.
- Claude fixes on top of Codex's work:
  1. AI Assistant buttons would have disappeared for every company: `fields.tsx` needed `ai_provider`/`ai_model`/company key flag, which Phase 2 removed from the browser. Now `CentralAISettingsService::availability()` adds booleans `ai_assistant_available`/`ai_agent_available` to `companyAllSetting` (HandleInertiaRequests); the old per-company `ai_*_api_key_configured` flags are no longer shared.
  2. Company Brand form was half-hidden (Logos tab hidden, light logo hidden, empty Text tab). Companies now see both logos and Theme; favicon and the Text tab are super admin only.
  3. SMTP fallback only covered template emails. `SetConfigEmail()` now falls back to the central super admin SMTP when the company lacks host or sender address (company name as sender name, company email as global reply-to, mailer purged so the config applies). Covers all ~15 direct senders (password change, user/portal invites, leads, video production, imported clients, workflows, reminders). Workflow/reminder pre-checks now test the effective mailer; `CommonEmailTemplate` falls back to the active mailer's sender.
  4. Raw central-SMTP errors are no longer returned to company users; the exception class is logged.
- Files (Claude): `app/Helpers/Helper.php` (SetConfigEmail), `app/Models/EmailTemplate.php`, `app/Mail/CommonEmailTemplate.php`, `app/Services/BrowserSettingsPayloadService.php`, `app/Services/CentralAISettingsService.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `packages/workdo/AIAssistant/src/Resources/js/fields/fields.tsx`, `resources/js/pages/settings/components/brand-settings.tsx`, `tests/Unit/BrowserSettingsPayloadServiceTest.php`, `tests/Smoke/SettingsScopePhase2Test.php` (+3 tests), `docs/security-settings-scope-phase2-deployment.md` (behaviour notes), `CHANGELOG.md`.
- Database: migration `2026_11_16_000001` run on local `erp_live_copy` only (2 permissions granted to superadmin) and permission cache reset. Nothing on live.
- Tests: settings unit suite 46/46; Phase 2 smoke 8/8 (phpunit.smoke.xml); SecurityRoleMatrixTest 1/1; Helpdesk smoke 30/30; full unit suite 196 tests with the same 5 pre-existing unrelated failures; PageSmokeTest 333 pages, 0 server errors; `npx tsc` clean for changed files; `npm run build` passed; `git diff --check` clean.
- Not seen in a browser.
- Deploy: follow `docs/security-settings-scope-phase2-deployment.md` after Phase 1. AI stays unavailable until the super admin enters the rotated central keys; companies without SMTP start sending through the central mailbox.
- Next: user browser check (super admin AI Settings, company Brand/System/Email/AI switches, one invoice email from a company without SMTP), then Phase 3 (Helpdesk mailbox and per-event switches) on the user's go-ahead.

### 2026-11-15 Asia/Colombo - Codex - Settings scope Phase 1 follow-up

- Branch: `security/settings-scope-2026-11-15`; released without a PR.
- Commit: included in this handoff commit.
- Files changed: `app/Http/Controllers/SettingController.php`, `tests/Unit/SettingsFormPayloadCompatibilityTest.php`, `docs/security-settings-scope-deployment.md`, `CHANGELOG.md` and `AGENTS.md`.
- Database records/migrations affected: none. No live access or data changes were performed.
- What was implemented: restored the exact Company, Currency, Cookie and Pusher form payloads to their endpoint allowlists with validation; Pusher `enabled` persists as `pusher_enabled`; superadmin currency is public for guest pricing while company currency and company information remain private; audited every remaining Settings form against its allowlist; added exact POST-payload contract coverage for all Settings sections.
- Decisions and assumptions: no guest page reads superadmin company, registration or tax fields, so those remain private. Existing secret-preservation and clear controls remain unchanged, and no credential value was read or printed.
- Tests run and results: settings security/unit suite passed 36 tests and 138 assertions; `SecurityRoleMatrixTest` passed 1 test and 11 assertions; Helpdesk smoke passed 30 tests and 168 assertions; PHP syntax, `npx tsc --noEmit`, `npm run build` and `git diff --check` passed.
- Manual verification: all Settings TSX state objects were compared with their server allowlists; the deployment SQL now creates its MySQL backup table before starting the update transaction.
- Known issues or blockers: none for this follow-up.
- Deployment or rollback notes: follow `docs/security-settings-scope-deployment.md`; generated `public/build`, `public/hot`, `tmp` and storage files are excluded.
- Files/areas still claimed: none after this release.
- Exact next step: review and deploy the branch, then perform the documented backup/update/cache-clear verification steps.

### 2026-11-15 Asia/Colombo - Codex - Settings scope and security Phase 1

- Branch: `security/settings-scope-2026-11-15`; released without a PR.
- Commit: included in this handoff commit.
- Files changed: `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Controllers/SettingController.php`, `app/Helpers/Helper.php`, `packages/workdo/AIAssistant/src/Http/Controllers/AIAssistantSettingsController.php`, `tests/Unit/SettingsScopeSecurityTest.php`, `tests/Unit/SensitiveSettingsAuthorizationTest.php`, `docs/security-settings-scope-deployment.md`, `CHANGELOG.md` and `AGENTS.md`.
- Database records/migrations affected: none locally or on live. The deployment runbook contains a counted, backed-up SQL data step to mark existing company brand rows private.
- What was implemented: anonymous users now receive only allowlisted superadmin branding; company brand writes cannot affect login; settings writers reject unknown keys; settings are private by default with intentional public superadmin sections explicit; cache/optimization, cookie, SEO, storage, Pusher and bank-transfer operations require the superadmin type plus permission; company SMTP save/test rejects private, loopback, link-local, reserved and unresolved hosts; test-mail failures log only safe metadata and show a generic message.
- Decisions and assumptions: per-company SMTP remains supported in Phase 1; central AI credentials, separated Platform/Company Settings and Helpdesk mailbox architecture remain Phase 2/3 work. No secret value was printed, logged or committed.
- Tests run and results: PHP syntax passed for all changed PHP files; focused security/unit suite passed 25 tests and 107 assertions; `npm run build` passed with 4,023 modules. `SecurityRoleMatrixTest` and Helpdesk smoke tests were not run against `.env` because it targets `erp_live_copy`; disposable SQLite initialization did not complete within the command window.
- Manual verification: frontend payload keys were compared with every endpoint allowlist; generated `public/build`, `public/hot`, `tmp` and storage artifacts remain excluded.
- Known issues or blockers: exposed credentials must still be rotated by the user. Stripe plan activation still trusts `return_type` in `StripeController.php:298`; intentionally not changed in this branch.
- Deployment or rollback notes: follow `docs/security-settings-scope-deployment.md`: back up first, record affected-row count, create the rollback table, update only public company brand rows, clear caches and verify anonymous login; rollback restores `is_public` by setting ID.
- Files/areas still claimed: none after this release.
- Exact next step: deploy and verify Phase 1, rotate exposed credentials, then wait for user decisions before Phase 2 or Phase 3.

### 2026-11-15 Asia/Colombo - Codex - Email notification toggle persistence

- Branch: `fix/email-notification-toggles-2026-11-15`. Released on this branch without a PR.
- Fixed the Settings page to return only its authorized private mail-notification values alongside the safe settings payload, so enabled switches remain enabled after save and refresh without exposing them application-wide.
- Hardened the save endpoint with the existing `manage-email-notification-settings` permission, on/off validation and an allowlist derived from visible notification records.
- Helpdesk ticket and reply delivery now checks the matching superadmin notification switch before creating a queued delivery.
- Files changed: `SettingController.php`, `HelpdeskEmailService.php`, `email-notification-settings.tsx`, `HelpdeskSecurityTest.php`, `CHANGELOG.md` and this handoff. No migration or manual data changes.
- Verification: PHP syntax passed for all changed PHP files; two focused Helpdesk smoke tests passed independently; `npx tsc --noEmit` and `git diff --check` passed.

### 2026-11-15 Asia/Colombo - Codex - Ticket Records UI simplification

- Branch: `UI-upgrade-2026-11-14`. Released on this branch without a PR.
- Renamed the company/client Helpdesk child menu from Helpdesk to Tickets. Ticket Records retains the five KPI cards while removing only the By Status, By Priority and By Category breakdown panels.
- Added the same permission-aware top-right plus action used by Manage Tickets. It opens the existing create-ticket dialog and remains hidden without `create-helpdesk-tickets`.
- Files changed: `resources/js/utils/menus/company-menu.ts`, `resources/js/pages/helpdesk/records/index.tsx`, `CHANGELOG.md` and this handoff. No backend, permissions, migrations or data changed.
- Verification: `npx tsc --noEmit` and `git diff --check` passed. Pre-existing generated `public/build`, storage and temporary workspace changes remain untouched and excluded.
- Local environment update: applied the existing `2026_11_14_000008_grant_helpdesk_records_permission` migration on 2026-11-15 and cleared the Spatie permission/application caches, so company/client default roles receive Ticket Records locally while custom roles remain configurable.

### 2026-11-15 Asia/Colombo - Codex - Ticket Records KPI correction

- Branch: `UI-upgrade-2026-11-14`. Released on this branch without a PR.
- Restored the five top KPI cards on Ticket Records. Only the By Status, By Priority and By Category panels remain removed, matching the clarified request.
- Verification: `npx tsc --noEmit`, production frontend build and `git diff --check` passed. No backend, permission, migration or data changes were made for this correction.

### 2026-11-14 Asia/Colombo - Codex - Client requester status dropdown access

- Branch: `UI-upgrade-2026-11-14`. Released on this branch without a PR.
- Corrected the inline ticket status authorization so the actual `client` requester sees and can use the dropdown on their own ticket. Platform support access remains unchanged; company users and unrelated clients cannot use the narrow status endpoint.
- Files changed: `HelpdeskTicketController.php`, `HelpdeskSecurityTest.php`, `CHANGELOG.md` and this handoff. No migration or data changes.
- Verification: controller PHP syntax clean; `tests/Smoke/HelpdeskSecurityTest.php` passed 28 tests / 161 assertions. Generated assets and `public/hot` are excluded.

### 2026-11-14 Asia/Colombo - Codex - Ticket status below Requester

- Branch: `UI-upgrade-2026-11-14`. Released on this branch without a PR.
- Moved the existing authorized inline status selector into the Requester column of the ticket header, directly below Requester. Created At now stays below Category and assignment information remains below the two-column metadata grid.
- Files changed for this follow-up: `resources/js/pages/helpdesk/tickets/show.tsx`, `CHANGELOG.md` and this handoff. No backend behavior, migrations, permissions or data changed.
- Verification: `npx tsc --noEmit` passed. Generated `public/build`, `public/hot`, storage and unrelated files were not included.

### 2026-11-14 Asia/Colombo - Codex - Helpdesk submenu and Ticket Records permission

- Branch: `UI-upgrade-2026-11-14`. Released on this branch without a PR.
- Company/client navigation now has one Helpdesk parent with `Helpdesk` and `Ticket Records` children. Superadmin Ticket Records also uses the dedicated permission.
- Activated the existing `view-helpdesk-records` permission end to end: menu visibility, server-side route enforcement, permission catalogue/default role setup and future client-role creation. Migration `2026_11_14_000008_grant_helpdesk_records_permission.php` preserves current behavior by granting it to existing company/client roles; administrators can remove it from roles afterward.
- Files changed for this item: company/superadmin menu definitions, `HelpdeskTicketController.php`, `PermissionRoleSeeder.php`, `User.php`, the new permission migration, `HelpdeskSecurityTest.php`, `CHANGELOG.md` and this handoff.
- Verification: migration/controller PHP syntax clean; `tests/Smoke/HelpdeskSecurityTest.php` passed 27 tests / 155 assertions using `phpunit.smoke.xml`; `npx tsc --noEmit` passed; `git diff --check` passed.
- Deployment: back up the database, deploy source, run migrations, clear Spatie permission/application caches, then rebuild frontend assets. No local data migration was run and no generated build/storage files were changed for this item.

### 2026-11-14 Asia/Colombo - Codex - Inline Helpdesk ticket status control

- Branch: `UI-upgrade-2026-11-14`. Released on this branch without a PR.
- Added a support-side-only status selector to the ticket detail page and a narrow `helpdesk-tickets.status` endpoint, so super admins and assigned authorized support agents can change status without opening Edit. Company and customer users cannot call the endpoint.
- Reused one controller lifecycle path for both full edits and inline changes. Status timestamps, status history, resolved pop-ups, status emails and the existing update event remain consistent; selecting the current status is a no-op.
- Files changed for this item: `app/Http/Controllers/HelpdeskTicketController.php`, `routes/web.php`, `resources/js/pages/helpdesk/tickets/show.tsx`, `resources/js/pages/helpdesk/tickets/types.ts`, `tests/Smoke/HelpdeskSecurityTest.php`, `CHANGELOG.md` and this handoff.
- Verification: PHP syntax clean; named route registered; `tests/Smoke/HelpdeskSecurityTest.php` passed 26 tests / 153 assertions using `phpunit.smoke.xml`; `npx tsc --noEmit` passed.
- No migrations, database changes, generated `public/build`, `public/hot`, `tmp` or storage files were created or modified for this item.

### 2026-11-10 Asia/Colombo - Claude - Branch Feature/UI-upgrade-2026-11-10 pushed

- Commit `610f53202` "Helpdesk takeover, salesperson statement and UI upgrades" (78 files) on top of `UI-upgrade-2026-11-09` (`3f1a88a9b`). Pushed to GitHub as `Feature/UI-upgrade-2026-11-10`. No PR.
- Contains Codex Helpdesk Steps 2/4/5/6 and Claude Steps 1/3/7, the role-permission fix, services without SKU/type, Bulk Import tabs and styling, Ticket Records/Agent changes, Journal Entry "Reversed" label, salesperson on invoices and the salesperson Statement, New invoice menu and item button alignment, and the two new smoke tests.
- Excluded: `public/build`, `public/hot`, `tmp/`, storage and all unrelated untracked folders. `origin/main` (`5d4cb305d`) is an ancestor, so it merges cleanly; the only `public/` path vs main is the earlier deletion of `public/hot`.
- Windows note: a local `feature/` ref folder already exists, so the local branch is `feature/UI-upgrade-2026-11-10` (lowercase) tracking `origin/Feature/UI-upgrade-2026-11-10`. Do not create capital `Feature/` branches locally; push with `HEAD:refs/heads/Feature/...` instead.
- Live still needs: backup, migrations 2026_11_09_000001-000004 and 2026_11_10_000005-000007, permission/config cache clear, build, queue worker for `emails`, writable `storage/app/private`.

### 2026-11-10 Asia/Colombo - Claude - Invoice items buttons aligned

- Branch: `UI-upgrade-2026-11-09`. Commit: none; local only.
- `resources/js/pages/Sales/components/InvoiceItemsTable.tsx`: Add New Row / Add Multiple Items row got `px-4` so it lines up with the table cells (which use `px-4`). Shared by Sales invoice create/edit and Sales Proposal create/edit; Purchase uses its own table and is unchanged.
- Verification: `npm run build` passed. Not seen in a browser.

### 2026-11-10 Asia/Colombo - Claude - New invoice menu (Product / Service)

- Branch: `UI-upgrade-2026-11-09`. Commit: none; local only.
- `resources/js/pages/Sales/Index.tsx`: "New invoice" is now a dropdown like Quotations: Product Invoice / Service Invoice -> `sales-invoices.create?type=product|service`.
- `resources/js/pages/Sales/Create.tsx`: reads `?type=service` to start as a service invoice (and its default revenue account); a converted quotation still uses its own type. Follow-up at user request: the Product Wise / Service Wise radio and its `handleTypeChange` were removed (type comes from the menu, like quotations); the page title is now "Create Product Invoice" / "Create Service Invoice". Edit had no switch and is unchanged. Links without `?type` (e.g. the empty-state button) open a product invoice.
- Verification: tsc clean, `npm run build` passed. Not seen in a browser.

### 2026-11-10 Asia/Colombo - Claude - Salesperson on invoices and salesperson Statement

- Branch: `UI-upgrade-2026-11-09`. Commit: none; local only.
- Request (senior, via user): typed/manual lines must not go into Products & Services; they must appear in a statement linked to the salesperson, for quotations and invoices, with full line details.
- Migration `database/migrations/2026_11_10_000007_add_salesperson_to_sales_invoices.php`: nullable `sales_invoices.salesperson_id` -> `quotation_salespersons` (null on delete). Run on local `erp_live_copy` only. No backfill: existing rows untouched.
- Invoices: `SalesInvoice::salesperson()`, fillable; Store/Update requests accept an optional same-company salesperson (active for new invoices); `SalesInvoiceService::create` uses the chosen salesperson or the converted quotation's; `SalesInvoiceController::update` saves it; create/edit and `QuotationController::convertToInvoice` pass `salespersons` (convert also pre-fills the quotation's salesperson). `resources/js/pages/Sales/Create.tsx` and `Edit.tsx`: optional Salesperson select after Customer.
- Statement: `QuotationSalespersonController::show` adds `statement` (every quotation line for the salesperson plus every invoice line whose invoice has that salesperson, or no salesperson and a quotation with it). `Salespersons/Show.tsx` new Statement tab: All/Quotations/Invoices, date range, search, totals in base currency, typed-line badge, CSV export, links to the documents.
- Tests: new `tests/Smoke/SalespersonStatementTest.php` 3/3 (phpunit.smoke.xml, rolls back); related unit/feature tests 26/26. tsc clean, build passed. Not seen in a browser.
- Live: needs migration 000007 with the other pending migrations.
- User browser test 2026-11-10 (Test COMPANY): statement quotation lines, typed invoice line, conversion pre-filling the salesperson, filters/export and moving an invoice from Sample001 to Sample002 all worked. Follow-up: statement search box widened `lg:w-64` -> `lg:w-80` so the placeholder fits.

### 2026-11-10 Asia/Colombo - Claude - Journal Entry report shows Reversed

- Branch: `UI-upgrade-2026-11-09`. Commit: none; local only.
- `packages/workdo/DoubleEntry/src/Resources/js/Pages/Reports/JournalEntry.tsx` and `Reports/Print/JournalEntry.tsx`: entries with status `reversed` (from invoice revert to draft) were labelled "Draft" because the report only knew posted vs anything else. They now show "Reversed" (grey badge on screen). Label only; data was already correct (checked JE-2026-179/180 on `erp_live_copy`: both `reversed`).
- Verification: tsc clean, `npm run build` passed. Not seen in a browser.

### 2026-11-10 Asia/Colombo - Claude - Bulk Import products and services tabs; services without SKU

- Branch: `UI-upgrade-2026-11-09`. Commit: none; local only.
- `resources/js/pages/zoho-migrations/index.tsx`: the Products and services "Enter one by one" form has Products and Services tabs. Products keep the old fields (type Product/Part only). Services show Name, Category, Selling Price, Purchase Price, Tax and Description and are sent with type `service`, no SKU and no unit. `FilePreview` shows All / Products / Services tabs (with counts) when the file has a `type`/`item type`/`product type` column; the Services tab hides SKU and unit columns. It filters the preview rows only.
- `app/Services/BulkImport/Definitions/ProductServiceDefinition.php` (shared with the general Bulk Import): SKU no longer required for type `service`; a service without SKU is identified as `service:<name>` and matched to existing services without SKU by name (case-insensitive); SKU stored as null when empty. Products and parts still require SKU.
- `app/Services/ZohoMigration/ZohoManualPrerequisiteService.php`: in-file and ERP duplicate checks use that identity ("Service name is duplicated / already exists" for SKU-less services); template notes updated.
- Tests: new `test_services_need_no_sku_and_are_matched_by_name_but_products_need_one`; `ZohoManualPrerequisiteImportTest`, `ZohoManualProductUploadTest`, `BulkImportSpreadsheetTest` 17/17 pass (DatabaseTransactions). tsc clean, build passed. Not seen in a browser.

### 2026-11-10 Asia/Colombo - Claude - Bulk Import boxes get rounded corners

- Branch: `UI-upgrade-2026-11-09`. Commit: none; local only.
- `resources/js/pages/zoho-migrations/index.tsx`: class-only change. Create form, metrics strip, progress bar, Import journey and step panel (now two separate rounded cards with a gap), upload box, issue list, manual-entry forms, result boxes, preview tables, Source preview, warnings and notices now use `rounded-lg`/`rounded-xl` with full borders (former top/bottom-only `border-y` panels got full borders so the corners show). Divider lines inside cards are unchanged. No logic change.
- Verification: tsc clean, `npm run build` passed. Not seen in a browser.

### 2026-11-10 Asia/Colombo - Claude - Ticket Records breakdowns in separate cards

- Branch: `UI-upgrade-2026-11-09`. Commit: none; local only.
- `resources/js/pages/helpdesk/records/index.tsx`: By status, By priority and By category (plus By company and By agent on the support side) are now separate cards in a grid instead of columns in one card. Data unchanged.
- Verification: tsc clean, `npm run build` passed. Not seen in a browser.

### 2026-11-10 Asia/Colombo - Claude - Header shows Agent for support staff

- Branch: `UI-upgrade-2026-11-09`. Commit: none; local only.
- `HandleInertiaRequests.php` shares `auth.user.is_platform_support` (from `User::isPlatformSupport()`); `resources/js/components/nav-user.tsx` shows "Agent" (or "Support Manager" for the support-manager role) under the name instead of "staff". Other users still see their type. Display only; no permission change.
- Verification: PHP lint clean, `npm run build` passed. Not seen in a browser.

### 2026-11-10 Asia/Colombo - Claude - Ticket Records first for agents

- Branch: `UI-upgrade-2026-11-09`. Commit: none; local only.
- `resources/js/utils/menus/company-menu.ts`: Ticket Records order 2951 -> 2949 so it sits above Helpdesk (2950). This menu is shared, so company users who see both links also get Ticket Records first.
- `app/Http/Controllers/HomeController.php`: platform support staff (agents and managers) landing on the dashboard now go to `helpdesk-tickets.records` instead of `helpdesk-tickets.index`.
- Verification: `npm run build` passed. Not re-tested in a browser.

### 2026-11-10 Asia/Colombo - Claude - Bulk Import create form uses app dropdowns and date picker

- Branch: `UI-upgrade-2026-11-09`. Commit: none; local only.
- `resources/js/pages/zoho-migrations/index.tsx`: the Create migration workspace form's Workflow, Accounting mode and Zoho date format now use the app `Select` (same as Base currency, posted through a hidden input); Inventory cutover uses the shared `DatePicker` instead of the browser date input, and the form says "Choose the inventory cutover date." when it is empty. Values sent to the server are unchanged (`yyyy-MM-dd` date, same option values, first option is the default as before).
- Verification: tsc clean for the file, `npm run build` passed. Not seen in a browser.

### 2026-11-10 Asia/Colombo - Claude - Services need no Type or SKU

- Branch: `UI-upgrade-2026-11-09`. Commit: none; local only.
- User request: the Create New Service pop-up should not show Type (it is already a service) and services do not need an SKU.
- Changed: `resources/js/components/product-picker-dialog.tsx` (Type hidden when the dialog is locked to services; SKU hidden and sent as null for services; Tax spans the row only in service mode), `packages/workdo/ProductService/src/Resources/js/Pages/Items/Index.tsx` (no SKU column or card line on the Services list), `Items/Create.tsx` and `Items/Edit.tsx` (SKU field and its required check only for products), `Store/UpdateProductServiceItemRequest.php` (`sku` is `nullable|required_unless:type,service`), `ProductServiceItemController.php` (`$validated['sku'] ?? null`). Products still require SKU. The `sku` column was already nullable; existing service SKUs are kept in the database (editing a service keeps its old SKU).
- Verification: tsc clean for the changed files, PHP lint clean, `npm run build` passed. Not seen in a browser.

### 2026-11-10 Asia/Colombo - Claude - Helpdesk Steps 5-7 finished and role escalation closed

- Branch: `UI-upgrade-2026-11-09`.
- Commit: none; changes remain local and are not pushed. Codex's Steps 2, 4, 5 and 6 and Claude's Steps 1 and 3 are all uncommitted in this tree.
- Takeover: Codex stopped mid Step 5 at its usage limit. Its pop-up and queued-email code (Steps 5-6) was reviewed and found complete against the prompt: top-right stacked pop-ups polling every 15 seconds with View and close, recipient-scoped; queued `SendHelpdeskEmail` on the `emails` queue with 3 tries, a delivery log, de-duplication, placeholder-address skipping and no email for internal notes.
- Step 7 Ticket Records: new `helpdesk-tickets.records` route, `HelpdeskTicketController::records`, `resources/js/pages/helpdesk/records/index.tsx`, menu links for the super admin and the company menu. Summary cards, breakdowns by status, priority and category (plus company and agent for the support side), averages for first response and resolution, search, filters, status tabs, server-side sort and paging. The summary follows the filters and is calculated in the database. Each role sees only its own scope. The ticket page now shows status history to everyone who can see the ticket, and assignment history to the support side only.
- Security fix: the role editor accepted any existing permission name, so a company admin could grant `view-all-support-tickets`, `manage-support-team` or internal-note permissions and see other companies' tickets. Store and Update role requests now only accept permissions the user holds, and reject the names `superadmin`, `support-agent` and `support-manager` for non-super-admins. `User::isPlatformSupport()` (staff owned by the super admin with a support role) now replaces every `hasAnyRole(['support-agent', 'support-manager'])` check, and cross-company visibility, the Support Agents page and internal notes also require it.
- Tests run and results: `tests/Smoke/HelpdeskSecurityTest.php` passed 25 tests / 144 assertions (Codex's 18 plus 7 new), run with `phpunit -c phpunit.smoke.xml` on the local copy with rollback. Unit suite 169 tests with the same 5 older failures (Bulk Import, address and project property validation). `npx tsc --noEmit` clean; `npm run build` passed. Whole-site page check result is in the session report.
- Manual verification: NOT seen in a browser; the test login expired. Pop-ups, Ticket Records, the Support Agents page and an agent login need a real browser pass.
- Known issues or blockers: a staff member with `edit-roles` can no longer save a role that holds permissions they lack themselves. Support roles skip the plan module check for every route; their access rests on permissions alone.
- Deployment or rollback notes: back up the database, deploy, run the six `2026_11_09/10` Helpdesk migrations, clear permission, config and application caches, rebuild, and run a queue worker that includes the `emails` queue. `storage/app/private` must be writable and persist. Migration `000003` grants Helpdesk permissions to client roles in every company.
- Files/areas still claimed: none.
- Exact next step: user does the browser pass with separate super admin, agent, company and customer logins, then says when to commit and push.

### 2026-11-10 Asia/Colombo - Codex - Helpdesk Step 4 agents and assignment

- Branch: `UI-upgrade-2026-11-09`; no commit, push or PR.
- Files changed: support-role migration, `SupportAgentController`, Helpdesk ticket policy/controller/model and UI, tenant and plan middleware, dashboard redirect, routes, menus, Support Agents page, Helpdesk smoke tests, changelog and this handoff. Claude's Steps 1/3 and Codex Step 2 remain preserved.
- Database records/migrations affected: local `erp_live_copy` only. `2026_11_09_000004_create_helpdesk_support_roles` was rolled back and reapplied after removing dashboard access from support roles. No live database was touched.
- What was implemented: platform-level Support Agent/Support Manager roles, isolated account creation, assignment/reassignment with history, permission-based internal notes, assigned-only agent scope, a narrow Helpdesk-only tenant-middleware exception, normal-login support accounts, and direct Helpdesk landing instead of ERP dashboard access.
- Decisions and assumptions: support agents are staff owned by the superadmin, never fake companies; ordinary agents see assigned tickets only, managers can see all and manage the team; support roles receive no user impersonation, invoice/customer/settings permissions or dashboard permission.
- Tests run and results: `tests/Smoke/HelpdeskSecurityTest.php` passed 15 tests / 74 assertions; `npx tsc --noEmit` passed. The agent test covers assigned access, internal notes, forbidden ERP permissions, assignment history and access loss after reassignment.
- Manual verification: browser verification remains for the final integrated pass.
- Known issues or blockers: none for Step 4. Pop-up notifications, queued email and Ticket Records remain Steps 5-7.
- Deployment or rollback notes: back up the database, deploy source, run migrations, clear permission/config/application caches and rebuild assets. Rollback application code before reversing migration 000004; doing so removes the two support roles and seven Step 4 permissions, so preserve or reassign any created support accounts first.
- Files/areas still claimed: Step 5 notification files described in Current Claims.
- Exact next step: implement persistent, recipient-scoped Helpdesk notifications with polling and the global top-right stack.

### 2026-11-09 Asia/Colombo - Codex - Helpdesk Step 2 data and access

- Branch: `UI-upgrade-2026-11-09`; no commit or push.
- Preserved Claude's uncommitted Steps 1 and 3. Added requester/agent lifecycle fields, soft deletes, safe foreign keys and sender snapshots, assignment/status history models and tables, one ticket policy/shared visibility scope, requester UI/status options, user/company deletion protection, and client-role Helpdesk access for existing and future companies.
- Local migrations run only on `erp_live_copy`: `2026_11_09_000002_add_helpdesk_lifecycle_and_history` (batch 69) and `2026_11_09_000003_grant_helpdesk_access_to_client_roles` (batch 70). No live database was touched.
- Verification: `tests/Smoke/HelpdeskSecurityTest.php` passed 14 tests / 59 assertions; `npx tsc --noEmit` passed; isolated Vite build to `tmp/helpdesk-step2-build` passed (4,020 modules). Standard `public/build` output was not changed intentionally and failed to clear its pre-existing locked assets with Windows `EPERM`.
- Deployment: back up database/config, deploy source, run migrations, clear permission/config/application caches, rebuild assets and smoke-test superadmin/company/customer scopes. Rollback application code first; migration 000002 is reversible after preserving any new lifecycle/history data. Migration 000003 intentionally does not revoke client access on down to avoid removing independently granted permissions; revoke those six Helpdesk permissions manually only if the product rollback requires it.
- Browser verification remains for the final integrated Helpdesk pass.

### 2026-11-09 Asia/Colombo - Claude - Helpdesk security fixes and private attachments

- Branch: `UI-upgrade-2026-11-09`.
- Commit: none; changes remain local and are not pushed.
- Files changed: `AGENTS.md`, `CHANGELOG.md`, `HelpdeskTicketController.php`, `HelpdeskReplyController.php`, new `HelpdeskAttachmentController.php`, `StoreHelpdeskReplyRequest.php`, `HelpdeskTicket.php`, `HelpdeskReply.php`, new `HelpdeskAttachment.php`, `routes/web.php`, Helpdesk `ReplyForm.tsx`, `ChatMessage.tsx`, `types.ts`, new migration, new `tests/Smoke/HelpdeskSecurityTest.php`.
- Database records/migrations affected: new `2026_11_09_000001_create_helpdesk_attachments_table`, run on local `erp_live_copy` only. Not on live.
- What was implemented: internal notes filtered on the server in `show()` and `today()`; internal notes never emailed; only the super admin can set `is_internal`; closed tickets refuse replies on the server; reply checks the ticket; a mail failure returns JSON success and is logged; user-entered Helpdesk email values are escaped; ticket list sort, direction and per_page limited. New reply attachments go to the private `local` disk through an authorized route, with internal-note files hidden from companies. Allowed types: images, pdf, Office, csv, txt; 5 files, 10 MB each.
- Decisions and assumptions: the media-library picker was removed from the reply form because it creates public files. Old `attachments` JSON stays readable and is not migrated. `/media-file` is unchanged.
- Tests run and results: `HelpdeskSecurityTest` passed 10 tests / 36 assertions through real routes on the local copy with rollback, run with `phpunit -c phpunit.smoke.xml`. See the session report for the other checks.
- Manual verification: NOT seen in a browser; test login expired. The screens and a real upload in the form need user checking.
- Known issues or blockers: NOT fixed here: a customer with ticket permission still sees every ticket in the tenant because there is no requester field; `helpdesk_*.created_by` still cascade on delete; email is still sent during the request; the one existing old attachment stays at a public URL. These belong to the Helpdesk build prompt Steps 2 and 6. That prompt's Steps 1 and 3 are now done, so tell Codex to verify rather than rebuild them.
- Deployment or rollback notes: backup first, run the new migration, rebuild. `storage/app/private` must be writable and persist across deploys. Rollback: revert files and drop `helpdesk_attachments`.
- Files/areas still claimed: none.
- Exact next step: user checks a ticket reply with a file, then says when to commit and push.

### 2026-11-09 Asia/Colombo - Claude - UI upgrade branch published

- Branch: `UI-upgrade-2026-11-09`, created from `New-feature/ui-upgrade-2026-11-07` at `b50e73e8e`. No pull request.
- Commit: single commit on this branch, pushed to origin.
- Files changed: list pages for Sales Invoices, Payment Received, Purchase Invoices, Vendor Payments and Users, the login eye toggle, the Quotations header tooltip fix, and return to Users after editing a client or vendor.
- Database records/migrations affected: none new on this branch. It carries the earlier `terms` and `type` quotation migrations from its parent branch. Not deployed.
- Decisions and assumptions: the Helpdesk work Codex started was reverted at the user's request before branching; no Helpdesk files or migrations are included.
- Manual verification: the rebuilt pages were NOT seen in a browser by Claude because the test login expired. The user reviews them.
- Files/areas still claimed: none.
- Exact next step: user decides whether to merge or deploy.

### 2026-11-08 Asia/Colombo - Claude - Users list redesigned like Customers

- Branch: `New-feature/ui-upgrade-2026-11-07`.
- Commit: none; changes remain local and are not pushed.
- Files changed: `AGENTS.md`, `CHANGELOG.md`, `resources/js/pages/users/index.tsx` (rewritten), `app/Http/Controllers/UserController.php` (index only).
- Database records/migrations affected: none.
- What was implemented: summary cards (Total users, Login enabled, Login disabled), live search on name, email or mobile, sort, per-page, Filters panel (Role, Login status), tabs (All users, Staff, Clients, Vendors, Login disabled) and rows like Customers. Edit stays on the row and a row click opens Edit; Login As User, Change Password, Send Password Reset Link, Change Plan and Delete moved to the three-dot menu with their permission conditions unchanged. Grid view, which was the default, removed. Create, Edit, Change Password and Change Plan popups and the Login History button unchanged.
- Decisions and assumptions: Codex owns security, so no user or password logic changed. Only list-query hardening was added: `sort` was passed straight to `orderBy` and is now a whitelist, direction and per-page are limited. An empty `is_enable_login` used to filter to disabled users; it is now ignored when empty. Super admins see only All users and Login disabled tabs because their list holds companies; this branch was not exercised.
- Tests run and results: `npx tsc --noEmit` no errors in the page; 10 related unit tests / 44 assertions passed; `npm run build` passed. Temporary read-only test on local `erp_live_copy` (deleted afterwards) matched SQL for companies 950 and 664 on every tab and filter.
- Manual verification: NOT seen in a browser, same login limit as the Sales Invoices entry below. Sensitive menu actions were not clicked.
- Follow-up: Edit on a client or vendor row now passes `back=users` and the current Users query. `Customers/Index.tsx` and `Vendors/Index.tsx` return to Users when that popup closes by X, Cancel, Esc or save. Without `back=users` they behave as before. The Customer 360 Edit link is unchanged.
- Known issues or blockers: user needs to view the page.
- Deployment or rollback notes: no migration. Do not commit generated `public/build` or `public/hot`.
- Files/areas still claimed: none.
- Exact next step: user opens the Users page and reports anything off, then says when to commit and push.

### 2026-11-08 Asia/Colombo - Claude - Purchase Invoices and Vendor Payments lists redesigned

- Branch: `New-feature/ui-upgrade-2026-11-07`.
- Commit: none; changes remain local and are not pushed.
- Files changed: `AGENTS.md`, `CHANGELOG.md`, `resources/js/pages/Purchase/Index.tsx` and `packages/workdo/Account/src/Resources/js/Pages/VendorPayments/Index.tsx` (rewritten), `PurchaseInvoiceController.php` and `VendorPaymentController.php` (index), `app/Models/PurchaseInvoice.php`.
- Database records/migrations affected: none.
- What was implemented: same layout as Quotations and Sales Invoices. Purchase Invoices: cards Total invoices, Awaiting payment, Overdue; tabs All invoices, Unpaid, Paid, Overdue; Download, signature and Xero stay on the row; View, Edit, Post, Delete in the three-dot menu. Vendor Payments: cards Total payments, Cleared, Pending; tabs All payments, Cleared, Pending, Cancelled; Download Payment Voucher and Xero stay on the row; View, Mark as Cleared, Cancel Payment, Delete in the menu; View and Create stay popups. Grid views removed.
- Decisions and assumptions: purchase invoices have no type column, so no Product/Service tabs. List Overdue uses posted, partial or overdue status, balance above 0, due before today, same as Sales Invoices; the old status filter missed rows with status `overdue`. `isOverdue()` and `display_status` were not changed. Vendor payment gain/loss colours stay reversed from customer payments and its status update stays POST. Sort and per-page are whitelisted on both.
- Tests run and results: `npx tsc --noEmit` no errors in both pages; 28 related unit tests / 342 assertions passed; `npm run build` passed. Temporary read-only test on local `erp_live_copy` (deleted afterwards) matched SQL for companies 950 and 664 on every tab and filter.
- Manual verification: NOT seen in a browser, same login limit as the Sales Invoices entry below.
- Known issues or blockers: user needs to view the pages.
- Deployment or rollback notes: no migration. Do not commit generated `public/build` or `public/hot`.
- Files/areas still claimed: none.
- Exact next step: user opens both pages and reports anything off, then says when to commit and push.

### 2026-11-08 Asia/Colombo - Claude - Payment Received list redesigned like Quotations

- Branch: `New-feature/ui-upgrade-2026-11-07`.
- Commit: none; changes remain local and are not pushed.
- Files changed: `AGENTS.md`, `CHANGELOG.md`, `packages/workdo/Account/src/Resources/js/Pages/CustomerPayments/Index.tsx` (rewritten), `CustomerPaymentController.php` (index).
- Database records/migrations affected: none.
- What was implemented: summary cards (Total payments, Cleared, Pending), live search, sort, per-page, Filters panel (Customer, Bank Account, Received date), tabs (All payments, Cleared, Pending, Cancelled) and clickable rows that open the existing View popup. Download Receipt and the Xero button stay on the row; View, Mark as Cleared, Cancel Payment and Delete moved to the three-dot menu. New payment still opens the existing Create popup. Exchange gain/loss colours and Available Deposit are kept. Grid view removed.
- Decisions and assumptions: tabs use the existing `status` parameter, so the Status filter moved from the panel to the tabs. Bank Account filter added to the panel because the server already supported it. Sort field and direction are now whitelisted; before, a bad value reached `orderBy`. Payments have no due date, so no Overdue. Bank account shows under the received date and the deposit under the amount.
- Tests run and results: `npx tsc --noEmit` no errors in the page; `npm run build` passed. Temporary read-only test on local `erp_live_copy` (deleted afterwards) matched SQL for companies 950 and 664 on every tab and filter.
- Manual verification: NOT seen in a browser, same login limit as the Sales Invoices entry below.
- Known issues or blockers: user needs to view the page.
- Deployment or rollback notes: no migration. Do not commit generated `public/build` or `public/hot`.
- Files/areas still claimed: none.
- Exact next step: user opens Sales > Payment Received and reports anything off, then says when to commit and push.

### 2026-11-08 Asia/Colombo - Claude - Sales Invoices list redesigned like Quotations

- Branch: `New-feature/ui-upgrade-2026-11-07`.
- Commit: none; changes remain local and are not pushed.
- Files changed: `AGENTS.md`, `CHANGELOG.md`, `resources/js/pages/Sales/Index.tsx` (rewritten), `SalesInvoiceController.php` (index), `app/Models/SalesInvoice.php`, Quotations `Index.tsx`, new `tests/Unit/SalesInvoiceListPageTest.php`.
- Database records/migrations affected: none.
- What was implemented: summary cards (Total invoices, Awaiting payment, Overdue), live search, sort, per-page, Filters panel, tabs (All invoices, Product, Service, Overdue) and clickable rows with Total and Balance. Preview, Download and module buttons (signature, e-invoice, Xero) stay on the row; View, Edit, Post, Duplicate, Delete moved to the three-dot menu. Grid view removed, as on Quotations. Foreign-currency amounts still use `DualCurrencyAmount`.
- Decisions and assumptions: list Overdue means status posted, partial or overdue, balance above 0, due before today. Drafts past due no longer show Overdue on this list. The old status filter missed rows with status `overdue`; it now uses the same rule. `isOverdue()` and `display_status` were not changed, so View and other pages keep their current behaviour. Also restored `TooltipProvider` around the Quotations header buttons: there is no global provider and removing it on 2026-11-07 risked a crash when a module adds a tooltip button.
- Tests run and results: `npx tsc --noEmit` no errors in both pages; 31 related unit tests / 367 assertions passed before the new test; `npm run build` passed. Temporary read-only test on local `erp_live_copy` (deleted afterwards) matched SQL for companies 950 and 664 on all tabs and statuses.
- Manual verification: NOT seen in a browser. The Playwright login expired and no test credential is recorded, so rendering, row click and menus are unverified. Do not reset passwords or mint sessions to get past this.
- Known issues or blockers: user needs to view the page. Row order and status logic on other pages unchanged.
- Deployment or rollback notes: no migration. Do not commit generated `public/build` or `public/hot`.
- Files/areas still claimed: none.
- Exact next step: user opens Sales > Sales Invoices and reports anything off, then says when to commit and push.

### 2026-11-08 Asia/Colombo - Claude - Login password eye toggle

- Branch: `New-feature/ui-upgrade-2026-11-07`.
- Commit: none; changes remain local and are not pushed.
- Files changed: `AGENTS.md`, `resources/js/pages/auth/login.tsx`.
- Database records/migrations affected: none.
- What was implemented: an eye button in the login password field shows and hides the password. Edge's built-in reveal icon is hidden so only one eye appears.
- Tests run and results: `npx tsc --noEmit` no errors in the file; `npm run build` passed.
- Manual verification: browser with a fake password, no login attempted: hidden by default, click shows and keeps the text and stays on the page, second click hides, Tab order is email, password, eye, remember, Log in, Forgot password. No page errors.
- Known issues or blockers: real login not exercised. The toggle cannot submit the form because it is `type="button"`.
- Deployment or rollback notes: no migration. Rollback is this one file.
- Files/areas still claimed: none.
- Exact next step: user checks the login page, then says when to commit and push.

### 2026-11-07 Asia/Colombo - Claude - Local queue worker task disabled

- Branch: `New-feature/ui-upgrade-2026-11-07`. Commit: none; this note is uncommitted.
- Files changed: `AGENTS.md`. Database records/migrations affected: none.
- What was done: at the user's request to stop everything, the Windows scheduled task `Wazely Zoho queue worker` was disabled. It runs every minute and restarted the `zoho-import-redesign` queue worker whenever it was stopped. Its files in `.worktrees` were not touched.
- Known issues or blockers: local Bulk Import jobs on the `imports` queue will not run until the task is enabled again.
- Deployment or rollback notes: local machine only. Rollback: `Enable-ScheduledTask -TaskName 'Wazely Zoho queue worker'`.
- Exact next step: none unless the user needs local Bulk Import.

### 2026-11-07 Asia/Colombo - Claude - UI upgrade branch published

- Branch: `New-feature/ui-upgrade-2026-11-07`, created from `feature/zoho-ui-upgrade-2026-11-04` at `dd3975f7f`. No pull request.
- Commit: single commit on this branch, pushed to origin.
- Files changed: 39 source files. Includes today's Claude UI work and Codex's completed Nov 5 work (Customer 360 statement PDF, Customer Statement template, Salesperson submenu and workspace, quotation Terms), because they share the same quotation files.
- Database records/migrations affected: new `2026_11_05_000001_add_terms_to_sales_quotations_table` and `2026_11_07_000001_add_type_to_sales_quotations_table`. Both ran on local `erp_live_copy` only, not on live.
- Tests run and results: full Unit suite ran 167 tests with 5 failures in `BulkImportRegistryTest` (2), `CustomerAddressValidationTest` (2), `ProjectPropertyInformationValidationTest` (1). No staged file relates to those areas. They were not compared on a clean checkout.
- Known issues or blockers: `public/build` is tracked and shows 1,661 local changes; excluded from the commit. Graphify refresh not completed this session.
- Deployment or rollback notes: backup first, run both migrations, rebuild on the server. Not deployed.
- Files/areas still claimed: none.
- Exact next step: user decides whether to merge or deploy.

### 2026-11-07 Asia/Colombo - Claude - Purchase Invoice details layout

- Branch: `feature/zoho-ui-upgrade-2026-11-04`.
- Commit: none; changes remain local and are not pushed.
- Files changed: `AGENTS.md`, `CHANGELOG.md`, `resources/js/pages/Purchase/Create.tsx`, `resources/js/pages/Purchase/Edit.tsx`.
- Database records/migrations affected: none.
- What was implemented: merged the 4-column and 2-column detail grids into one 4-column grid; Notes is full width. No field, name or behaviour changed.
- Decisions and assumptions: the user's request was read as tidying the layout to match Quotation and Sales Invoice.
- Tests run and results: `TransactionCurrencyLineEntryTest`, `ManualDocumentLineTest`, `LiveSecuritySmokeRegressionTest`, `PurchaseInvoiceProductPickerTest` passed 26 tests / 329 assertions; `npx tsc --noEmit` no errors in Purchase pages; `npm run build` passed.
- Manual verification: Create page measured in the browser at 1536 and 900 wide: fields align in 4 columns and 2 columns, no sideways scroll. Edit received the same change but was not opened, because Test COMPANY has no draft Purchase Invoice.
- Known issues or blockers: none.
- Deployment or rollback notes: no migration. Do not commit generated `public/build` or `public/hot`.
- Files/areas still claimed: none for this task.
- Exact next step: user checks Create Purchase Invoice, then says when to commit and push.

### 2026-11-07 Asia/Colombo - Claude - Default currency removed from Multi-Currency dropdown

- Branch: `feature/zoho-ui-upgrade-2026-11-04`.
- Commit: none; changes remain local and are not pushed.
- Files changed: `AGENTS.md`, `CHANGELOG.md`, `resources/js/components/transaction-currency-fields.tsx`, `tests/Unit/TransactionCurrencyLineEntryTest.php`.
- Database records/migrations affected: none.
- What was implemented: the shared control no longer lists the company default currency, on all 14 forms that use it. This is the only change. Hiding Exchange Rate until a currency was picked was added without being asked, the user rejected it, and the file was restored from git with only the one-line filter re-applied.
- Decisions and assumptions: this reverses the earlier rule that listed every currency, at the user's request, so one test assertion was changed. Do not hide or change Exchange Rate. Turning the switch on without picking a currency saves in the default currency, as it did before.
- Tests run and results: `TransactionCurrencyLineEntryTest` passed 7 tests / 110 assertions; `npx tsc --noEmit` no errors in the component; `npm run build` passed.
- Manual verification: Quotation, Sales Invoice and Purchase Invoice create pages on Test COMPANY: QAR absent from the list, searching Qatar returns no currency, USD selectable, rate appears, switching off hides it. No page errors. Not saved with a foreign currency in this check. The other 11 forms share the component but were not opened.
- Known issues or blockers: if a company changes its default currency later, an older document in the new default shows Select currency in Edit.
- Deployment or rollback notes: no migration. Do not commit generated `public/build` or `public/hot`.
- Files/areas still claimed: none for this task.
- Exact next step: user checks the dropdown, then says when to commit and push.

### 2026-11-07 Asia/Colombo - Claude - Quotations list redesigned like Customers

- Branch: `feature/zoho-ui-upgrade-2026-11-04`.
- Commit: none; changes remain local and are not pushed.
- Files changed: `AGENTS.md`, `CHANGELOG.md`, Quotations `Index.tsx` (rewritten), `QuotationController.php` (index), `SalesQuotation.php`, `tests/Unit/QuotationTypeSplitTest.php`.
- Database records/migrations affected: none beyond the `type` column from the entry below.
- What was implemented: Customers-style page with summary cards (Total, Awaiting response, Overdue), live search, sort and direction, per-page, Filters panel, tabs (All quotations, Product, Service, Overdue) and clickable rows. Preview and Download stay as icons; all other actions moved to a three-dot menu. `New quotation` opens the Product/Service choice.
- Decisions and assumptions: Overdue now means draft, sent or expired, not converted, due date before today; accepted, rejected and converted are finished, so they no longer show Overdue. Product/Service tabs include older quotations by their lines via `scopeOfType`, same rule as `resolvedType()`; mixed and manual-only quotations appear only under All. Grid view was removed because Customers has none. Placeholder `@import.local` emails are hidden. Default page size is 10.
- Tests run and results: `QuotationTypeSplitTest`, `QuotationSalespersonFeatureTest`, `ManualDocumentLineTest` passed 18 tests / 227 assertions before the final two-line tidy; `npx tsc --noEmit` no errors in the quotation pages; `npm run build` passed.
- Manual verification: browser on Test COMPANY against SQL counts: total 479, awaiting 110, overdue 155, Service 478, Product 0, converted 203, search Squad 2. No converted row shows Overdue. Sort, paging, row click, both menus and widths 1280, 1360, 1366, 1536 and 390 checked with no sideways scroll.
- Known issues or blockers: `python -m graphify update .` ran over 40 minutes and was stopped, so the graph is not refreshed for this session's changes.
- Deployment or rollback notes: no extra migration. Do not commit generated `public/build` or `public/hot`.
- Files/areas still claimed: none for this task.
- Exact next step: user checks Sales > Quotations, then says when to commit and push.

### 2026-11-07 Asia/Colombo - Claude - Separate Product and Service quotations

- Branch: `feature/zoho-ui-upgrade-2026-11-04`.
- Commit: none; changes remain local and are not pushed.
- Files changed: `AGENTS.md`, `CHANGELOG.md`, Quotation controller, Store/Update requests, `SalesQuotation` model, new migration `2026_11_07_000001_add_type_to_sales_quotations_table`, Quotations `Index.tsx`/`Create.tsx`/`Edit.tsx`, `Customers/Workspace.tsx`, `product-picker-dialog.tsx`, new `tests/Unit/QuotationTypeSplitTest.php`.
- Database records/migrations affected: new nullable `sales_quotations.type`, run on local `erp_live_copy`. Existing rows are not changed; all 975 stay NULL.
- What was implemented: the Quotations + now opens Product Quotation / Service Quotation (also the empty-state button and Customer 360). The type is saved on the quotation and fixes the item dropdown and Add Multiple Items list. Service quotations hide Warehouse, confirmed by the user. Salesperson sits directly after Customer on create and edit. On Create Quotation, Notes and Terms sit side by side left of Quotation Summary, each the Description box size (270 x 100), the layout the user chose. Store/Update reject lines of the wrong type. Convert to Invoice uses the saved type. Edit now sends `terms`, which was being blanked on every edit.
- Decisions and assumptions: no backfill, so no production data changes. Older quotations get their type from their catalog lines at runtime; mixed or manual-only ones keep the full list. Product means `type != service`, matching the Products page, so Parts are included. Manual lines stay allowed on both types. The type cannot be changed after creation.
- Tests run and results: `npx tsc --noEmit` no errors in touched files; PHP syntax passed; `QuotationSalespersonFeatureTest`, `ManualDocumentLineTest`, `PurchaseInvoiceProductPickerTest`, `ProductServiceCatalogSplitTest`, `TransactionCurrencyLineEntryTest` passed 32 tests / 435 assertions before the new test; `QuotationTypeSplitTest` + `QuotationSalespersonFeatureTest` passed 11 tests / 109 assertions; `npm run build` passed.
- Manual verification: browser on Test COMPANY. + menu shows both options; Service list 18 services, Product list only the temporary product. Wrong-type lines and missing type returned 422; both types saved; Edit kept the type; Update through the page succeeded; Convert returned the right invoice type, including manual-only Service; legacy EST-000470 opened as Service. Temporary product and 3 quotations were deleted through the app; all 13 product-linked tables show 0 rows and 975 quotations remain.
- Known issues or blockers: Edit Quotation has no visible Terms box, so Terms can only be set on create. Create ignores `customer_id` in the URL, so Customer 360 does not preselect the customer (already the case).
- Deployment or rollback notes: run migrations for BOTH `2026_11_05_000001_add_terms_to_sales_quotations_table` and this one, then rebuild. Take a phpMyAdmin backup first. Rollback: revert the files and drop the nullable column. Do not commit generated `public/build` or `public/hot`.
- Files/areas still claimed: none for this task.
- Exact next step: user tests Quotations > + locally, then says when to commit and push.

### 2026-11-07 Asia/Colombo - Claude - Product picker layout and Products/Services create popup

- Branch: `feature/zoho-ui-upgrade-2026-11-04`.
- Commit: none; changes remain local and are not pushed.
- Files changed: `AGENTS.md`, `resources/js/components/product-picker-dialog.tsx`, ProductService `ProductServiceItemController.php`, `Pages/Items/Index.tsx`, `Pages/Items/types.ts`.
- Database records/migrations affected: none.
- What was implemented: Add Products popup (Sales Invoice, Purchase Invoice, Quotation) got search/table spacing and no sideways scroll. The + on Products and Services now opens the shared create form in a new `createOnly` mode instead of a separate page. Tax moved next to Opening Stock, above Description, and the create form was tightened so it fits without scrolling.
- Decisions and assumptions: `createOnly` allows Opening Stock 0 and hides Part. Image and long description stay on the Edit page. The layout change also applies to the create form inside invoices and quotations because the component is shared.
- Tests run and results: `ProductServiceCatalogSplitTest` + `PurchaseInvoiceProductPickerTest` passed 12 tests / 125 assertions; `npm run build` passed.
- Manual verification: real popup measured in the browser. Products needs 648px and fits at 1536x730, 1366x768 and 1920x950; Services needs 560px. At 1366x650 Products still scrolls (585px available). The Create save was not clicked, to avoid adding test data.
- Known issues or blockers: Part chosen in the invoice version of the popup probably saves as Product (existing behaviour, unchanged, not tested).
- Deployment or rollback notes: no migration; do not commit generated `public/build` or `public/hot`. Rollback is limited to the files above.
- Files/areas still claimed: none for this task.
- Exact next step: user creates one product and one service locally, then says when to commit and push.

### 2026-11-05 Asia/Colombo - Codex - Salesperson department workspace

- Branch: `feature/zoho-ui-upgrade-2026-11-04`.
- Commit: none; changes remain local and are not pushed.
- Files changed: `AGENTS.md`, `CHANGELOG.md`, Quotation salesperson routes/controller, Salesperson directory and new `Salespersons/Show.tsx`, plus `tests/Unit/QuotationSalespersonFeatureTest.php`.
- Database records/migrations affected: none. Existing salesperson, quotation, converted-invoice and payment-allocation relationships are read only.
- What was implemented: clicking a salesperson opens a tenant-scoped salesperson workspace with Overview, Quotations and Payments tabs; salesperson-specific quoted, converted, collected and outstanding totals; quotation status breakdown; recent/all quotation lists; payment attribution rows; direct quotation navigation; and separate invoice-number navigation to the sales invoice. Recent rows use a compact complete layout so invoice details are not clipped. All visible department wording was removed at the user's request.
- Decisions and assumptions: salesperson records represent departments; payments are attributed only through allocations on invoices converted from quotations assigned to that department; customer-only concepts such as portal access, receivables aging and customer contact snapshots are intentionally omitted.
- Tests run and results: PHP syntax passed for the quotation controller, requests, and Terms migration; `QuotationSalespersonFeatureTest` passed 7 tests / 88 assertions after invoice drill-through, Create Quotation layout, and template-prefill coverage were added; `npx tsc --noEmit` passed; `npm run build` passed with 4,020 modules; migration `2026_11_05_000001_add_terms_to_sales_quotations_table` ran successfully on the local database; route listing confirmed all six salesperson routes; focused `git diff --check` passed.
- Manual verification: local Laravel and Vite servers are running; authenticated browser refresh and data review remain for the user.
- Known issues or blockers: historical quotations without a salesperson are intentionally excluded until assigned; payments on unrelated invoices cannot be attributed to a department.
- Deployment or rollback notes: no migration is required; do not commit generated `public/build` or `public/hot`. Rollback is limited to the files above.
- Files/areas still claimed: none for this task.
- Exact next step: user opens Sales > Salesperson, clicks a department, checks quotation/payment totals, and opens a quotation row.

### 2026-11-05 Asia/Colombo - Codex - Salesperson submenu and required quotation assignment

- Branch: `feature/zoho-ui-upgrade-2026-11-04`.
- Commit: none; changes remain local and are not pushed.
- Files changed: `AGENTS.md`, `CHANGELOG.md`, Quotation salesperson controller/routes/menu/management page, quotation create/update requests, shared salesperson field, and `tests/Unit/QuotationSalespersonFeatureTest.php`.
- Database records/migrations affected: none. The existing `quotation_salespersons` table and `sales_quotations.salesperson_id` relationship are reused.
- What was implemented: added Sales > Salesperson beneath Quotations; added a tenant-scoped Customer-directory-style management page with salesperson search, All Salespersons/Active/Inactive tabs, responsive rows and add/edit/activate/inactivate/delete actions without summary cards; made an active tenant-owned salesperson mandatory when creating or updating a quotation; removed the optional No Salesperson choice from the quotation form; corrected the Inertia package prefix after browser testing exposed that the submenu route could not resolve its page.
- Decisions and assumptions: the submenu uses the existing `manage-quotations` permission instead of introducing a new permission; historical quotations are not changed, but an old quotation without an active salesperson must select one before it can be saved again.
- Tests run and results: PHP syntax passed for the controller, create/update requests and focused test; `QuotationSalespersonFeatureTest` passed 5 tests / 50 assertions after the directory redesign; `npx tsc --noEmit` passed; `npm run build` passed with 4,019 modules before the final UI-only redesign; focused `git diff --check` passed; `artisan route:list --path=quotation-salespersons` confirmed all five routes.
- Manual verification: source, menu and route contracts verified. Authenticated browser verification of the submenu, add flow and required-field error remains for the user.
- Known issues or blockers: none.
- Deployment or rollback notes: no migration is required; do not commit generated `public/build` or `public/hot`. Rollback is limited to the files listed above.
- Files/areas still claimed: none for this task.
- Exact next step: user opens Sales > Salesperson, adds an active salesperson, then confirms a quotation cannot be created or updated without selecting one.

### 2026-11-05 Asia/Colombo - Codex - Branded Customer Statement template and PDF

- Branch: `feature/zoho-ui-upgrade-2026-11-04`.
- Commit: none; changes remain local and are not pushed.
- Files changed: `AGENTS.md`, `CHANGELOG.md`, `app/Models/DocumentTemplate.php`, `app/Services/DocumentTemplates/DocumentTemplateService.php`, Customer Workspace controller/service/routes/UI, new `StatementPrint.tsx`, document-template preview/form/index/types, and `tests/Feature/CustomerWorkspaceContractTest.php`.
- Database records/migrations affected: none. The first statement PDF request lazily creates the company's default Customer Statement template through the existing document-template service.
- What was implemented: added a fourth `statement` template type; added its Templates filter/form option, statement-column controls and live preview; added a branded A4 statement renderer with customer and period details, opening balance, running debit/credit rows, closing balance, watermark and contact footer; replaced both Customer 360 PDF actions with a dedicated filtered statement route that downloads the rendered template.
- Decisions and assumptions: Customer Statement is separate from the legacy Customer Detail Report; CSV behavior is unchanged; the PDF uses company base currency and preserves start/end date filters; existing invoice, quotation and payment template behavior remains unchanged.
- Tests run and results: PHP syntax passed for all changed PHP files; `CustomerWorkspaceContractTest` passed 9 tests / 76 assertions under PHP 8.5 with a 1 GB test memory limit; `npx tsc --noEmit` passed; `npm run build` passed with 4,018 modules; `git diff --check` passed excluding generated `public/build` and `public/hot`.
- Manual verification: source and route contract verified; the production bundle contains the new StatementPrint and updated Workspace/template assets. Authenticated browser download should be checked with a real customer and both an unfiltered and date-filtered statement.
- Known issues or blockers: none in implementation. The default local PHP memory limit of 128 MB is too low for PHPUnit preload, so the focused test was run directly with `-d memory_limit=1G`.
- Deployment or rollback notes: do not commit generated `public/build` or `public/hot`; no migration is required. Rollback is limited to the files above and restores the legacy customer-detail PDF action.
- Files/areas still claimed: none for this task.
- Exact next step: user tests Customer 360 > Statement > PDF and optionally customizes Templates > Customer Statement before deployment.

Add newest entries first. Do not remove earlier entries.

### 2026-11-05 Asia/Colombo - Codex - Customer 360 statement PDF download

- Branch: `feature/zoho-ui-upgrade-2026-11-04`; changes are locally verified and are not committed or pushed in this handoff.
- Scope: Customer 360 Statement-tab PDF and overflow Download statement actions now call the existing customer-detail PDF generator with `download=pdf` and preserve the selected statement start/end dates.
- Root cause: Customer 360 opened the print route without the download query flag, so live correctly rendered only the PDF preview page while the legacy customer report already used the working download flag.
- Verification: `CustomerWorkspaceContractTest` passed 8 tests / 64 assertions; `npx tsc --noEmit` passed; targeted `git diff --check` passed.
- Exclusions: generated `public/build`, `public/hot`, local server logs and unrelated workspace files remain excluded.

### 2026-11-04 Asia/Colombo - Codex - Guarded invoice revert to draft

- Branch: `feature/zoho-ui-upgrade-2026-11-04`; changes are committed locally and are not pushed in this handoff.
- Scope: Sales and Purchase Invoice Revert to Draft actions with required reasons, dedicated permissions, tenant checks, dependency blocking, reversal journals, warehouse stock reversal, status history, shared confirmation UI, and transaction-safe Purchase posting.
- Accounting behavior: original and generated reversal journals are retained with `reversed` status, cached account balances are neutralized, and reposting creates a fresh active journal set. Sales product COGS, sales/service revenue and purchase journals are all covered.
- Guardrails: only `posted` invoices qualify; payment allocations, credit/vendor-credit links and sales/purchase returns block reversion. Product sales without a warehouse snapshot and purchase bills whose stock has been consumed are also blocked.
- Verification: PHP syntax passed; both routes resolve; focused PHPUnit passed 19 tests / 255 assertions on PHP 8.5.7; TypeScript and production Vite build passed with 4,017 modules transformed; local `/login` returns HTTP 200.
- Database: migration `2026_11_04_000002_add_invoice_revert_to_draft` was cleanly rolled back and reapplied locally (batch 65). Production still requires `php artisan migrate --force`.
- Exclusions: generated `public/build`, `public/hot`, server logs and unrelated workspace files are intentionally excluded.

### 2026-11-04 Asia/Colombo - Codex - Customer 360 document UX release branch

- Branch: `feature/zoho-ui-upgrade-2026-11-04`, created from Aadil's Customer 360 tip `c26514fdd` with the verified local document UX work applied on top.
- Scope: Sales Invoice, Purchase Invoice and Quotation manual/catalog line entry, shared tax management, quotation salesperson management, multi-currency controls, supporting migrations, focused tests, changelog and coordination records.
- Verification before packaging: focused PHPUnit passed 23 tests / 325 assertions; TypeScript and the production Vite build passed with 4,015 modules transformed; local `/login` returned HTTP 200.
- Database: local-only migrations for manual document lines, Product/Service permission split, quotation salespersons and Customer 360 activity logs are applied. Production requires normal migration deployment.
- Exclusions: generated `public/build`, `public/hot`, local scratch folders, the unrelated Medical Production Evidence architecture note, and all other unrelated untracked files.
- Delivery: commit and push this branch directly without creating a pull request. No production deployment is included.

### 2026-11-04 Asia/Colombo - Codex - Customer 360 local integration recovery

- Branch: `feature/customer-360-ui-upgrade-2026-11-03`, tracking Aadil's remote branch at `c26514fdd`; no commit or push.
- Existing document UX changes remain uncommitted on top of Aadil's branch. Generated `public/build` remains local-only.
- Installed Aadil's declared font dependencies, rebuilt the complete frontend successfully, confirmed the bundle contains Sales and Product/Service menu permissions, and restarted Laravel at `http://127.0.0.1:8001`.
- Local database only: ran Aadil's pending `2026_11_03_000001_create_customer_activity_logs_table` migration. Product/service permission and document migrations were already applied.
- Aadil's branch already descends from release commit `e8db2fb50`, so the committed ERP changes were present; the visible menu problem came from a stopped server and stale bundle after the branch switch, not missing commits.
- Verification: production frontend build passed with 4,015 modules; focused document suite passed 23 tests / 325 assertions; `/login` on port 8001 returns HTTP 200. The browser automation bridge failed, so the authenticated sidebar still needs a manual hard refresh check.
- No GitHub or production changes. Do not commit generated build files. Next: hard-refresh the authenticated local page and verify Sales and Product/Service menus.

### 2026-11-04 Asia/Colombo - Codex - Shared tax create-only behavior

- Branch: `release/zoho-and-security`; no PR, commit, or push; retained within the active local document UX claim for user verification.
- Removed Save and Select from the shared tax manager at the user's request. Creating a tax now uses Save, adds it to the shared catalogue without changing the active Quotation, Sales Invoice, or Purchase Invoice line, and leaves explicit selection to the user. Editing a selected tax still refreshes that line's tax snapshot.
- Verification: focused PHPUnit passed 9 tests / 138 assertions; TypeScript and production Vite build passed with 4,007 modules transformed. Generated `public/build` remains excluded from commits.

### 2026-11-04 Asia/Colombo - Codex - Purchase Invoice tax manager parity

- Branch: `release/zoho-and-security`; no PR, commit, or push; retained within the active local document UX claim for user verification.
- Purchase Invoice create/edit now use the same shared searchable TaxField and permission-controlled Manage Taxes dialog as Sales Invoice and Quotation. New and cleared lines visibly default to No Tax, selecting a catalog item preserves its configured tax, and tax overrides recalculate line totals immediately.
- Verification: focused PHPUnit passed 9 tests / 130 assertions; TypeScript and production Vite build passed with 4,007 modules transformed. Generated `public/build` remains excluded from commits.

### 2026-11-04 Asia/Colombo - Codex - Sales Invoice tax manager parity

- Branch: `release/zoho-and-security`; no PR, commit, or push; retained within the active local document UX claim for user verification.
- Sales Invoice create/edit now use the same shared searchable TaxField and permission-controlled Manage Taxes dialog as Quotation. Create, edit and delete actions mutate the shared Product & Service tax catalogue and update every Sales Invoice row immediately; No Tax remains the explicit default.
- Verification: focused PHPUnit passed 9 tests / 117 assertions; TypeScript and production Vite build passed with 4,007 modules transformed. Generated `public/build` remains excluded from commits. Local PHP server restarted on port 8001 and `/login` returned HTTP 200.

### 2026-11-04 Asia/Colombo - Codex - Sales Invoice No Tax default

- Branch: `release/zoho-and-security`; no PR, commit, or push; retained within the active local document UX claim for user verification.
- Fixed new and manually cleared Sales Invoice rows mapping their empty tax state to the explicit `none` option, so the selector visibly reads No Tax instead of rendering blank.
- Verification: focused PHPUnit passed 6 tests / 84 assertions; TypeScript and production Vite build passed with 4,006 modules transformed. Generated `public/build` remains excluded from commits. Local PHP server restarted on port 8001 and `/login` returned HTTP 200.

### 2026-11-04 Asia/Colombo - Codex - Shared quotation tax manager

- Branch: `release/zoho-and-security`; no PR, commit, or push; retained within the active local document UX claim for user verification.
- Quotation tax fields now visibly default to No Tax and use a searchable selector with a permission-controlled Manage Taxes dialog. Users with the corresponding Product & Service tax permissions can create, edit and delete the tenant's shared taxes; changes update every quotation row immediately and are available to Product/Service forms from the same catalogue.
- Product and Service create/edit forms now label their empty tax option No Tax instead of `_`. Quotation users receive tenant tax options independently from legacy product-item permissions, while categories and units remain catalog-permission scoped.
- Security/compatibility: inline tax CRUD returns JSON only when requested; existing System Setup redirects remain intact. Tax update/delete now enforce tenant ownership. Historical document-line tax snapshots are not removed when a catalogue tax is deleted.
- Verification: PHP syntax checks passed; focused PHPUnit passed 9 tests / 107 assertions; TypeScript and production Vite build passed with 4,006 modules transformed. Generated `public/build` remains excluded from commits. Local PHP server restarted on port 8001 and `/login` returned HTTP 200.

### 2026-11-04 Asia/Colombo - Codex - Quotation editable tax parity

- Branch: `release/zoho-and-security`; no PR, commit, or push; retained within the active local document UX claim for user verification.
- Quotation create/edit lines now receive the tenant tax catalogue and render the same editable tax selector as Sales Invoice. Saved-item selection applies its configured tax; users can change or remove it, and line/summary amounts recalculate immediately. Item names and descriptions remain editable for saved and manual lines.
- Verification: focused PHPUnit passed 9 tests / 95 assertions; TypeScript and production Vite build passed with 4,005 modules transformed. Generated `public/build` remains excluded from commits. Local PHP server restarted on port 8001 after the build; `/login` returned HTTP 200.

### 2026-11-04 Asia/Colombo - Codex - Manual Sales Invoice item-name persistence

- Branch: `release/zoho-and-security`; no PR, commit, or push; kept within the active local document UX claim for user verification.
- Fixed the Sales Invoice create service so catalog-optional lines persist their typed `item_name`, accept a null `product_id`, and skip catalog-description synchronization when no saved product was selected. The existing edit, view, print and document-template fallbacks remain unchanged.
- Deployment dependency: migration `2026_11_03_000001_allow_manual_document_line_items` provides the nullable product relation and `item_name` columns; it is already applied in the local database and must run in production with the rest of the pending migrations.
- Verification: PHP syntax checks passed; focused PHPUnit passed 5 tests / 60 assertions; migration status confirms `2026_11_03_000001_allow_manual_document_line_items` is applied locally. No frontend rebuild was required for this backend-only follow-up, and generated `public/build` remains excluded.

### 2026-11-04 Asia/Colombo - Codex - Quotation salesperson and multi-currency switch repair

- Branch: `release/zoho-and-security`; no PR and no push; local user verification pending.
- Multi-currency: repaired the shared switch geometry and state handling. New documents initialize once to the company base currency with the switch off; user activation is no longer immediately reversed; existing foreign-currency edits can still initialize enabled.
- Quotations: added a tenant-scoped salesperson table and optional quotation relationship. Create/edit forms place Salesperson on the second details row beside Template and provide a searchable Zoho-style manager with blank create fields, populated edit fields, active/inactive and delete actions, and hover-only action controls.
- Follow-up: explicitly mapped `QuotationSalesperson` to `quotation_salespersons`; Laravel otherwise pluralizes the class to the nonexistent `quotation_salespeople` table.
- Deployment: applied `2026_11_04_000001_add_quotation_salespersons` locally. Production deployment requires `php artisan migrate --force`.
- Verification: PHP syntax checks passed; focused PHPUnit passed 8 tests / 72 assertions; TypeScript and the production Vite build passed with 4,005 modules transformed before the final one-time base-currency initialization adjustment. A final rebuild follows this entry. Generated `public/build` remains excluded from commits.

### 2026-11-03 Asia/Colombo - Codex - Zoho-style document item selection

- Branch: `release/zoho-and-security`; no PR; locally verified and ready to commit and push.
- Scope: Sales Invoice, Purchase Invoice and Quotation create/edit only. Their line-item selectors are now searchable popovers showing item name and transaction-currency rate, with selected-item highlighting and a fixed Add New Item footer.
- Actions: removed the top Add Product/Add Service button. Added Add New Row and Add Multiple Items beneath each item table; Add Multiple Items opens the existing bulk browser.
- Quick create: removed creation controls from the bulk browser. Add New Item in the row selector opens the existing create form directly, submits through the permission-controlled Product or Service route, refreshes the available items and immediately adds the created item to the document.
- Preserved behavior: existing line calculations, descriptions, quantity, stock, account, discount, tax, currency conversion, totals and bulk selection behavior are unchanged.
- Verification: PHP 8.5 PHPUnit passed 14 tests / 185 assertions (`PurchaseInvoiceProductPickerTest`, `TransactionCurrencyLineEntryTest`); `npm run build` passed with 4,004 modules transformed; generated `public/build` output remains excluded. Browser verification was deferred because the local PHP child process stopped during stale-process cleanup and is restarted separately.

### 2026-11-03 Asia/Colombo - Codex - Multi-currency pill default-off correction

- Branch: `release/zoho-and-security`; no PR; locally verified and ready to commit and push.
- Fixed: the shared Multi-Currency Transaction control now resolves the company base currency from both `companyAllSetting` and `settings` page payloads. New QAR quotations therefore remain off instead of being compared with a USD fallback and incorrectly appearing active.
- Behavior preserved: new base-currency Sales Invoices, Purchase Invoices and Quotations start collapsed/off. Editing an existing document still opens the pill only when its saved transaction currency is genuinely different from its base currency.
- Verification: PHP 8.5 PHPUnit passed 13 tests / 162 assertions (`TransactionCurrencyLineEntryTest`, `PurchaseInvoiceProductPickerTest`); `npm run build` passed with 4,001 modules transformed. Generated `public/build` output remains excluded.

### 2026-11-03 Asia/Colombo - Codex - Product and Service catalog split

- Branch: `release/zoho-and-security`; no PR; locally verified and ready to commit and push.
- Navigation and forms: replaced the single Items submenu with Products and Services. Their list, create, view and edit flows are type-locked by route, and the Item Type dropdown is removed. Existing Part records remain visible under Products and retain their stored type when edited.
- Permissions: added independent manage, own/all scope, view, create, edit and delete permissions for Products and Services. The deployment migration copies each role's matching legacy item grants to both new permission families so current access is preserved; future role edits can grant either catalog independently.
- Compatibility: retained the legacy Items routes for existing integrations and links. Product and Service pages use matching titles, actions, empty states and route destinations.
- Deployment: run `php artisan migrate --force` before testing role access so the new permissions exist and existing grants are copied.
- Verification: PHP 8.5 PHPUnit passed 11 tests / 100 assertions (`ProductServiceCatalogSplitTest`, `PurchaseInvoiceProductPickerTest`); PHP syntax checks passed for the controller and migration; the earlier production frontend build passed; Git whitespace validation passed. Generated `public/build`, `public/hot`, `public/storage`, and unrelated `tmp` files remain excluded.

### 2026-11-03 Asia/Colombo - Codex - Multi-currency pill toggle

- Branch: `release/zoho-and-security`; no PR; locally verified and ready to commit and push.
- Changed: replaced the shared Multi-Currency Transaction checkbox and external label with one rounded pill button. The inactive state is neutrally outlined; the active state uses the primary blue treatment and a filled state indicator.
- Accessibility and behavior: the pill uses `aria-pressed`, remains keyboard-operable and disabled-aware, and calls the existing toggle path. Expanding, collapsing, currency reset and exchange-rate behavior are unchanged across Sales Invoice, Purchase Invoice and Quotation create/edit forms.
- Verification: PHP 8.5 PHPUnit passed 13 tests / 161 assertions (`TransactionCurrencyLineEntryTest`, `PurchaseInvoiceProductPickerTest`); `npm run build` passed with 4,003 modules transformed; Git's whitespace check passed with `cr-at-eol` for the existing CRLF shared component. Generated `public/build` output remains excluded.

### 2026-11-03 Asia/Colombo - Codex - Multi-currency controls inside document details

- Branch: `release/zoho-and-security`; no PR; locally verified and ready to commit and push.
- Layout: moved the Multi-Currency Transaction controls from separate cards into the bottom of the upper details card on Sales Invoice, Purchase Invoice and Quotation create/edit forms. A divider and balanced spacing keep the currency controls visually distinct without creating another page section.
- Behavior preserved: the collapsed state remains compact; enabling multi-currency expands the same upper details card to show Currency, Exchange Rate and the base-currency equivalent. Currency selection, legacy-document handling, calculations and submission behavior are unchanged.
- Verification: PHP 8.5 PHPUnit passed 12 tests / 155 assertions (`TransactionCurrencyLineEntryTest`, `PurchaseInvoiceProductPickerTest`); `npm run build` passed with 4,003 modules transformed. Generated `public/build` output remains excluded.

### 2026-11-03 Asia/Colombo - Codex - Multi-currency edit state and document-card parity

- Branch: `release/zoho-and-security`; no PR; locally verified and ready to commit and push.
- Legacy edit behavior: Sales Invoice, Purchase Invoice and Quotation records without a saved `base_currency_code` now use the current company base currency. Old QAR documents therefore open with multi-currency off and a 1.00 rate instead of incorrectly appearing as USD transactions.
- Currency picker: the company base currency remains searchable while multi-currency is open; selecting it restores a 1.00 rate and collapses multi-currency mode, eliminating the `No currency found` dead end.
- Layout: Sales Invoice and Quotation create/edit now use the same separate white expandable multi-currency card already used by Purchase Invoice.
- Verification: PHP 8.5 PHPUnit passed 12 tests / 139 assertions (`TransactionCurrencyLineEntryTest`, `PurchaseInvoiceProductPickerTest`); `npm run build` passed; Git's whitespace check passed with `cr-at-eol` for the existing CRLF shared component; generated `public/build` output remains excluded.

### 2026-11-02 Asia/Colombo - Codex - Product search icon and currency-control spacing

- Branch: `release/zoho-and-security`; no PR; locally verified and ready to commit and push.
- Product picker: replaced the absolutely positioned search icon with a single bordered flex input shell, keeping the icon inside the field at every width. This shared popup change applies to Sales Invoice and Purchase Invoice create/edit.
- Sales Invoice: added balanced vertical padding around the Multi-Currency Transaction control on create and edit so it no longer touches the Template or Revenue Category sections.
- Verification: `PurchaseInvoiceProductPickerTest` passed 6 tests / 48 assertions on PHP 8.5; `npm run build` passed; `git diff --check` passed. Generated `public/build` output remains excluded.

### 2026-11-02 Asia/Colombo - Codex - Add Products category-filter removal

- Branch: `release/zoho-and-security`; no PR; locally verified and ready to commit and push.
- Changed: removed the `All Categories` dropdown and category-based filtering from the shared Add Products browser. Product name/SKU search now spans the full dialog width.
- Scope: the shared browser is used by Sales Invoice and Purchase Invoice create/edit screens, so all four occurrences receive the change. Quotation does not currently use this exact popup and was not changed.
- Preserved: the Category field remains in the permission-controlled Create New Product form because saved products still require category assignment.
- Verification: `PurchaseInvoiceProductPickerTest` passed 5 tests / 42 assertions on PHP 8.5; `npm run build` passed; `git diff --check` passed. Generated `public/build` output remains excluded.

### 2026-11-02 Asia/Colombo - Codex - Direct transaction-currency line entry

- Branch: `release/zoho-and-security`; no PR; locally verified and ready to commit and push.
- Changed: Sales Invoice, Purchase Invoice and Quotation create/edit line-item headings dynamically show the selected currency symbol, such as `$`, `€` or the configured QAR symbol. Unit prices, fixed discounts, line totals and summaries are entered and calculated directly in the selected transaction currency.
- Conversion behavior: catalogue prices are stored in company currency and converted when inserted into a foreign-currency document; the document base equivalent is calculated as transaction total multiplied by the entered exchange rate. Editing an existing foreign document keeps its saved transaction-currency line values unchanged.
- Compatibility: current forms no longer send the legacy base-line flag. Sales and Purchase server paths still honor an explicit `line_amounts_are_base_currency` flag for older clients, while direct transaction amounts are now the default. Quotation-to-invoice conversion no longer applies a second line conversion.
- Verification: PHP 8.5 PHPUnit passed 17 tests / 115 assertions (`TransactionCurrencyLineEntryTest`, `CurrencyConversionServiceTest`, `PurchaseInvoiceProductPickerTest`, `LiveSecuritySmokeRegressionTest`); `npm run build` passed; changed PHP files passed syntax checks; `git diff --check` passed. Generated `public/build` output remains excluded.

### 2026-11-02 Asia/Colombo - Codex - Invoice and quotation Unit Price heading

- Branch: `release/zoho-and-security`; no PR; locally verified and ready to commit and push.
- Changed: Sales Invoice and Purchase Invoice create/edit line-item tables now show `Unit Price` without appending the base-currency code. Quotation create/edit already used the requested label and was verified unchanged.
- Scope: only these three document flows were audited; returns, purchase orders, credits, retainers, POS and other modules were not changed. Currency conversion, amount formatting and submission behavior remain unchanged.
- Verification: focused source search confirmed all three shared item-table headings render `Unit Price`; `npm run build` passed. Generated `public/build` output remains excluded.

### 2026-11-02 Asia/Colombo - Codex - Purchase Invoice multi-currency card

- Branch: `release/zoho-and-security`; no PR; locally verified and ready to commit and push.
- Changed: the Purchase Invoice multi-currency component is contained in a dedicated white card on both create and edit pages, aligned with the surrounding page sections.
- Behavior preserved: the collapsed card shows the Multi-Currency Transaction control; enabling it expands the same card to show currency and exchange-rate fields. Currency calculation and submission logic are unchanged.
- Verification: `PurchaseInvoiceProductPickerTest` passed 4 tests / 36 assertions on PHP 8.5; `npm run build` passed; `git diff --check` passed. Generated `public/build` output remains excluded.

### 2026-11-02 Asia/Colombo - Codex - Purchase Invoice product picker parity

- Branch: `release/zoho-and-security`; no PR; locally verified and ready to commit and push.
- Added: Purchase Invoice create and edit use the shared Sales Invoice product browser with search, category filtering, multi-select, permission-controlled quick product creation, and immediate insertion into invoice lines.
- Purchase behavior: the picker displays and applies purchase prices, carries descriptions/taxes/units, and permits out-of-stock products because purchases replenish stock. Sales Invoice retains its existing selling-price display and out-of-stock restriction.
- Accounting boundary: newly selected lines leave Account blank for explicit user selection; no expense account is guessed from product data.
- Verification: `PurchaseInvoiceProductPickerTest` and `LiveSecuritySmokeRegressionTest` passed 9 tests / 56 assertions on PHP 8.5; PurchaseInvoiceController syntax passed; `npm run build` passed; `git diff --check` passed. Generated `public/build` output remains excluded.

### 2026-11-02 Asia/Colombo - Codex - Vendor Payment submission follow-up

- Branch: `release/zoho-and-security`; no PR; locally verified and ready to commit and push.
- Root cause: choosing a vendor with a preferred currency replaced the valid base-currency rate with an empty rate. Base-currency forms hide the rate control, so both full and partial payments failed validation without giving the user a field they could correct.
- Fixed: vendor selection now changes only the vendor. Adding a foreign-currency bill still switches the payment to that bill's currency and requests its rate; base-currency payments retain rate `1.00000000`. Editable bill allocation amounts and automatic payment-total recalculation continue to support partial payments.
- Verification: `LiveSecuritySmokeRegressionTest` and `PartyPayloadHardeningTest` passed 18 tests / 108 assertions on PHP 8.5; `npm run build` passed; `git diff --check` passed. Generated `public/build` output remains excluded.

### 2026-11-02 Asia/Colombo - Codex - Live security smoke-test follow-up

- Branch: `release/zoho-and-security`; no PR; locally verified and ready to commit and push.
- Fixed: customer and vendor portal dashboard totals are scoped to the signed-in party's non-draft documents instead of company-wide revenue/expense; purchase invoices use the shared company currency and no longer automatically enable multi-currency from the vendor preference.
- Fixed: customer and vendor payment forms initialize the company currency, surface allocation validation errors, and retain editable allocation amounts for partial payments; successful invoice creation and credit-note approval now redirect to fresh records even when a follow-up notification or integration reports an error.
- Preserved: an approved sales return creates a credit note but does not silently settle the original invoice; the credit is applied explicitly in Customer Payment to preserve the accounting audit trail. No `public/build` or `public/hot` output is included.
- Verification: `LiveSecuritySmokeRegressionTest` and `PartyPayloadHardeningTest` passed 17 tests / 105 assertions on PHP 8.5; changed PHP files passed syntax checks; `npm run build` passed; `git diff --check` passed.

### 2026-11-01 Asia/Colombo - Codex - Correct audited migration column guidance

- Branch: `release/zoho-and-security`; no PR; ready to commit and push.
- Corrected: Expenses `Total` and Manual Journals `Account Code` are required; Chart of Accounts `Account # ` and `Currency` are supported optional fields because the Payment accounts step reads them.
- Clarified: name/code alternatives now explain that code wins; contact Display Name fallback, vendor-contact type, conditional vendor bill allocations, and Trial Balance Closing Balance precedence appear in XLSX comments and the on-screen guide. Duplicate column names use stable indexed React keys.
- Preserved: unchanged Zoho exports and importer detection behavior; vendor-payment unapplied advances remain supported. No `public/build` or `public/hot` file was edited or staged.
- Verification: `ZohoExportTemplateTest` passed 25 tests / 237 assertions on PHP 8.5.7; `npx tsc --noEmit -p .` passed; `git -c core.whitespace=cr-at-eol diff --check` passed.

### 2026-11-01 Asia/Colombo - Codex - Audit migration template columns

- Branch: `release/zoho-and-security`; ready to commit and push on top of `4d12b100`.
- Audited: all 18 generated file structures against their concrete `prepare`, validation and reconciliation paths. Raw `original_values` preservation was not treated as importer support.
- Changed: XLSX required/recognition headers are pale green with a comment, supported optional headers remain white, and ignored headers are light red with a leave-blank comment. The upload guide shows the same Required / Supported optional / Do not fill groups and explains that CSV cannot retain colors.
- Fixed: the generated Manual Journals structure now includes Status, Journal Type, Total, Currency, Exchange Rate, Description and contact fields read by the importer. Without Status, a template-filled row was recognized but skipped.
- Verification: `ZohoExportTemplateTest` passed 23 tests / 228 assertions on PHP 8.5.7; `npx tsc --noEmit -p .` passed; changed backend PHP syntax passed.

### 2026-11-01 Asia/Colombo - Codex - Generic ERP migration templates

- Branch: `release/zoho-and-security`; ready to commit and push.
- Changed: every file-based migration upload guide now presents two supported paths: upload an unchanged Zoho export, or download a Wazely XLSX/CSV structure and move data into it from another ERP. The guide exposes accepted column names before upload and the download filenames are vendor-neutral.
- Coverage added: Refund History, Manual Journals, AR Aging Summary and AP Aging Summary templates, completing template coverage for every upload-guide file entry.
- Verification: PHP 8.5 syntax passed for the changed backend files; `npx tsc --noEmit -p .` passed; `ZohoExportTemplateTest` passed 21 tests / 180 assertions on PHP 8.5.7. XAMPP PHP 8.2 cannot start this repository because Composer requires PHP 8.3 or newer.

### 2026-11-01 Asia/Colombo - Codex - Fix Payments Made server error

- Branch: `release/zoho-and-security`; pushed commit `b2f53743`.
- Changed: removed the nonexistent `vendors.primary_email` column from the vendor-payment eager-load projection. Payments Made can now load using the actual vendor schema; payment vouchers retain contact-person and vendor-user email fallbacks.
- Verification: PHP syntax and `git diff --check` passed. `PartyPayloadHardeningTest` passed on PHP 8.5.7 with 12 tests and 78 assertions. XAMPP PHP 8.2 could not start the repository because Composer requires PHP 8.3 or newer. No migration or generated build change is required.

### 2026-10-31 Asia/Colombo - Codex - Repair old product import templates

- Branch: `release/zoho-and-security`.
- Changed: old generated product CSV/XLSX files containing `General` and `Pcs` now resolve those known placeholders to the company's sole category and unit when the replacement is unambiguous. Other category and unit values remain strictly validated.
- Verification: PHP syntax and scoped whitespace passed; `ZohoManualProductUploadTest` passed (5 tests, 18 assertions), including the reported `Promotion reels` / `SKU-1793280989075` case. Generated build and unrelated local changes remain excluded.

### 2026-10-31 Asia/Colombo - Codex - Tenant-aware product import template

- Branch: `release/zoho-and-security`.
- Changed: product/service XLSX and CSV templates now use the current company's first category, unit and tax in the example row instead of fixed `General` and `Pcs` placeholders that may not exist.
- Verification: PHP syntax and scoped whitespace checks passed; `ZohoManualProductUploadTest` passed (4 tests, 16 assertions). Generated `public/build`, the unrelated payload test change, and other local artifacts remain excluded.

### 2026-10-31 Asia/Colombo - Codex - Restore manual-only categories and units

- Branch: `release/zoho-and-security`.
- Changed: removed the Categories and Units spreadsheet template/upload mode at the user's request. The existing manual category and unit entry remains unchanged; Products and Services retains its separate spreadsheet importer.
- Verification: TypeScript no-emit compilation and scoped whitespace checks passed. Generated `public/build` and unrelated local artifacts remain excluded.

### 2026-10-31 Asia/Colombo - Codex - Remove warehouse fields from migration setup

- Branch: `release/zoho-and-security`.
- Changed: removed all default-warehouse inputs and setup-time warehouse creation from initial migration workspace setup. New batches start without a default warehouse; Step 6 remains the single place for warehouse entry or no-warehouse confirmation.
- Verification: controller PHP syntax, TypeScript no-emit compilation, and scoped whitespace checks passed. `ZohoManualWarehouseImportTest` ran 3 tests: 1 passed and 2 errored because the shared `erp_live_copy` database deadlocked while inserting test migration-file rows outside the changed setup code. Generated `public/build` and unrelated local artifacts remain excluded.

### 2026-10-31 Asia/Colombo - Codex - Optional migration default warehouse

- Branch: `release/zoho-and-security`.
- Changed: migration workspace creation accepts a completely blank default-warehouse section and stores no warehouse assignment; supplying a warehouse name conditionally requires address, city, and postal code and retains exact-name reuse, creation permission checks, and legacy placeholder repair.
- Verification: controller PHP syntax, TypeScript no-emit compilation, and whitespace verification passed. The existing nullable `default_warehouse_id` database constraint required no migration. Generated `public/build` and unrelated local artifacts remain excluded.

### 2026-10-31 Asia/Colombo - Codex - Searchable migration base-currency selector

- Branch: `release/zoho-and-security`.
- Changed: migration workspace setup now uses the shared searchable currency select for `base_currency`, showing currency names and ISO codes while retaining QAR as the default and the existing backend field contract.
- Verification: TypeScript no-emit compilation and whitespace verification passed. Generated `public/build` and unrelated local artifacts remain excluded.

### 2026-10-31 Asia/Colombo - Codex - Generic full-ledger accounting-mode label

- Branch: `release/zoho-and-security`.
- Changed: renamed the visible `Full Zoho ledger history` accounting-mode option to `Full ledger history`; the internal `full_history` value and migration behavior remain unchanged.
- Verification: TypeScript no-emit compilation and whitespace verification passed. Generated `public/build` and unrelated local artifacts remain excluded.

### 2026-10-31 Asia/Qatar - Claude - Release branch zoho + security

- Branch: `release/zoho-and-security` = `origin/zoho-import-redesign` (7c94ba00) + merge of `origin/security/settings-and-payload-hardening` (94cbcd24); merge commit `86010052`, pushed at the user's request. No branch deleted; no PR.
- Files changed: merge of the 44 security files. Conflicts (7) resolved keeping both sides: StorePurchaseInvoiceRequest / UpdatePurchaseInvoiceRequest / UpdateSalesInvoiceRequest (Zoho base-currency flag, supplier/PO/sales-order refs, nullable product_id + description max 20000, with security tenant-scoped vendor/customer/warehouse/item rules); CreditNoteController (accountingCustomers scope + preferred_currency_code); CustomerPaymentController and VendorPaymentController (security per-document balance checks, permission/ownership guards and paymentRelations, then Zoho payment-currency totals, inPaymentCurrency, OutstandingDocumentService; security's trimmed column lists extended with the fields Zoho pages read: preferred_currency_code, base_currency_code, allocation carrying/settlement/exchange_difference, invoice date/total/balance/rate); CHANGELOG (all 221 entries). Zoho side had committed the two payment controllers with CRLF; resolved files use LF like main/security.
- Database records/migrations affected: none run. Release adds 9 migrations vs live `10a5f68f` (security audit table, Zoho workspace/permissions/stage tracking, `2026_10_30_000001_complete_multi_currency_and_invoice_requirements`).
- Tests run and results: `php -l` passed on all 34 merged PHP files; `npx tsc --noEmit -p .` passed. PHPUnit: tests use DatabaseTransactions and need a migrated DB; `.env` points to `erp_live_copy`, so they were NOT run there. In-memory SQLite failed (no tables). A separate empty local DB `erp_release_test` was created and is being migrated to run the suites; results pending.
- Known issues or blockers: security runbook says deploy the security commit alone; the user chose a combined release. Test the multi-currency migration on a copy of live data before production.
- Exact next step: run the requested suites against `erp_release_test` (force DB via a temporary phpunit config, never `erp_live_copy`), report, fix on the release branch if needed.
- Update same day: suites run on `erp_release_test` (migrated + seeded): 129 tests. `PartyPayloadHardeningTest` updated in `3c62ffb3` (customer/vendor payment selectors now expect `preferred_currency_code`, outstanding invoices `base_currency_code`; retainer keeps `['id','name']`) -> 11 tests / 75 assertions pass. 32 Zoho import tests still error on an empty DB: they copy `account_types.category_id` from an existing company's `SR` type (e.g. `ZohoSalesInvoiceImportTest:129`), i.e. they need a DB that already holds a company with `AccountUtility::defaultdata()` rows; not caused by the merge (merge touched no Zoho/account-setup code). Codex: run them in your usual test DB. Also `965c6a30` removed the warehouse step's "Only the warehouses already in ERP are used" option (setup no longer has a default warehouse). Note: 8 commits (c9c9b288..27a79835) were added to this branch locally after the merge; Claude's commits sit on top (fast-forward).

### 2026-10-30 Asia/Colombo - Codex - Local backend restarted with invoice conversion fix

- Branch: `zoho-import-redesign`; code commit remains `e65fd6d8`.
- Root cause of the repeated browser result: Vite was serving the current worktree frontend, but the long-running PHP backend on port 8000 had not reloaded the corrected invoice persistence code. The UI preview therefore showed USD 273.97 while the stale backend stored USD 1,000 / QAR 3,650.
- Local state: restarted the Laravel development server from the `zoho-import-redesign` worktree with error/output logs under `storage/logs`; `GET /login` returned HTTP 200 on port 8000. No database rows were rewritten and the old incorrect draft must not be used as verification.

### 2026-10-30 Asia/Colombo - Codex - Enforced invoice line currency conversion

- Branch: `zoho-import-redesign`.
- Commit: `e65fd6d8`.
- Root cause: sales/purchase invoice persistence made base-to-transaction line conversion conditional on a request flag, allowing a QAR 1,000 input on a USD invoice to be stored as USD 1,000 and then reported as QAR 3,650.
- Changed: create and update now always treat invoice line prices as company base-currency inputs and convert them through the selected transaction rate before calculating and storing document totals.
- Verification: PHP syntax passed for the sales service and both invoice controllers; `CurrencyConversionServiceTest` passed 3 tests / 3 assertions; whitespace verification passed. Existing incorrectly saved drafts are not silently rewritten. Generated `public/build` and unrelated installation-state changes remain excluded.

### 2026-10-30 Asia/Colombo - Codex - Invoice-list base-currency fallback

- Branch: `zoho-import-redesign`.
- Commit: `e3fc821f`.
- Root cause: invoices lacking `base_currency_code` in the list payload caused the dual-currency renderer to treat the selected foreign currency as both transaction and base currency, so only USD appeared.
- Changed: the shared renderer falls back to the configured company base currency, and sales/purchase list totals fall back to the transaction amount multiplied by the saved exchange rate when `base_amount` is absent. QAR remains the main line and the selected foreign currency appears beneath it in smaller text.
- Verification: TypeScript no-emit compilation and whitespace verification passed. Generated `public/build` and unrelated installation-state changes remain excluded.

### 2026-10-30 Asia/Colombo - Codex - Invoice-list dual-currency display

- Branch: `zoho-import-redesign`.
- Commit: `1d9ca63d`.
- Changed: sales and purchase invoice list/table/card amounts now show the historical company base-currency value first and the selected foreign transaction currency beneath it in smaller muted text. Base-currency-only invoices remain a single line.
- Scope: subtotal, tax, total, and balance columns plus mobile/card total and balance displays; no stored accounting values or rates were changed.
- Verification: TypeScript no-emit compilation and staged whitespace verification passed. Generated `public/build` and unrelated installation-state changes remain excluded.

### 2026-10-30 Asia/Colombo - Codex - Migration warehouse location validation

- Branch: `zoho-import-redesign`.
- Commit: `93ef6ad9`.
- Root cause: migration workspace setup created its default warehouse with hard-coded `Not provided during migration setup`, `Not specified`, and `N/A` location values, although normal warehouse creation requires real location details.
- Changed: workspace setup now collects and validates warehouse address, city, postal code, optional phone, and optional email; newly created warehouses use those values, and an exact legacy placeholder warehouse is repaired when reused without overwriting a valid warehouse.
- Verification: controller PHP syntax passed; TypeScript no-emit compilation passed. Generated `public/build` and unrelated installation-state changes remain excluded.

### 2026-10-30 Asia/Colombo - Codex - Base-price to transaction-currency invoice conversion

- Branch: `zoho-import-redesign`.
- Changed: sales and purchase invoice unit-price inputs explicitly remain in the company base currency (`Unit Price (QAR)`). Line totals and summaries divide base amounts by the entered historical rate to show the selected transaction currency; the smaller base total remains unchanged.
- Accounting: invoice forms send an explicit base-line-amount flag. The backend converts unit prices and fixed discounts to transaction currency before calculating and storing document/items, while `base_amount` remains the original carrying value. Existing imports/API requests without the flag retain their current transaction-currency contract.
- Edit safety: existing foreign invoices are converted back to base-currency line inputs for editing, then reconverted once on save.
- Verification: PHP syntax passed for all eight changed backend files; `npm exec tsc -- --noEmit` passed; scoped diff check passed.
- Commit: `bfa0bb26` (`Convert base invoice prices to transaction currency`).

### 2026-10-30 Asia/Colombo - Codex - QAR symbol and base-equivalent cleanup

- Branch: `zoho-import-redesign`.
- Changed: transaction amounts in QAR use the ERP's configured Qatari Riyal symbol and number placement, while foreign currencies retain their ISO code. Sales and purchase create/edit summaries hide the base-equivalent hint when transaction and base currencies match.
- Verification: `npm exec tsc -- --noEmit` passed; scoped diff check passed.
- Commit: `c1ec06df` (`Use QAR symbol for base currency invoices`).

### 2026-10-30 Asia/Colombo - Codex - Invoice form transaction-currency totals

- Branch: `zoho-import-redesign`.
- Changed: sales and purchase invoice create/edit item totals and summary rows now display the selected transaction currency (for example `USD 100.00`) rather than the company symbol. The final summary also shows the smaller base-currency equivalent calculated from the entered transaction rate.
- Verification: `npm exec tsc -- --noEmit` passed; scoped diff check passed.
- Commit: `0a14a904` (`Show invoice totals in transaction currency`).

### 2026-10-30 Asia/Colombo - Codex - Purchase invoice line top alignment

- Branch: `zoho-import-redesign`.
- Changed: purchase invoice Account, Qty, Unit Price, Discount, Tax, Total and Action cells now align with the top item selector instead of centering against the selector-plus-description height.
- Verification: `npm exec tsc -- --noEmit` passed; scoped diff check passed.
- Commit: `9922a08b` (`Align purchase invoice line controls to top`).

### 2026-10-30 Asia/Colombo - Codex - Searchable transaction currency picker

- Branch: `zoho-import-redesign`.
- Changed: replaced the long multi-currency select list with a shared searchable combobox that filters by currency name or ISO code, identifies the selected currency, reports no matches, and preserves manual exchange-rate behavior across every transaction form using the shared field.
- Verification: `npm run build` passed (TypeScript and Vite, 3,998 modules). Existing generated `public/build` changes remain excluded.
- Commit: `192c0311` (`Add searchable transaction currency picker`).

### 2026-10-30 Asia/Colombo - Codex - Required changes and multi-currency completion

- Branch: `zoho-import-redesign`.
- Changed: completed transaction/base-currency snapshots, party preferred currencies, manual foreign rates, currency-safe allocations and realized gain/loss posting; added exchange account configuration and dual-currency document totals. Zoho document, credit, payment and journal imports retain historical snapshots without creating artificial settlement differences.
- Changed: added purchase line descriptions, supplier/PO references, sales-order reference, separate sales/purchase download permissions enforced in UI and direct PDF routes, and renamed visible Double Entry labels to Reports without changing routes or permissions.
- Data safety: one backward-safe migration adds and backfills snapshots, allocation values, references and permissions without recalculating posted ledgers. Existing generated `public/build`, `tmp`, `graphify-out`, cache and unrelated `storage/installed` changes were not staged.
- Verification: PHP syntax passed for 44 changed PHP files; currency unit suite passed 3 tests / 3 assertions on PHP 8.5; migration `--pretend` completed; `npm run build` passed. Focused Zoho feature tests could not run from this worktree because the shared vendor autoloader resolves application classes from the parent checkout; this environment limitation was reproduced and no failing application assertion was reached.
- Commit: `5b5cde15` (`Complete multi-currency accounting and required invoice changes`).

### 2026-10-29 Asia/Colombo - Codex - Chart of Accounts completion rollback fix

- Branch: `zoho-import-redesign`.
- Root cause: `ZohoChartOfAccountsService::import()` used `$stage` inside its database transaction without capturing it in the closure, producing `Undefined variable $stage` at completion. The transaction rolled back all account and staged-row writes before the job marked the stage failed.
- Changed: captured the stage in the transaction and added a focused regression assertion. Local batch 1 was verified at 140 validated, 0 rejected, 0 written rows and safely restored from `failed` to `ready`; no upload is required.
- Verification: service PHP syntax passed; focused service suite passed 3 tests / 9 assertions; queue worker restarted with the corrected code.

### 2026-10-29 Asia/Colombo - Codex - Same-file retry after unimported migration failure

- Branch: `zoho-import-redesign`.
- Root cause: selecting the corrected 140-row Chart of Accounts file again was rejected by the batch-wide SHA-256 duplicate guard, even though the previous attempt had written no ERP rows and remained failed.
- Changed: selecting the same source again for the same uncompleted stage now reuses its private stored file, resets transient validation counts, and restarts queued inspection. Cross-stage duplicate use and replacement after completion remain blocked.
- Verification: controller PHP syntax passed; focused Zoho unit suites passed 9 tests / 25 assertions; queue worker restarted with the fix.

### 2026-10-29 Asia/Colombo - Codex - Safe replacement of unimported Zoho source files

- Branch: `zoho-import-redesign`.
- Changed: the migration workspace now labels an existing source action as `Replace file` and explains that choosing another spreadsheet supersedes the current unimported file and restarts validation. Completed stages reject replacement.
- Queue safety: superseded inspection jobs exit without staging, validating, failing, or otherwise changing the active stage; the inspector locks and rechecks file status before writing staged rows.
- Verification: PHP syntax checks passed for the controller, inspector, and job; focused Zoho unit suites passed 9 tests / 25 assertions; TypeScript and the Vite production build passed for 3,999 modules with only the existing large-chunk warning. Generated `public/build` assets remain excluded from the commit.

### 2026-10-29 Asia/Colombo - Codex - Fixed queued Chart of Accounts execution state

- Branch: `zoho-import-redesign`.
- Root cause: the controller correctly changed a validated stage from `ready` to `processing` before dispatching the queue job, but `ZohoChartOfAccountsService::import()` accepted only `ready`; the worker therefore failed before opening its import transaction or writing any ERP records.
- Changes: execution now accepts both guarded `ready` and controller-owned `processing` states; added a focused regression assertion and changelog entry. The user's latest stage was verified at 140 accepted, 0 rejected, 0 written rows, then restored from `failed` to `ready`; the import queue worker was restarted with the corrected code.
- Verification: focused unit suite passed 2 tests / 8 assertions; PHP syntax check passed. No Chart of Accounts records were imported by the failed attempt.

### 2026-10-29 Asia/Colombo - Codex - Repaired local Zoho queue and account-type validation

- Branch: `zoho-import-redesign`; account-type mapping commit pending at handoff time. Local `.env` files were restored to the user-requested `erp_live_copy` database and are not committed.
- Root cause of the stuck Chart of Accounts upload: the browser server retained `erp_live_copy` while a restarted queue worker read `erp_local`, so the worker could not see the queued upload. Server and worker were restarted against the same local-copy database; the existing upload was processed without re-uploading.
- Initial validation correctly read all 144 rows but rejected 139 because Zoho's 16 account-type names differ from ERP's grouped account types. Added an explicit reviewed mapping (for example Cash/Bank/AR/Stock to Current Assets, AP/Credit Card to Current Liabilities, and Zoho income/expense types to their ERP equivalents) rather than requiring manual edits.
- Revalidation result on the existing `erp_live_copy` workspace: 140 accepted, 4 rejected, 4 issues. The only remaining source errors are missing account codes on rows 31 Bad Debt, 45 Inventory Asset, 55 Uncategorized, and 127 Sales to Customers (Cash). No ERP records were imported. Unit mapping test passed 1 test / 7 assertions; PHP syntax and `git diff --check` passed.

### 2026-10-29 Asia/Colombo - Codex - Chart of Accounts import workflow completed

- Branch: `zoho-import-redesign`; commit pending at handoff time.
- Added the complete browser workflow for the Chart of Accounts stage: upload percentage, queued processing state and polling, automatic inspection and validation, row/field correction guidance, downloadable issue report, ERP correction links, corrected-file supersession, recheck action, guarded queued import, and timeline completion only after a successful database transaction.
- The unchanged Zoho export headers (`Account Name`, `Account Code`, `Description`, `Account Type`, `Account Status`, `Parent Account`) are mapped server-side. Normal balance is derived from the Zoho account type, existing account codes are conservatively skipped, parent accounts are imported in dependency order, and any unresolved/invalid row blocks all execution.
- Verification: PHP 8.5.12 focused tests passed 11 tests / 37 assertions; all changed PHP files passed syntax checks; the execute route is registered; TypeScript and Vite production build passed for 3,996 modules with only the existing large-chunk warning; `git diff --check` passed. The real Sithari workbook headers were inspected read-only. The currently configured worktree database `erp_local` contained no migration batch, so no real records were imported during verification. Generated `public/build` assets remain excluded.

### 2026-10-29 Asia/Colombo - Codex - Removed standalone currency migration stage

- Branch: `zoho-import-redesign`; commit pending at handoff time.
- Removed the unsupported `Currencies and exchange rates` placeholder from the Zoho migration catalogue. Migration configuration remains the source of the base currency; transaction currency and exchange rate stay attached to invoices, bills, payments, and journals where they can be validated against the source document.
- Added migration `2026_10_29_000001_remove_currency_stage_from_zoho_migrations.php` to remove the stale stage from existing workspaces and renumber later stages. The migration ran successfully on the currently configured local `erp_local` database. New and existing workspaces now expose a contiguous 24-stage journey with Chart of Accounts at step 2.
- Verification: PHP 8.5.12 catalogue tests passed 3 tests / 15 assertions; manual products/warehouse prerequisite regression passed 1 test / 5 assertions after one transient MySQL deadlock on the first attempt; PHP syntax checks and `git diff --check` passed. Generated `public/build` assets and local `tmp` runtime files remain excluded.

### 2026-10-29 Asia/Colombo - Codex - Removed all page-level import buttons

- Branch: `zoho-import-redesign`; committed locally as `4a20f27e` (`Centralize imports in bulk workspace`). Push remains pending.
- Removed every remaining `BulkImportButton` import/control from module and setup pages: vendors, customers, products/services, units, taxes, product categories, chart of accounts, account types, bank accounts/transfers, journal entries, revenues, expenses, revenue/expense categories, customer/vendor payments, credit/debit notes, petty cash, and quotations. Create, export, stock-management, filters, and third-party page buttons remain unchanged.
- Verification: repository-wide TS/TSX/JSX scan reports no page-level `BulkImportButton` usages; TypeScript and Vite production build passed for 3,999 modules with only the existing large-chunk warning; `git diff --check` passed. Generated `public/build` assets were excluded from the commit.
- Imports are now entered only through the central Bulk Import / Zoho migration workspace. No database or production state changed.

### 2026-10-29 Asia/Colombo - Codex - Worktree autoload runtime repair

- Branch: `zoho-import-redesign`; no committed application changes in this step.
- The shared `vendor` autoloader mapped `App\\` and Workdo package namespaces to the main checkout, causing `Target class [App\\Http\\Controllers\\ZohoMigrationController] does not exist` when opening the new workspace from this worktree.
- Added an untracked local runtime bootstrap at `tmp/worktree-autoload.php` and restarted both the PHP development server and database queue worker with it. The bootstrap prepends this worktree's `app`, `tests`, and `packages/workdo` PSR-4 paths without rewriting the shared Composer autoloader.
- Verification: `zoho-migrations.index` resolves to `ZohoMigrationController@index`; unauthenticated `GET /zoho-migrations` returns the expected 302 to `/login`; one worktree-aware queue worker is active. Local app remains on `erp_live_copy`; production is unchanged.

### 2026-10-29 Asia/Colombo - Codex - Local company plan-assignment timeout

- Branch: `zoho-import-redesign`; HRM idempotency fix committed locally as `7a302951` (`Make HRM plan initialization idempotent`). Push remains pending.
- Root cause of the browser's 30-second fatal: the local Laravel process had been started with `QUEUE_CONNECTION=sync`, so `InitializePlanData` ran every enabled module's default-data and role-permission listeners inside the HTTP request. Restarted Laravel with the database queue and left one persistent worker running with a 600-second job timeout against local `erp_live_copy`.
- Improved `HrmUtility::defaultdata()` to resolve permission IDs once and `syncWithoutDetaching`, replacing hundreds of cached per-permission checks/inserts and preventing duplicate role-permission links when HRM initialization repeats.
- Verification: the same `assignPlan()` call returned successfully in 0.82 seconds after queue correction; focused HRM repeat-initialization test passed 1 test / 3 assertions; PHP syntax and diff checks passed; local HTTP `/` returns 200. No production state changed.
- Local data note: `Test COMPANY` (user id 950) had plan 4 saved before the original synchronous request timed out and is partially initialized. Replay testing exposed a separate legacy duplicate-permission issue in Contract role initialization, so replay jobs 116/118 were removed. Use a newly created company for a clean UI verification; the queue is now correctly configured for that flow.

### 2026-10-29 Asia/Colombo - Codex - Local app switched to erp_live_copy

- Branch: `zoho-import-redesign`; no code changes in this step.
- Local Laravel development server at `http://127.0.0.1:8000` now runs against the existing local `erp_live_copy` database, not `erp_local` or `erp_security_test`. Vite remains on port 5173.
- Applied only the three existing Zoho workspace migrations (`2026_10_27_000001`, `2026_10_27_000002`, `2026_10_28_000001`) to `erp_live_copy` so the redesign can be tested with the copied live records. Verification: database reports 321 users, `zoho_migration_batches` exists, and HTTP `/` returns 200.
- This is a local copied database only. Production database, credentials, code, and deployment state were not changed.

### 2026-10-29 Asia/Colombo - Codex - Full erp_local initialization

- Branch: `zoho-import-redesign`; migration compatibility fix committed locally as `c15c6aa4` (`Fix purchase order index migration`). Push remains pending.
- Initialized the empty local-only `erp_local` database with the repository's full migration and standard seed set. Both the application and Vite development servers are running locally; Laravel is explicitly connected to `erp_local` for this process.
- A clean MySQL migration exposed an existing identifier-length failure in the Purchase Order invoice-link unique index. Shortened it to `po_invoice_link_unique` and made the migration restart-safe when MySQL created the table before rejecting the original index.
- Verification: the complete migration and seed run finished successfully through `2026_10_28_000001_add_zoho_migration_stage_tracking`; PHP syntax and `git diff --check` passed; `migrate:status` reports both the Purchase Order and latest Zoho stage migrations as ran; HTTP `/` returns 200; the seeded Super Admin password hash was verified.
- Local seeded accounts only: `superadmin@example.com` and `company@example.com`. No production database, credentials, or deployment state changed.

### 2026-10-29 Asia/Colombo - Codex - Manual Zoho prerequisite imports

- Branch: `zoho-import-redesign`; committed locally as `68ddc83b` (`Add guarded manual Zoho prerequisites`). Push remains pending.
- Added guarded copy/paste imports for the two datasets unavailable from Zoho: Products & Services and Warehouse Stock. Each screen presents fixed Excel-compatible headers, a prominent dependency warning, and a required accuracy confirmation before import.
- Validation is all-or-nothing. Product rows reuse the existing product import definition and additionally require explicit type, category, unit, sale price, and purchase price; duplicate/existing SKUs follow the batch duplicate policy. Stock rows require an existing non-service SKU, a tenant-owned warehouse, a non-negative integer quantity, and unique SKU/warehouse pairs. Later guided stages remain locked until these prerequisites complete.
- Imported source CSVs, staged rows, validation issues, counts, and audit events are retained in the migration workspace. Previous source files are deleted only after a replacement transaction succeeds. Spreadsheet-formula-leading values are escaped in stored CSV output.
- Verification: PHP syntax checks passed; route registration confirmed; focused tests passed 8 tests / 26 assertions, including a real Products & Services then Warehouse Stock import against isolated `erp_security_test`; TypeScript/Vite production build passed for 4,000 modules with existing chunk-size warnings only; `git diff --check` passed. Generated `public/build` files and temporary test bootstrap were excluded from the commit.
- No production data, credentials, or deployment state changed. Next Zoho redesign work remains execution/reconciliation for the exported datasets and mapping fixtures.

### 2026-10-29 Asia/Colombo - Codex - Exact-live security integration verification

- Branch: `security/live-20261028-verified`, based on exact production commit `34cdca12`; local head `8dc4c81`. Push and production deployment remain pending.
- Files changed: 44 reviewed application, test, migration, changelog, and runbook files across the six security commits. Generated `public/build` assets, `.env`, dependency links, and verification artifacts are not committed.
- Integration decisions: preserved the live overdue-bill selection behavior while adding create permission checks, tenant-owned vendor validation, minimal invoice fields, and the existing outstanding-document filter. Preserved all live and security changelog entries.
- Tests run and results: PHP 8.5.12 focused security suites passed 30 tests / 151 assertions; six-role MySQL tenant-isolation feature test passed 1 / 11; outstanding-document regression suite passed 4 / 5. Total targeted verification: 35 tests / 167 assertions. All 34 changed PHP files passed syntax checks. The sensitive-setting audit migration rolled back and reapplied successfully on isolated `erp_security_test`. TypeScript and the Vite production build passed for 3,999 modules with only the existing large-chunk warning. `git diff --check` and conflict-marker scan passed.
- Broader suite result: the full unit suite reported 3 errors and 5 failures outside the security scope. Re-running exactly those tests on untouched live commit `34cdca12` produced identical results: missing `CurrencyConversionService`, stale bulk-import registry expectations, two address-validation expectations, and one project-property validation expectation. These are confirmed baseline issues, not security integration regressions.
- Production state: unchanged and reported healthy. Database and `.env` backups exist, `.env` permissions were corrected, and the exact live commit is preserved at `backup/live-20261028-before-security`.
- Remaining work: explicitly approve and push `security/live-20261028-verified`, deploy in the approved window, run the additive migration and cache clears, rotate credentials one at a time, invalidate sessions where required, execute live SMTP/AI/OAuth/permission/workflow smoke tests, monitor logs, and revoke old credentials.

### 2026-10-28 Asia/Colombo - Codex - Security pre-deployment reminder

- Branch: `security/settings-and-payload-hardening` at `94cbcd24`; no repository code changed.
- Files changed: external `E:\Doha Workplace\ERP\Security Updates\README - Before Security Deployment.md` and this untracked coordination file.
- What was implemented: added a complete operator checklist covering live database and `.env` backups, current commit capture, backup verification, reviewed release identity, deployment/migration/cache steps, pre-rotation smoke tests, one-at-a-time credential rotation, session invalidation, monitoring, final verification, and rollback.
- Tests run and results: content checked for credential values; none included. No application tests were required.
- Known issues or blockers: live backup and production authorization remain required before deployment.
- Exact next step: verify the live production backups, then follow `docs/security-day-6-deployment-runbook.md`.

### 2026-10-28 Asia/Colombo - Codex - Security Day 6 pre-deployment gate

- Branch: `security/settings-and-payload-hardening`; application commit `4563ea7a`; runbook commit `94cbcd24` (`Add security deployment runbook`).
- Files changed: new `docs/security-day-6-deployment-runbook.md`, `AGENTS.md`, and the external Word tracker `E:\Doha Workplace\ERP\Security Updates\Wazely ERP - Security Implementation Plan and Progress Tracker - Updated.docx`. Generated `public/build`, `public/hot`, storage, artifact, and temp files were not staged.
- Database records/migrations affected: no production or business data. On isolated `erp_security_test`, the additive `2026_10_27_000001_create_sensitive_setting_audits_table` migration was rolled back, reapplied, and confirmed as ran.
- What was implemented: documented the exact release identity, approval gates, production-state capture, verified backup requirements, deployment/cache commands, pre/post-rotation smoke tests, one-at-a-time credential rotation, session invalidation, monitoring, stop conditions, and rollback procedure. The Word tracker now marks the completed Day 5 build/check row green while end-to-end integrations and production Day 6 work remain pending.
- Decisions and assumptions: no production operation or credential rotation occurs without an authorized maintenance window, verified backups, secure replacement credentials, and an operator able to test integrations. Credential values must never enter Git, logs, screenshots, prompts, chat, or the runbook.
- Tests run and results: focused PHP security suites passed 30 tests / 151 assertions; isolated MySQL six-role matrix passed 1 test / 11 assertions; `npm run build` (`tsc && vite build`) passed in 3m 28s with only the existing >500 kB chunk-size warning; audit migration rollback/reapply passed; settings routes and local environment inventory completed.
- Manual verification: visually rendered all four Word pages after update; no clipping, overlap, or blank page remains. Reviewed the five-commit security delta and confirmed only one new security migration.
- Known issues or blockers: production backup, deployment, integration smoke tests, credential rotation, session invalidation, monitoring, and old-credential revocation require production access and explicit operational authorization. The broad end-to-end integration row remains pending.
- Deployment or rollback notes: follow `docs/security-day-6-deployment-runbook.md`. Build assets must be generated by the deployment process, not taken from this dirty local worktree. A normal code rollback should retain the additive audit table unless the release owner explicitly approves dropping it.
- Files/areas still claimed: none after the runbook commit. Production Phase 3 operations must be claimed when an authorized maintenance window begins.
- Exact next step: release owner confirms the maintenance window, operator, verified backup destinations, and secure access to replacement credentials; then execute Sections 1 through 7 of the runbook in order.

### 2026-10-28 Asia/Colombo - Codex - Security Day 5 role and tenant isolation

- Branch: `security/settings-and-payload-hardening`; commit `4563ea7a` (`Complete security role isolation tests`).
- Files changed: shared user accounting-customer scope, tenant accounting-customer validation rule, sales invoice/proposal/return and Account customer-payment/credit-note selectors, sales-invoice and customer-payment requests, focused unit/feature tests, `CHANGELOG.md`, and `AGENTS.md`.
- Database records/migrations affected: no application or production data and no migrations. A dedicated local `erp_security_test` database was created and migrated solely for transactional tests; tests rolled back their records.
- What was implemented: production-client portal users are excluded consistently from accounting customer selectors and rejected when submitted as invoice/payment customers; normal customers remain tenant-scoped. The role matrix covers superadmin, company admin, staff, customer, vendor, and production client, plus a foreign-company customer.
- Decisions and assumptions: `production-client` role membership is the authoritative separator because production portal accounts intentionally retain the underlying `client` user type for authentication. No customer records or role assignments were changed.
- Tests run and results: PHP 8.5 focused Phase 1/Day 4 unit suites passed 30 tests / 151 assertions; MySQL role-matrix feature test passed 1 test / 11 assertions; PHP syntax and `git diff --check` passed. The feature database is isolated from local business data.
- Manual verification: source inventory confirmed the affected accounting selector paths use the shared scope; no browser records were created or modified.
- Known issues or blockers: none for Phase 2 code. Credential rotation, production backup, deployment smoke tests, cache clearing, monitoring, and old-credential revocation remain Day 6 Phase 3 operations.
- Deployment or rollback notes: code-only change; no migration. Do not deploy generated `public/build` or `public/hot` changes present in this worktree. Revert commit `4563ea7a` if rollback is required.
- Files/areas still claimed: none after the Day 5 commit; Phase 3 deployment paths must be claimed when deployment begins.
- Exact next step: prepare the Day 6 backup/rotation/deployment checklist without printing credentials.

### 2026-10-28 Asia/Colombo - Codex - Security Day 4 party payload and tenant hardening

- Branch: `security/settings-and-payload-hardening`; commit `3b4b6764` (`Harden party selectors and tenant validation`).
- Files changed: Account customer/vendor payment controllers and requests, Retainer payment controller and retainer/payment requests, purchase-invoice create/update requests, focused unit contract test, and `CHANGELOG.md`.
- Database records/migrations affected: none.
- What was implemented: customer/vendor/bank-account selector payloads now contain only fields used by the UI; payment rows and receipt/voucher relations use explicit field lists, with portal email included only on the receipt/voucher where displayed; outstanding invoice/bill/retainer endpoints require the matching create permission and reject parties outside the active tenant/type; submitted party, bank, warehouse, product, document and allocation IDs are tenant-scoped; vendor and retainer allocations must belong to the selected party and grouped allocations cannot exceed the available balance.
- Audit result: sales invoices and quotations already used tenant/type-scoped parties and minimal explicit fields; deals and projects already used minimal `id`/`name` option payloads; retainers intentionally keep customer email on document create/edit because it is displayed to disambiguate customers. Production clients remain excluded from accounting customer selectors because those queries require `type = client` and the active tenant.
- Tests/checks: PHP syntax passed for all 11 changed/new PHP files; TypeScript `npx tsc --noEmit` passed; `git diff --check` passed. The focused PHPUnit file was added but could not run locally because the only available CLI is PHP 8.2.12 while Composer requires PHP >= 8.3; no test failure occurred.
- Known issues or blockers: run the focused PHPUnit suite in the deployment/test environment with PHP >= 8.3 before release. The separate overdue-bill branch was not merged or altered.
- Deployment/rollback: code-only change, no migration; revert commit `3b4b6764` to roll back. Do not deploy generated `public/build` or `public/hot` files from this worktree.
- Files/areas still claimed: none for Day 4 after commit. Day 5 permission and role-matrix testing remains next.
- Exact next step: run `php artisan test tests/Unit/PartyPayloadHardeningTest.php tests/Unit/BrowserSettingsPayloadServiceTest.php tests/Unit/SensitiveSettingsAuthorizationTest.php` under PHP >= 8.3, then perform Day 5 role/tenant manipulation tests.

### 2026-10-28 Asia/Qatar - Claude - Zoho export inventory

- Branch: none (read-only; current checkout `security/settings-and-payload-hardening` untouched)
- Commit: none
- Files changed: `AGENTS.md` (Claude Track Status > Zoho import redesign, Current Claims); new `E:\Doha Workplace\ERP Data Fixes\Zoho Import\Zoho Export Inventory.md` (outside repo). Earlier today in this file: payment accounts run on live, overdue-bill finding for Codex.
- Database records/migrations affected: none
- What was implemented: inventory of every Zoho dataset in ERP Data Fixes, Import data (incl. ZIPs), Downloads, repo review folders and the session scratchpad - status Available/Partial/Missing, exact path, headers, line and distinct-document counts, date ranges, original vs generated, and which fields (currency, exchange rate, tax, unit, category, payment account, warehouse) can be derived safely.
- Decisions and assumptions: original = Zoho export/report as received; duplicates confirmed by MD5; `Downloads\Creditnotes_Invoice.xlsx` treated as a Zoho export received from Sithari (Zoho column layout). No financial value was guessed.
- Tests run and results: profiler scripts (openpyxl/xlrd, read-only) over 229 files; counts cross-checked against the GL (e.g. 552 invoices = 552 in Invoice Details report; 460 customer payments = 460 GL customer_payment txns; 31 bills, 33 vendor payments, 25 CNs, 3 VCs match GL counts).
- Manual verification: report periods read from each report's title rows. Second pass searched all of C:\Users\acer, E:\ (files since May 2026) and GIT incl. storage uploads: 468 files opened by content, no new Zoho export type; column-set differences between export dates recorded in Claude Track Status.
- Known issues or blockers: missing Items master, AR/AP Aging Details, Inventory Valuation Summary, vendor-credit applications; expenses export stops 27 Jul 2026; nothing after 30 Sep 2026.
- Deployment or rollback notes: none
- Files/areas still claimed: Claude data folders listed in Current Claims (now incl. `Zoho Import`)
- Exact next step: if the migration needs them, ask the accounts team for Zoho exports of Items, AR Aging Details, AP Aging Details, Inventory Valuation Summary, Expenses from 28 Jul 2026, and a Trial Balance as of the chosen cutover date.

### 2026-10-28 Asia/Colombo - Codex - Migration default warehouse text entry

- Branch: `zoho-import-redesign`; commit `25d79774` (`Create default warehouse from migration setup`) pushed to origin; no pull request created.
- Replaced the empty default-warehouse selector with a required warehouse-name textbox. Migration creation now reuses an exact tenant-owned warehouse name or, with warehouse-create permission, creates an active ERP warehouse and stores its ID as the migration default.
- Newly created setup warehouses use explicit incomplete-detail placeholders for required address/city/ZIP fields and can be completed from the normal Warehouses page. No duplicate is created for case-insensitive exact-name matches.
- Verification: PHP syntax and TypeScript passed; focused PHPUnit passed 16 tests / 42 assertions; `git diff --check` passed; no generated assets included.

### 2026-10-28 Asia/Colombo - Codex - Neutral migration placeholder

- Branch: `zoho-import-redesign`; commit `42f59174` (`Use neutral migration name placeholder`) pushed to origin; no pull request created.
- Changed the Bulk Import migration-name placeholder from a Zoho-specific example to `Company data migration`; no behavior or data changed.
- Verification: TypeScript `npx tsc --noEmit` and `git diff --check` passed; worktree clean; no generated assets included.

### 2026-10-28 Asia/Colombo - Codex - Zoho Bulk Import workspace redesign slice

- Branch: `zoho-import-redesign`; commit `f917ad3e` (`Build guided Zoho bulk import workspace`) pushed to origin; no pull request created.
- Files changed: Zoho stage catalogue, validator, controller/jobs/models/migration/routes, new Bulk Import Inertia page, company navigation, legacy invoice/bill/warehouse import-button removal, focused test, and `CHANGELOG.md`.
- Database changes: added `workflow_mode`, file stage ownership, durable migration stages, validation issues, and audit events. Migration rollback and re-apply both passed locally; no accounting or production records changed.
- Implemented: full/standalone workspaces, canonical 25-stage vertical journey, strict full-mode locking, supported-profile gating, private stage-aware CSV/XLSX upload, safe source preview, polling, progress metrics, ERP destination links, source download, deterministic invoice/bill customer/vendor/item/warehouse validation, revalidation, CSV issue report with formula-injection protection, and stage activity history. Existing page-level imports were removed from Sales, Purchases, and Warehouses.
- Safety boundary: no financial execution or fake completion was added. Document assembly/import, payment and credit allocation execution, provenance actions, reconciliation, undo, and verified profiles for currently unsupported stages remain required before stages can be completed/reconciled.
- Tests: PHP syntax passed; TypeScript `npx tsc --noEmit` passed; focused PHPUnit passed 16 tests / 42 assertions on PHP 8.5 with the worktree autoloader; seven routes registered; `git diff --check` passed. Migration rollback/re-apply passed.
- Visual verification: attempted Chrome and in-app browser automation, but the browser request-header policy could not load and the in-app browser was unavailable. No visual QA is claimed.
- Generated files: verification build output was removed; `public/build` and `public/hot` are not part of this change.
- Exact next step: add versioned mappings for currencies/taxes/units/categories/warehouses, then implement approved master execution and provenance before document execution; visually QA the workspace on desktop/mobile once browser automation is available.

### 2026-10-28 Asia/Colombo - Codex - Excel content detection compatibility

- Branch: `zoho-import-redesign`; commit `5d4889f1` (`Detect Excel imports by workbook content`) pushed to origin; no pull request created.
- Issue: after the runtime was corrected, the user's `Invoice (1).xlsx` reached the new validator but the strict Xlsx reader reported it unreadable. This indicates Excel-readable content stored in a different workbook representation rather than the previous MIME/ZIP problem.
- Implementation: spreadsheet validation now uses PhpSpreadsheet `IOFactory::createReaderForFile()` to detect actual content. `.xlsx` uploads may be recognized as Xlsx, legacy Xls, XML Spreadsheet, HTML workbook, or ODS; `.csv` remains CSV-only. Arbitrary text renamed to `.xlsx` remains rejected. The importer uses the same reader factory, keeping validation and parsing consistent.
- Tests: focused PHPUnit passed 3 tests / 3 assertions for XLSX-as-ZIP, legacy Excel content named `.xlsx`, and fake renamed content rejection.
- Database/migrations: none. Running local port 8001 uses this worktree source through the verified runtime autoloader.
- Exact next step: reselect `Invoice (1).xlsx` and validate it; if parsing then reports a workbook-specific error, capture that parser message and inspect the exact source export rather than weakening file safety.

### 2026-10-28 Asia/Colombo - Codex - Local XLSX runtime correction

- Branch: `zoho-import-redesign` at pushed commit `05551fde`; no additional code commit.
- Root cause: the worktree shared the main checkout's Composer `vendor` directory, whose generated PSR-4 map loaded `App` classes from the main security checkout. The local port 8001 therefore executed the old `mimes:csv,txt,xlsx` controller despite the XLSX fix existing on the Zoho branch.
- Runtime fix: restarted port 8001 with an ignored worktree-aware autoloader stored under `storage/framework`; the current Vite frontend remains active via `public/hot`.
- Verification: temporary diagnostic route confirmed both `BulkImportController` and `SpreadsheetUpload` are loaded from `.worktrees/zoho-import-redesign`; the diagnostic route was removed immediately. Login returns HTTP 200.
- Database/code impact: none. No business data, tracked source, `public/build`, or committed `public/hot` changed.
- Exact next step: hard-refresh the local page, reopen Import Sales Invoices, reselect the XLSX, and click Validate File.

### 2026-10-28 Asia/Colombo - Codex - Local plan-assignment UI diagnosis

- Branch/runtime: `zoho-import-redesign` at `05551fde`; no code or database changes.
- Diagnosis: local superadmin has 114 permissions including `manage-any-plans` and `edit-users`; `erp_local` contains four active plans; Test COMPANY exists and has no active plan. The backend therefore supplies the plan list correctly.
- Root cause: the local Laravel server was serving an older compiled frontend, so the current green Change Plan package action was absent from the company card even though live/current source contains it.
- Runtime fix: started the Zoho worktree Vite development frontend; `public/hot` currently points to `http://127.0.0.1:5175`, and the Laravel login HTML references that current frontend. `public/hot` is runtime-only and must not be committed.
- Exact next step: hard-refresh `http://127.0.0.1:8001/users`; use the green package icon on the company card to assign one of the four active plans.

### 2026-10-28 Asia/Colombo - Codex - Local superadmin access

- Branch/runtime: `zoho-import-redesign` at `05551fde`, running locally at `http://127.0.0.1:8001`; no code changes or commit.
- Database change: created or refreshed one local-only enabled, verified superadmin account in `erp_local` (user id 944) and assigned the `superadmin` role. Password hashing was verified locally; credentials were provided only to the user and are not stored in this coordination file.
- SQL backup decision: did not import `C:\Users\acer\Downloads\127_0_0_1 (1).sql`; it targets database `erpsystem`, contains hashed rather than recoverable passwords, and importing it could replace unrelated local state.
- Production impact: none. No production database or credentials were accessed.
- Exact next step: sign in with the local-only account, create or manage a company account as needed, then use a company login to test tenant-scoped sales-invoice and Zoho imports.

### 2026-10-28 Asia/Colombo - Codex - Genuine XLSX upload validation fix

- Branch: `zoho-import-redesign`; commit `05551fde` (`Accept genuine XLSX import uploads`) pushed to origin; no pull request created.
- Files changed: shared spreadsheet upload validation rule, generic bulk-import controller, Zoho migration controller, focused unit tests, and `CHANGELOG.md`.
- What was implemented: removed Laravel's MIME-extension `mimes:xlsx` check, which rejects some real XLSX ZIP containers; validation now uses PhpSpreadsheet readers to verify that CSV/XLSX content is actually readable. The 10 MB file limit and `.csv`/`.xlsx` restriction remain.
- Security behavior: genuine XLSX files reported as `application/zip` are accepted; arbitrary files renamed to `.xlsx` are rejected.
- Tests run and results: focused PHPUnit passed 2 tests / 2 assertions; PHP syntax passed for every changed/new PHP file; `git diff --check` passed. A standalone runtime check also accepted a generated genuine XLSX reported as `application/zip`.
- Database records/migrations affected: none.
- Runtime state: local Zoho branch server remains available at `http://127.0.0.1:8001` and automatically uses the PHP change; no frontend rebuild is required.
- Deployment notes: code-only validation change; no `public/build` or `public/hot` files included.
- Exact next step: retry the same sales-invoice XLSX through the open local importer, then continue with Zoho mapping and master-data resolution implementation.

### 2026-10-28 Asia/Colombo - Codex - Zoho migration local runtime

- Branch: `zoho-import-redesign` at `96753b74`; no code changes and no commit.
- Local runtime: Laravel development server started from the isolated Zoho worktree at `http://127.0.0.1:8001` (process 32620).
- Database state: applied Zoho workspace migrations `2026_10_27_000001` and `2026_10_27_000002` to local database `erp_local` in batch 57. No production database was accessed.
- Verification: `GET /zoho-migrations` returned HTTP 302 to `/login`, confirming the authenticated route is available; both migrations report `Ran`.
- Current limitation: commit `96753b74` is backend foundation only. `/zoho-migrations` returns JSON after authentication; the visual batch/upload/review UI is still the next implementation phase.
- Runtime-only setup: the worktree uses an ignored local `.env` copy and `node_modules` junction; no tracked files, generated `public/build` assets, or `public/hot` were changed.
- Exact next step: sign in at the local URL to inspect the ERP, then implement the Zoho migration UI and menu entry before user-facing import testing.

### 2026-10-28 Asia/Colombo - Codex - Overdue bill payment and aging fix

- Branch: `fix/overdue-bill-payment-selection`, isolated from security, Zoho import, data-correction, dashboard, and generated-asset work.
- Commit: `da58a92f` (`Fix overdue bill payment selection`), pushed to `origin/fix/overdue-bill-payment-selection`; no pull request created.
- Files changed: vendor payment controller, report service, shared outstanding-document service, focused unit test, and `CHANGELOG.md`.
- Database records/migrations affected: none. The local database was queried read-only only.
- What was implemented: overdue bills with a positive balance now appear for vendor payment; submitted allocations must belong to the selected tenant/vendor, remain payable, and not exceed the outstanding balance; clearing a partial/full payment deterministically sets the bill to `partial`/`paid`; aging, tax, customer/vendor summary, and statement queries include overdue documents.
- Status-filter inventory: the three exact `posted`/`partial` exclusions were vendor outstanding selection plus invoice and bill aging. Seven additional `posted`/`partial`/`paid` report filters omitted overdue documents (sales/purchase tax, customer invoiced/balance, vendor billed/balance, customer statement, vendor statement); all were corrected.
- Tests run and results: focused PHPUnit passed 4 tests / 5 assertions; PHP syntax passed for all changed/new PHP files; `git diff --check` passed. Read-only local check found Newoon `INV 359/2026`, status `overdue`, balance 500, and confirmed the new outstanding query returns it. Payment-state tests confirmed 200 of 500 becomes partial with 300 remaining and 500 of 500 becomes paid with zero remaining.
- Manual verification: no payment was created or cleared, because the task explicitly forbids data changes. UI behavior follows the same tested endpoint query; final create/clear smoke testing should use a disposable database copy.
- Known issues or blockers: none for the code fix. A full browser payment smoke test requires a disposable database because it writes payment, allocation, journal, bank-transaction, and bill state.
- Deployment or rollback notes: code-only change; no migration. Do not deploy `public/build` or `public/hot` from another workstream.
- Files/areas still claimed: none after commit/push.
- Exact next step: deploy the branch code to a test copy, create a partial then full payment for an overdue test bill, verify aging, then merge through the normal release process.

### 2026-10-27 Asia/Colombo - Codex - Zoho migration workspace foundation

- Branch: `zoho-import-redesign`, isolated worktree based on deployed commit `10a5f68f` so security work and generated assets are excluded.
- Commit: `96753b74` (`Build Zoho migration workspace foundation`), pushed to `origin/zoho-import-redesign`; no pull request created.
- Files changed: new Zoho batch/file/staged-row models, workspace and permission migrations, conservative profile catalogue, queued file inspector/job, tenant-scoped JSON controller/routes, focused unit test, `docs/zoho-import-plan.md`, and `CHANGELOG.md`.
- Database records/migrations affected: adds `zoho_migration_batches`, `zoho_migration_files`, `zoho_migration_staged_rows`, and six granular Zoho migration permissions when deployed; no migration was run and no business/production records changed.
- What was implemented: immutable batch accounting-mode configuration, mandatory default warehouse, tenant-owned warehouse validation, private 10 MB CSV/XLSX upload, SHA-256 duplicate protection, queued inspection, 10,000-row safety limit, profile detection with control/allocation precedence, durable original-row staging, and sanitized failure handling. No financial records can be created by this slice.
- Decisions and assumptions: the unsafe one-time `ZohoMigrationImportService` is not called because it can create unlinked payment accounts and process allocation rows independently; versioned profiles remain conservative until Claude supplies sanitized exact export headers.
- Tests run and results: PHP 8.5.12 PHPUnit passed 7 tests / 17 assertions; all four Zoho routes registered; PHP syntax passed for every new PHP/migration file; `git diff --check` passed.
- Manual verification: inspected existing generic bulk import, one-time Zoho CLI service, permission migrations, tenant resolution, and earlier remote Zoho branches without merging them.
- Known issues or blockers: workspace UI, source checklist, manual ambiguous-profile resolution, mapping catalogue, batch locking, validation/reconciliation, provenance destination links, execution, and undo remain future phases; exact sanitized Zoho headers/control files are still required.
- Deployment or rollback notes: do not deploy as a complete migration feature yet; migrations are backward-safe and rollback drops only the new workspace tables/permissions. An `imports` queue worker is required before enabling uploads outside sync/test environments.
- Files/areas still claimed: Zoho workspace paths in Current Claims; generic bulk-import files remain deliberately unmodified.
- Exact next step: implement the batch/upload/review UI and required-source checklist, then add versioned profiles from Claude's sanitized export catalogue before any master or financial import execution.

### 2026-10-27 Asia/Colombo - Codex - Zoho migration implementation plan

- Branch: `security/settings-and-payload-hardening` (documentation-only work; no import implementation started).
- Commit: not committed.
- Files changed: `docs/zoho-import-plan.md`, `AGENTS.md`.
- Database records/migrations affected: none.
- What was implemented: created an implementation-ready Zoho migration plan covering mutually exclusive accounting modes, required source datasets, durable staging/provenance, AI review controls, dependency waves, financial transformations, inventory cutover, reconciliation, rollback, security, tests, and delivery gates.
- Decisions and assumptions: Zoho filenames may vary, so versioned profiles detect logical datasets by headers/metadata; missing allocation/control datasets block import; AI is opt-in and review-only; no existing bulk-import definition is Zoho-ready without contract verification.
- Tests run and results: documentation checked against current bulk-import limits and known invoice/payment migration failures; no application tests were required.
- Manual verification: confirmed the plan incorporates every MUST FIX, SHOULD FIX, and ALSO item from the reviewed draft.
- Known issues or blockers: sanitized Zoho samples and exact export headers are required before implementation; the first-pilot accounting mode still requires product/finance approval.
- Deployment or rollback notes: documentation only; no application or production state changed.
- Files/areas still claimed: none from this documentation task; Claude's planned import-code claim remains unchanged.
- Exact next step: Claude completes the mapping catalogue from sanitized exports, then the implementing agent claims exact files before Phase 2 code changes.

### 2026-10-27 Asia/Colombo - Codex - Phase 1 payment settings and authorization completion

- Branch: `security/settings-and-payload-hardening`.
- Commit: `58e5652f` (`Complete payment settings hardening`), pushed to `origin/security/settings-and-payload-hardening`; no pull request created.
- Files changed: browser settings allowlist/flags; Stripe and PayPal settings controllers, request authorization and React forms; focused payload and authorization tests; `CHANGELOG.md`. Generated `public/build` files and `public/hot` were excluded.
- Database records/migrations affected: none in this commit; the prior `sensitive_setting_audits` migration was validated against isolated in-memory SQLite and remains required at deployment.
- What was implemented: Stripe and PayPal secrets are no longer returned to settings forms, blank submissions preserve configured credentials, explicit clear is available only when the gateway is disabled, replacements and clears are audited without values, and payment setting requests reject users without the matching edit permission before controller execution.
- Decisions and assumptions: Stripe publishable key and PayPal client ID/mode are safe settings-page identifiers; payment secret end-to-end calls require approved sandbox credentials and are not simulated with production values.
- Tests run and results: TypeScript passed; production build passed (3,999 modules, existing chunk-size warning only); PHPUnit passed 19 tests / 76 assertions; PHP syntax and `git diff --check` passed; audit migration SQL generated successfully on isolated SQLite.
- Manual verification: frontend/backend request fields and configured flags were matched; tracker render was visually inspected. No real credentials were viewed, submitted or changed.
- Known issues or blockers: live/sandbox email, AI, social-login, payment and storage integration checks require test credentials and an authorized settings session. Production backup belongs immediately before deployment. The original external tracker was open/locked, so the updated verified copy was saved as `Wazely ERP - Security Implementation Plan and Progress Tracker - Updated.docx`.
- Deployment or rollback notes: run the audit migration before using settings updates; use sandbox/test credentials for integration checks; do not rotate production credentials until Phase 3 backup and deployment.
- Files/areas still claimed: none from Phase 1.
- Exact next step: begin Phase 2 payload and tenant-access inventory, while scheduling credential-backed integration verification for the pre-deployment test environment.

### 2026-10-27 Asia/Colombo - Codex - Explicit secret clearing and audit trail

- Branch: `security/settings-and-payload-hardening`.
- Commit: `6ddda3f9` (`Audit sensitive setting changes`), pushed to `origin/security/settings-and-payload-hardening`; no pull request created.
- Files changed: sensitive settings flows for email, Pusher, AWS/Wasabi storage, AI Agent, AI Assistant and social login; new `SensitiveSettingAudit` model/service and migration; focused unit test; `CHANGELOG.md`. Generated `public/build` files and `public/hot` were excluded.
- Database records/migrations affected: new `sensitive_setting_audits` table after deployment; no existing setting or credential values were changed during development.
- What was implemented: blank secret inputs preserve stored credentials, replacement inputs replace them, and a separate visible Clear action is required to remove them. Configuration, replacement and clearing actions record user, tenant, setting identifier, timestamp, IP and user agent without recording secret values.
- Decisions and assumptions: existing permission checks remain the authorization boundary; Google and Microsoft secret clearing already existed and is now included in the same audit trail; conflicting replace-and-clear submissions are rejected.
- Tests run and results: TypeScript `npx tsc --noEmit` passed; production `npm run build` passed (3,999 modules, existing large-chunk warning only); PHPUnit 12.5.9 on PHP 8.5.12 passed 5 tests / 28 assertions; PHP syntax and `git diff --check` passed.
- Manual verification: request names, configured-state flags and clear controls were matched across frontend and backend; no real credentials were viewed or submitted. Authenticated browser interaction was not performed because no test credentials were used.
- Known issues or blockers: migration must run before audited settings updates are used; Phase 2 minimal party payload and tenant-access hardening remains pending.
- Deployment or rollback notes: deploy source without generated local assets, run the migration, clear caches, then verify each settings screen with non-production test credentials. Rollback code and drop the new audit table only if the release is reverted.
- Files/areas still claimed: none from Phase 1; Phase 2 paths must be claimed before edits.
- Exact next step: inventory quotations, invoices, customer/vendor payments, retainers, deals and projects for full user/customer/vendor payloads and missing tenant/type constraints, then claim and harden the first related controller/resource group.

### 2026-10-26 Asia/Colombo - Codex - Account dashboard ledger bank balance

- Branch: `fix/account-dashboard-ledger-bank-balance`, isolated worktree based on deployed commit `10a5f68f`.
- Commit: `4b20baff` (`Use ledger balances on account dashboard`).
- Files changed: `packages/workdo/Account/src/Http/Controllers/DashboardController.php`, `CHANGELOG.md`; `AGENTS.md` updated in the coordination worktree only.
- Database records/migrations affected: none.
- What was implemented: Account Dashboard Bank Balance now totals the signed closing balances of unique linked GL accounts for active checking/savings bank records. Legacy unlinked bank records retain their stored-balance fallback.
- Decisions and assumptions: the chart-of-account posted ledger is the source of truth; duplicate bank records linked to the same GL account must not double-count it; all active checking/savings accounts remain included, not only QIB.
- Tests run and results: PHP syntax passed; `git diff --check` passed; local integration reproduction showed stored bank balance `1,790.00`, linked ledger `1,640.00`, and the corrected dashboard returned `1,640.00`.
- Manual verification: backend calculation verified against the same posted-ledger formula used by the account detail page; no frontend files changed.
- Known issues or blockers: live Brill Creations amounts were not queried or changed; the branch must be deployed before checking the live `3,001.99` QIB figure.
- Deployment or rollback notes: no migration or data correction is required; rollback is reverting commit `4b20baff`.
- Files/areas still claimed: none; accounting fix is committed and ready for verification.
- Exact next step: deploy the pushed branch to a test/live environment, clear application cache, and verify the Bank Balance card against the sum of all active linked bank GL balances.

### 2026-10-26 Asia/Colombo - Codex - Security progress tracker document

- Branch: `security/settings-and-payload-hardening`.
- Commit: no new commit; latest security code commit remains `0a7d9dd9`.
- Files changed: `AGENTS.md`; created the external Word tracker `E:\Doha Workplace\ERP\Security Updates\Wazely ERP - Security Implementation Plan and Progress Tracker.docx`. Workspace-only authoring and render files are under ignored `tmp/docs/` and `output/docx/`.
- Database records/migrations affected: none.
- What was implemented: created a four-page implementation tracker covering all three security phases and six working days; completed items are highlighted lime green and pending items remain unhighlighted.
- Decisions and assumptions: progress markings reflect only verified work already completed on the security branch; production backup, explicit secret clearing, audit logging, integration tests, Phase 2, and Phase 3 remain pending.
- Tests run and results: generated the DOCX, rendered it through Microsoft Word, rasterized all four pages, and visually inspected every page; no clipping, overlap, broken tables, or repeated footer content remained.
- Manual verification: phase and day labels, status legend, lime-green completed rows, pending rows, completion criteria, and pagination verified in the final render.
- Known issues or blockers: none for the document; security implementation tasks remain as recorded in the tracker.
- Deployment or rollback notes: documentation only; no production or application state changed.
- Files/areas still claimed: Phase 1 security paths listed in Current Claims.
- Exact next step: continue Day 3 with explicit replace/clear secret actions, administrator authorization verification, and sensitive-setting audit logging.

### 2026-10-26 Asia/Qatar - Claude - Data correction phase 2 run on live

- Branch: none (scripts outside the repo).
- Commit: none.
- Files changed: `AGENTS.md` only.
- Database records/migrations affected: LIVE - see Claude Track Status (phase 2 row).
- What was implemented: user ran phase 2 locally (passed), checked live "before" (matched; 5 new live entries since the backup verified unrelated: new payment CP-2026-10-006 paid new invoice SI-2026-10-006), took a backup, ran the fix on live, and the live "after" check passed.
- Decisions and assumptions: as recorded for phase 2.
- Tests run and results: live after - unlinked payments 0, paid-vs-links mismatches 1 (INV-000142, expected), 25 credit notes applied with open balance 0, example invoices paid/0, BRINV-000071 3,000, INV-000365 gone, 24 CNA records with 0 cash, journals 3,577 and AR 129,645.90 unchanged.
- Manual verification: user's live check screenshots.
- Known issues or blockers: INV-000142 (needs Zoho PDF); vendor bills (29 "paid" with 19,204 balance) not reconciled yet.
- Deployment or rollback notes: rollback = restore the pre-fix phpMyAdmin backup (would also lose any entries made after it).
- Files/areas still claimed: data scripts outside the repo.
- Exact next step: INV-000142 fix once the PDF arrives; phase 3 vendor bills (Zoho bills export from Sithari likely needed).

### 2026-10-26 Asia/Qatar - Claude - Account Dashboard figures checked

- Branch: none (read-only investigation; repo on Codex's `security/settings-and-payload-hardening`, untouched).
- Commit: none.
- Files changed: `AGENTS.md` only.
- Database records/migrations affected: none.
- What was implemented: verified every Account Dashboard card against the books on the local copy; findings in Claude Track Status.
- Decisions and assumptions: books (journal lines) are correct; dashboard stored-balance fields are not.
- Tests run and results: SQL comparisons only - bank card = Banking running total from 0 on 1 Aug; cash card = stale stored value; AR/AP cards exclude "overdue"/"paid"-with-balance documents.
- Manual verification: Sithari confirmed the QIB account page (3,001.99) tallies with the bank.
- Known issues or blockers: fix is a code change to `DashboardController.php` / `ReportService.php` - not claimed; awaiting user decision. Vendor-side data not reconciled.
- Deployment or rollback notes: none.
- Files/areas still claimed: data scripts outside the repo.
- Exact next step: user decides on the dashboard code fix and a vendor-bill reconciliation; phase 2 live run still pending.

### 2026-10-26 Asia/Colombo - Codex - Compatible runtime and local payload verification

- Branch: `security/settings-and-payload-hardening`.
- Commit: no new source commit; latest remains `0a7d9dd9`.
- Files changed: `AGENTS.md` only; isolated PHP runtime/config and captured guest HTML are under ignored `tmp/`.
- Database records/migrations affected: none.
- What was implemented: installed an isolated official PHP 8.5.12 NTS x64 CLI under `tmp/php85` without changing XAMPP or system PATH, enabled required extensions, ran the focused payload tests, started the local ERP on `127.0.0.1:8000`, and inspected the guest login response for forbidden setting keys.
- Decisions and assumptions: PHP 8.5 satisfies the dependency requirement of PHP >=8.3; database-resetting feature tests will not run against the configured accounting database; authenticated verification will use safe targeted tests and a manual local session.
- Tests run and results: PHPUnit 12.5.9 on PHP 8.5.12: `BrowserSettingsPayloadServiceTest`, 2 tests/2 passed. Local `/login` returned HTTP 200 on PHP 8.5.12. SMTP, Pusher, storage and OAuth secret keys were absent; AI matches were only the safe `ai_api_key_configured` and `ai_agent_api_key_configured` boolean names.
- Manual verification: local server is listening on port 8000. Native browser automation failed to initialize because its kernel asset path was missing; no credentials were requested or inspected.
- Known issues or blockers: authenticated Settings and ordinary-page responses still require local-session verification; explicit clear actions and sensitive-setting audit records are not implemented yet.
- Deployment or rollback notes: no production changes. The isolated runtime can be removed by deleting ignored `tmp/php85` when no longer needed.
- Files/areas still claimed: Phase 1 paths in Current Claims.
- Exact next step: implement explicit clear controls and value-free sensitive-setting audit records, add focused tests, then verify authenticated browser payloads through the local app.

### 2026-10-26 Asia/Qatar - Claude - Data correction phase 2 built and dry-run

- Branch: none for this work (scripts outside the repo); repo currently on Codex's `security/settings-and-payload-hardening` working tree - not touched by Claude except this file.
- Commit: none.
- Files changed: `AGENTS.md` (claims, Claude Track Status, this entry). Outside repo: `ERP Data Fixes\Invoices - Phase 2\` (check, fix, README).
- Database records/migrations affected: none on live yet. Dry run only (throwaway DB `erp_phase2_test`, dropped afterwards). Affected-record counts in Claude Track Status.
- What was implemented: phase 2 correction script generated from Sithari's Zoho exports and Je's review, with exact-match guards and NOT EXISTS checks.
- Decisions and assumptions: credit applications stored like Wazely's own UI (0-cash customer payment + allocation + credit_note_applications); refunded credit-note amounts counted as applied with a note (Wazely has no refund record); INV-000365 deleted because Wazely has no void status; INV-000142 deferred.
- Tests run and results: dry run passed all checks; full 507-invoice comparison with Zoho = only INV-000142 differs (excluded); idempotent on re-run.
- Manual verification: pending - user local run, then live.
- Known issues or blockers: INV-000142 needs the Zoho PDF.
- Deployment or rollback notes: live run needs a phpMyAdmin backup first; rollback = restore that backup.
- Files/areas still claimed: data scripts outside the repo; Zoho import area planned.
- Exact next step: user runs `1-check` / `2-fix` / `1-check` locally, then on live.

### 2026-10-26 Asia/Colombo - Codex - Browser secret exposure protection

- Branch: `security/settings-and-payload-hardening`.
- Commit: `0a7d9dd9` (`Protect browser settings from secret exposure`), local only; remote branch not pushed yet.
- Files changed: `CHANGELOG.md`, `app/Services/BrowserSettingsPayloadService.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Controllers/SettingController.php`, AI Assistant controller/field/settings component, core AI Agent/email/Pusher/storage settings components, and `tests/Unit/BrowserSettingsPayloadServiceTest.php`.
- Database records/migrations affected: none.
- What was implemented: browser-shared admin/company settings now use explicit allowlists; unknown settings are blocked by default; SMTP, AI, Pusher, storage and OAuth secret values are absent from shared/settings payloads; configuration-state booleans replace secret values; blank secret inputs preserve existing values; AI Assistant visibility uses a configured flag rather than receiving its API key.
- Decisions and assumptions: access-key identifiers may be shown only on the permission-protected settings page, while secret values are never returned; credential clearing will require a separate explicit action rather than treating blank as clear.
- Tests run and results: PHP syntax passed for all changed PHP files; `git diff --check` passed; `npx tsc --noEmit` passed; `npm run build` passed (3,999 modules, 2m31s). Focused PHPUnit tests were added but could not execute because local PHP is 8.2.12 and installed Composer dependencies require PHP >=8.3.
- Manual verification: source-level payload inspection completed; no real secret values were read or printed. Browser workflow verification is still pending.
- Known issues or blockers: use PHP 8.3 to run PHPUnit; remote branch is not pushed; settings-page and ordinary-page browser responses still need local runtime inspection.
- Deployment or rollback notes: no production changes; rollback is commit `0a7d9dd9^` or reverting this isolated commit before deployment.
- Files/areas still claimed: Phase 1 paths in Current Claims.
- Exact next step: run the local app with PHP 8.3, execute the focused tests, inspect Inertia/browser payloads for normal and settings pages, then add explicit replace/clear handling and sensitive-settings audit records before Phase 2.

### 2026-10-26 Asia/Colombo - Codex - Security Phase 1 kickoff

- Branch: `security/settings-and-payload-hardening`, created from deployed commit `10a5f68f`.
- Commit: none yet.
- Files changed: `AGENTS.md` only.
- Database records/migrations affected: none.
- What was implemented: activated the security workstream, claimed the Phase 1 files, and completed the initial code/test inventory without reading or printing stored secret values.
- Decisions and assumptions: use explicit browser-safe allowlists rather than a secret-key denylist; preserve unrestricted settings only for server-side consumers; keep Claude's data-correction and planned import areas untouched.
- Tests run and results: baseline code inspection confirmed authenticated pages still receive unrestricted `companyAllSetting`; SMTP and AI settings forms preload stored secrets; Pusher and storage settings also require masking. No application tests run yet.
- Manual verification: repository is on the dedicated security branch; tracked files were clean before branch creation. Existing untracked generated and working folders were left untouched.
- Known issues or blockers: remote tracking branch is not created yet; local PHP test compatibility must be checked before the first test run.
- Deployment or rollback notes: no application or production changes have been made.
- Files/areas still claimed: Phase 1 paths listed in Current Claims.
- Exact next step: add failing feature/unit tests for safe settings payloads, then implement the centralized context-specific settings allowlist and update `HandleInertiaRequests.php`.

### 2026-10-26 Asia/Qatar - Claude - Zoho exports from Sithari reconciled

- Branch: `bill-line-expense-accounts` (no repo code edited).
- Commit: none (latest `10a5f68f`).
- Files changed: `AGENTS.md` (Claude Track Status + this entry).
- Database records/migrations affected: none - read-only comparison on local `erp_live_copy`.
- What was implemented: compared Sithari's Zoho invoice list and credit-note applications with Wazely; results in Claude Track Status.
- Decisions and assumptions: Zoho invoice list is authoritative up to the 24 Aug 2026 go-live; later Wazely payments win over Zoho "overdue".
- Tests run and results: all 45 will-be-fixed invoices confirmed by Zoho; credit notes tie out to applications + refunds to the cent.
- Manual verification: none yet.
- Known issues or blockers: INV-000365 decision; Je's review still pending.
- Deployment or rollback notes: n/a.
- Files/areas still claimed: data scripts outside the repo.
- Exact next step: write phase 2 (link remaining payments, apply credit notes, fix CN-00031 / BRINV-000071 / INV-000142 totals), dry run on a fresh live backup, then live after approval.

### 2026-10-25 Asia/Qatar - Claude - Status recorded, data correction phase 1

- Branch: `bill-line-expense-accounts` (unchanged; no repo code edited this session).
- Commit: latest `10a5f68f` (bill line accounts, journal line contacts, consistent line discounts) - pushed and deployed to live 2026-10-25 with `migrate --force`; migrations 2026_10_22_000001, 2026_10_24_000001, 2026_10_24_000002 confirmed in live `migrations` table.
- Files changed: `AGENTS.md` only (this update). Data scripts outside the repo: `ERP Data Fixes\Botanico\`, `ERP Data Fixes\Je Invoices - Phase 1\`, `ERP Data Fixes\Invoice Balances\`.
- Database records/migrations affected: live - Botanico payment 1088 + INV-000533; Je phase 1 records listed in Claude Track Status. No journals changed (3,572 before and after).
- What was implemented: Botanico and Je phase 1 corrections on live; full invoice-vs-ledger comparison; review workbook `Invoice Recheck.xlsx` for Je.
- Decisions and assumptions: Zoho ledger journals are the source of truth; corrections never add journals; Je's invoices fixed first, the rest after her review (phase 2); INV-000388 deleted on Je's written instruction.
- Tests run and results: local dry runs on `erp_live_copy` and a throwaway copy, before/after checks, idempotency re-run - all passed; user ran the same checks on live - passed.
- Manual verification: user checked INV-000533 on live (Paid, balance 0) and the phase 1 check script on live.
- Known issues or blockers: waiting for Je's review and Sithari's credit note export.
- Deployment or rollback notes: rollback = restore the phpMyAdmin backup taken before each fix.
- Files/areas still claimed: data scripts outside the repo; Zoho import area planned (see Current Claims).
- Exact next step: phase 2 once reviewer data arrives; then start the Zoho import redesign on a new branch `zoho-import-redesign`.

### 2026-10-25 - Codex - Coordination Setup

- Branch: `bill-line-expense-accounts` at the time this file was created.
- Commit: Not committed yet.
- Changes: Created the shared parallel-agent coordination protocol and recorded the planned security phases plus Claude's data/import tracks.
- Tests: Documentation-only change; no application tests required.
- Decisions: Security implementation is six working days across all three phases. Data correction and Zoho import work remain separate and can proceed in parallel.
- Blockers: Claude must record its actual branch, exact file claims, current progress, tests, and latest commit before continuing implementation.
- Next: Claude updates this file first; Codex later creates the dedicated security branch and claims files before security implementation begins.

## Handoff Entry Template

Copy this block, place the new entry above older entries, and complete every field:

```text
### YYYY-MM-DD HH:MM TZ - Agent - Short Task Name

- Branch:
- Commit:
- Files changed:
- Database records/migrations affected:
- What was implemented:
- Decisions and assumptions:
- Tests run and results:
- Manual verification:
- Known issues or blockers:
- Deployment or rollback notes:
- Files/areas still claimed:
- Exact next step:
```
