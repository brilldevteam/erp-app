# Zoho Books Direct Import and AI-Assisted Migration Plan

## 1. Objective

Build a controlled migration workspace that accepts unchanged Zoho Books CSV/XLSX exports and imports them into Wazely ERP. The process must be tenant-isolated, deterministic, reviewable, idempotent, reversible during the pilot, and reconciled against Zoho control reports.

AI may rank ambiguous matches and propose missing master records. It must never invent financial values, write directly to ERP tables, or approve its own proposal.

## 2. Non-Negotiable Rules

1. Preserve uploaded Zoho files unchanged and record their hashes.
2. Stage and validate everything before creating ERP records.
3. Match Zoho external IDs first and exact document numbers second. Never extract document numbers with a loose regex.
4. Never import invoice or bill `paid_amount` as authoritative. Derive paid amount, balance, and status from allocations and credit applications.
5. Never guess a missing allocation, account, tax, currency, exchange rate, warehouse, or dependency.
6. Never mix accounting modes in one migration batch.
7. Historical documents before inventory cutover create no stock movement and no inventory/COGS journal.
8. Every AI suggestion requires human approval.
9. A failed document blocks its dependent payments, credits, and applications.
10. Successful row insertion is not completion; all required reconciliation gates must pass.

## 3. Ownership

### Claude: Data, Mapping, and Reconciliation

- Collect and sanitize representative Zoho exports.
- Maintain the export-profile and column-mapping catalogue.
- Define transformation rules and unsupported fields.
- Build expected-result fixtures from approved source files.
- Verify document, allocation, AR/AP, Trial Balance, and stock reconciliation.

### Codex: Application and Security

- Build the migration workspace, staging schema, orchestration, queues, permissions, and UI.
- Implement tenant-scoped matching, provenance, idempotency, rollback, and sequence protection.
- Implement safe AI integration, audit records, data minimization, and browser-secret protection.
- Integrate staged data with ERP domain services without duplicate journals or stock effects.
- Add automated tests and deployment controls.

Before editing shared import, accounting, selector, test, or changelog files, the agent must claim the exact paths in `AGENTS.md`.

## 4. Current Code Baseline

The existing system provides `BulkImportRegistry`, `EntityDefinition`, `SpreadsheetService`, 24 entity definitions, upload/mapping/validation/confirmation controllers, and transactional processing jobs.

It is not yet suitable for direct Zoho migration because:

- Sales and purchase invoice definitions accept spreadsheet `paid_amount` values.
- Invoice definitions leave warehouses unresolved in important paths.
- There is no durable cross-file staging model or dependency graph.
- Validation and processing are currently dispatched synchronously.
- There is no migration-wide provenance, accounting-mode lock, reconciliation, or whole-batch undo.
- The current 10,000-row limit is per file, while Zoho line exports may split a document.

Existing parsers and validators may be reused, but no definition is considered Zoho-ready until verified against this plan and sanitized export fixtures.

## 5. Batch Configuration

An authorized company administrator creates a batch containing:

- Company/tenant and Zoho organization IDs
- Zoho locale, date format, fiscal year, and base currency
- Migration scope
- Inventory cutover timestamp
- One accounting mode
- Mandatory default warehouse for product documents without a trustworthy source warehouse
- Duplicate strategy: block, link, or approved update
- AI opt-in and provider policy
- Created, reviewed, approved, executed, and undone users/timestamps

After final validation, company, accounting mode, base currency, and cutover become immutable. A change requires a new validation version or batch.

## 6. Accounting Modes

### Mode A: Full History With Zoho Ledger

- Import operational documents and settlements without generating Wazely journals.
- Import the complete approved Zoho General Ledger as the accounting source of truth.
- Preserve Zoho transaction IDs, dates, accounts, descriptions, currencies, exchange rates, debits, and credits.
- Reject any document path that unexpectedly creates a journal.
- Historical documents create no stock movement and no inventory/COGS journal.

Use this when Wazely posting rules cannot reproduce Zoho exactly because of account, tax, discount, rounding, inventory, or COGS differences.

### Mode B: Rebuild From Documents

- Import documents and settlements through approved Wazely posting rules.
- Do not import Zoho-generated journals for those transactions.
- Import only genuinely manual journals and explicitly approved opening balances.
- Use Zoho's Trial Balance at cutover as the accounting pass/fail control.
- Block a source transaction represented by both a document and a manual journal.

### Opening Balances

Opening balances cannot cover accounts populated by imported activity. AR, AP, bank, cash, tax, inventory, COGS, revenue, and expense accounts require an explicit overlap check. The review must show every account receiving an opening balance and the reason.

## 7. Required Logical Datasets

Zoho filenames vary by edition, locale, and report options. Versioned profiles must detect datasets from headers and metadata, while recording accepted filename aliases.

### Organization and Masters

- Organization settings: ID, base currency, locale, date format, fiscal year, and tax mode
- Contacts: contact ID/type, names, email, tax treatment, currency, and addresses
- Items: item ID, SKU, name, product/service type, unit, taxes, rates, and linked accounts
- Taxes and tax groups
- Chart of Accounts
- Warehouses and cutover stock snapshot
- Currencies and exchange rates used by transactions

### Sales and Receivables

- Quotes/estimates with lines
- Invoices with lines
- Payments Received
- Customer payment allocations: payment ID, invoice ID/number, and applied amount; multiple rows may share one payment
- Credit Notes with lines
- Credit-note applications: credit ID, invoice ID/number, date, and amount
- Credit-note refunds: credit ID, refund ID/date, amount, and refund account
- Invoice Details control report: total, balance due, and status per invoice
- AR Aging at the agreed cutover

### Purchases and Payables

- Bills with lines
- Vendor Payments
- Vendor payment allocations: payment ID, bill ID/number, and applied amount
- Vendor Credits with lines
- Vendor-credit applications: credit ID, bill ID/number, date, and amount
- Vendor-credit refunds where used
- Bill Details control report: total, balance due, and status per bill
- AP Aging at cutover

### Banking and Accounting

- Every bank, cash, petty-cash, credit-card, partner-current, payable-clearing, `Paid Through`, and `Deposit To` account
- Bank transfers, expenses, revenues, and petty-cash transactions in scope
- Manual journals
- General Ledger for Mode A
- Trial Balance at cutover for both modes

If a required dataset is missing, stale, incorrectly filtered, or unsupported, confirmation is blocked. The importer reports the exact missing dataset/columns and never infers allocations from balances.

## 8. File Intake and Staging

1. Upload CSV/XLSX files to private tenant storage.
2. Validate extension, MIME, size, workbook structure, encoding, and malware policy.
3. Calculate SHA-256 and detect duplicate uploads.
4. Detect the dataset and profile from headers and metadata; require confirmation when ambiguous.
5. Parse dates using the Zoho organization's format and preserve UTF-8/Arabic text with `utf8mb4`.
6. Store file, sheet, row, original values, normalized values, and validation state.
7. Assemble line exports into complete source documents before validation.

The initial 10,000-row-per-file limit may remain only if files split at complete-document boundaries, or all parts are staged and grouped by immutable Zoho document ID before validation. Missing parts block confirmation.

Formula cells, hidden sheets, malformed rows, unexpected columns, and duplicate headers must be reported. Spreadsheet content is untrusted data and cannot become AI instructions or executable formulas in reports.

## 9. Durable Data Model and Provenance

Create tenant-scoped records for:

- Migration batches and immutable configuration versions
- Uploaded files, hashes, profiles, and processing states
- Staged rows and assembled documents
- Source references and dependency edges
- Match proposals and approvals
- Validation issues and resolutions
- Import actions and destination IDs
- Reconciliation results
- Undo actions and exceptions

Every destination record must trace to company, batch, source system, Zoho organization, entity type, external ID, file/sheet/rows, source snapshot/hash, destination table/ID, action, actor, and timestamp.

Use tenant-scoped unique constraints for source external IDs. Re-runs must link, skip, or apply an explicitly approved update, never silently duplicate.

## 10. Matching and Duplicate Policy

Use this order:

1. Existing provenance for tenant, Zoho organization, entity type, and external ID
2. Exact stored Zoho external ID
3. Exact document number inside the correct tenant/type
4. Exact normalized stable key: SKU, account/tax code, account number, or valid email
5. Deterministic normalized-name candidates
6. Fuzzy candidate generation
7. AI ranking of a small candidate list
8. Human approval or approved master creation

Rules:

- `INV-000029`, `BRINV-000029`, and `RAPPINV-000029` are different numbers.
- Normalization can discover candidates but cannot alter stored source values.
- Near-duplicate names enter review.
- A payment/customer or vendor-payment/vendor mismatch is blocking.
- Cross-company candidates are never returned or accepted.
- Duplicate external IDs with different financial content block the batch.
- Updates require a field-level before/after preview.

## 11. AI-Assisted Resolution

AI is optional per company and batch. A dedicated resolution service must:

- Send only unresolved fields and a small tenant-scoped candidate list.
- Exclude secrets, attachments, unrestricted notes, and unnecessary personal/financial data.
- Treat spreadsheet text strictly as data.
- Use a versioned system prompt and strict structured JSON schema.
- Record provider, model, prompt version, request hash/field names, response, confidence, latency, and approving user without recording secrets.
- Support timeout, retry, rate limiting, and manual fallback.

AI may propose `match_existing`, `create_new`, or `needs_review` with candidate ID, proposed fields, confidence, reason, and review fields.

AI cannot write records, invent financial data, choose accounts or accounting mode, override validation/security, or silently resolve conflicting/low-confidence matches.

## 12. Dependency Waves

### Wave 0: Configuration

Confirm organization settings, accounting mode, scope, cutover, date format, source checklist, control-report dates, and account/tax/status mappings.

### Wave 1: Masters

Import account types/COA, taxes, units/categories, currencies/rates, warehouses, contacts, items/services, bank/payment accounts, and revenue/expense categories. No later wave runs with unresolved required masters.

### Wave 2: Documents

Import quotations, invoices, bills, credit notes, and vendor credits. Each assembled document is atomic and preserves exact number/ID, dates, currency/rate, line ordering/descriptions, quantity/rate, taxes, discounts, adjustments, notes, and mapped status.

### Wave 3: Settlements and Inventory

Import payments once with child allocations, credit applications/refunds, bank transfers, expenses/revenues/petty cash, and the cutover stock snapshot. A failed target document holds dependent records unless an explicitly supported partial-scope migration is approved.

### Wave 4: Accounting

- Mode A: approved complete Zoho ledger with document journal generation disabled.
- Mode B: approved manual journals and non-overlapping opening balances after document posting.

Each wave has independent validation, approval, execution, and reconciliation gates.

## 13. Financial Rules

### Documents and Settlements

- Ignore source `paid_amount` as an instruction.
- Derive paid, balance, and status after allocations/credits.
- Preserve line versus document discounts and fixed versus percentage discounts.
- Capture discount before/after tax and inclusive/exclusive tax pricing.
- Preserve adjustment/rounding values and currency precision.
- Validate subtotal, discount, tax, adjustment, total, and balance.
- Do not spread document discounts across lines unless an approved rule preserves exact reconciliation.

### Statuses

Wazely invoices/bills support `draft`, `posted`, `partial`, `paid`, and `overdue`, not `void`.

- Draft imports without accounting/stock effects.
- Posted/open/overdue maps from balance and due date.
- Partial/paid derives from allocations, not source text alone.
- Void is preserved in staging/provenance and skipped with reason until an explicit void representation exists.
- Unknown statuses block for mapping.

### Payment Accounts

Every Zoho payment/refund account maps to a tenant-owned Wazely bank/payment record linked to the correct GL account. Import must not depend on the Bank Account UI's current 1000-1099 GL filter. Missing or ambiguous GL links block dependent transactions.

### Inventory

- Products require an approved warehouse; services do not.
- Use exact trustworthy source warehouse, otherwise the batch default.
- Import current stock as an approved cutover snapshot.
- Pre-cutover documents create no stock movement or inventory/COGS journal.
- Post-cutover documents follow normal ERP behavior when in scope.
- Negative stock follows company policy but is always reported before approval.

## 14. Unsupported Scope Register

Every field/type is marked supported, transformed, preserved-only, or unsupported. Initial separately planned areas include sales orders, purchase orders, recurring transactions, retainers, attachments, arbitrary custom fields, fixed assets/depreciation, projects/timesheets, and unsupported write-offs/refunds/regional tax documents.

Unsupported data is never silently dropped. The dry run reports count, value where meaningful, rows, and selected action: exclude, preserve, map manually, or postpone.

## 15. Blocking Validation

Block confirmation for:

- Missing required datasets/control reports or incorrect cutover scope
- Unsupported/ambiguous profiles or incomplete split documents
- Unresolved/cross-company references
- Conflicting external IDs or exact document numbers
- Invalid dates, decimal formats, currencies, rates, taxes, or account codes
- Missing exchange rates or GL links
- Unbalanced journals
- Over-allocated payments/credits or party mismatch
- Missing warehouse or inconsistent product/service type
- Invalid quantities outside approved policy
- Totals not reproduced from lines, tax, discounts, adjustments, and rounding
- Duplicate/omitted journal behavior for the selected mode
- Prohibited historical stock/COGS effects

Warnings require explicit acknowledgement and cannot hide financial uncertainty.

## 16. Processing, Retry, and Undo

- Use durable queued jobs, not request-bound synchronous processing.
- Lock by tenant, batch, entity, and source external ID.
- Import complete documents and children atomically.
- Persist dependency states so retry resumes safely.
- Pause on systemic errors and sanitize user-facing failures/logs.

For the pilot, provide whole-batch undo using provenance:

1. Require administrator permission, backup confirmation, and preview.
2. Refuse automatic undo when later edits/dependencies exist; report them.
3. Reverse/delete in dependency order.
4. Restore sequences only when safe.
5. Reconcile after undo while retaining audit history.

Undo does not replace a production backup.

## 17. Number Sequence Protection

- Preserve source numbers exactly.
- Distinguish Wazely-compatible sequences from foreign prefixes.
- Advance the relevant tenant sequence beyond compatible imported numbers.
- Enforce tenant-scoped uniqueness when writing.
- Preview the next generated number.
- Block completion if normal creation could collide.

Never infer a sequence with substring matching that confuses prefixed families.

## 18. Reconciliation Gates

### Per Document

Compare exact ID/number, party, date, currency, total, balance due, derived status, payment allocations, and credit applications/refunds against Zoho Invoice/Bill Details. Unapproved differences block completion.

### Subledger and Accounting

- Document balances equal payment and credit allocations.
- AR and AP Aging match Zoho by bucket and total at cutover.
- Trial Balance matches by mapped account/currency.
- Debits equal credits.
- Mode A has no Wazely-generated document journals.
- Mode B has no duplicate Zoho-generated journals.
- Bank/cash balances match approved controls.

### Inventory and Completeness

- Item/warehouse stock equals the approved cutover snapshot.
- Pre-cutover documents produced no stock or inventory/COGS effects.
- Accepted, rejected, skipped, duplicate, warning, and failed counts are reported.
- No unresolved references/unreviewed AI proposals or duplicate entities remain.

Final states:

- `completed`: all required gates pass.
- `completed_with_approved_exceptions`: only authorized non-financial scope exceptions remain.
- `failed_reconciliation`: held for correction or rollback.

Financial mismatches can never be downgraded to generic warnings.

## 19. Workspace Pages

Provide batch setup, required-file checklist, upload/profile detection, mapping/unsupported review, master/AI review, dependency-aware validation, dry-run financial summary, approval/progress, reconciliation drill-down, downloadable outcome reports, audit history, and controlled undo.

Display source values beside proposed ERP values. Permissions protect approvals/actions. Browser payloads contain no secrets or unnecessary complete user/customer records.

## 20. Security and Privacy

- Enforce tenant ownership on every batch, file, staged row, candidate, resolution, destination ID, and report.
- Separate view, upload, map, approve, execute, undo, and audit permissions.
- Use private storage and short-lived authorized downloads.
- Keep API keys server-side and encrypted/configured under the security plan.
- Avoid full confidential payloads in logs; apply retention/deletion policies.
- Prevent formula injection in reports.
- Rate-limit upload, AI, validation, execution, and report endpoints.
- Audit security-relevant actions.

## 21. Testing

Automated fixtures must cover profile variants, locale dates/decimals, Unicode/Arabic, split multi-line documents, exact prefixed numbers, similar names, party mismatches, all discount/tax modes, rounding, multi-currency, multi-document payments, partial/overpayments, unapplied amounts, credit applications/refunds, both accounting modes, historical inventory suppression, cutover stock, duplicate/retry/idempotency behavior, sequence collisions, permissions/tenant manipulation, AI failure/prompt injection, interrupted jobs, and undo with/without later dependencies.

The pilot must use sanitized real exports on isolated database copies, compare every document/control report, verify normal ERP workflows after import, rerun the identical batch to prove no changes, test undo/re-import, and obtain finance plus technical approval.

## 22. Delivery Phases

### Phase 1: Discovery and Contracts

Collect sanitized samples/control reports; freeze supported scope, profile catalogue, mappings, accounting contracts, side-effect controls, and expected reconciliation fixtures.

Exit: every supported dataset has headers, mapping, validation, duplicate behavior, dependencies, side effects, and expected results.

### Phase 2: Foundation

Build batches, uploads, staging, provenance, dependency graph, permissions, queues, audit/reporting, profile detection, assembly, and deterministic matching.

Exit: complete validation without writing ERP records.

### Phase 3: Masters and AI Review

Implement master/payment-account mapping, candidate review, approved creation, and opt-in audited AI ranking.

Exit: downstream references resolve through approved tenant-owned masters.

### Phase 4: Documents and Inventory

Implement documents, discounts/taxes, statuses, warehouse/cutover behavior, and sequence protection.

Exit: source document totals match exactly before settlements.

### Phase 5: Settlements and Accounting

Implement payments, allocations, applications/refunds, bank activity, and both accounting modes with overlap prevention.

Exit: document balances, AR/AP, bank/cash, and Trial Balance reconcile.

### Phase 6: Rollback, Hardening, and Pilot

Implement undo; finish security, performance, retry, failure, and regression testing; pilot with copied data; document deployment/monitoring/recovery.

Exit: finance and technical reviewers approve exact pilot reconciliation.

## 23. Required Mapping Catalogue

Before coding a dataset, document its accepted export aliases, required/optional columns, external-ID and grouping keys, target fields, date/decimal/currency/tax/discount/status transformations, matching order, duplicate/update behavior, validations, dependencies, accounting/inventory effects, rejection behavior, controls, unsupported fields, and sanitized expected-result fixtures.

## 24. Definition of Done

The feature is complete only when unchanged supported exports work; every row traces to a destination/rejection; dependencies are enforced; AI is optional/review-only/audited; accounting modes cannot mix; paid/balance derives from allocations; inventory respects cutover; per-document and AR/AP/TB/bank/stock controls pass; re-runs are idempotent; sequence collisions are prevented; pilot undo works; tenant/security tests pass; unsupported scope is explicit; and tests, changelog, deployment instructions, monitoring, and `AGENTS.md` handoff are complete.

## 25. Immediate Next Step

Do not start migrations or UI code yet. Complete Phase 1 first: collect sanitized samples for every required logical dataset, record exact headers/relationships in the mapping catalogue, choose the first pilot's accounting mode, inspect existing definitions/domain posting services against those contracts, and claim exact files in `AGENTS.md` before editing.
