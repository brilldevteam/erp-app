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

Add newest entries first. Do not remove earlier entries.

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
