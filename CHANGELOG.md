# Changelog

All notable changes to the Wazely ERP application are documented here.

## [Unreleased]

### Added
- Multi-currency transaction forms now provide a searchable currency picker that filters by currency name or ISO code.
- Completed multi-currency accounting for sales and purchase documents, receipts, payments, credit notes and debit notes: immutable base/transaction currency snapshots, party preferred currencies, mandatory manual foreign rates, currency-matched allocations and bank accounts, realized exchange gain/loss posting, allocation carrying/settlement values, and dual-currency document totals. Existing posted ledger values are preserved, and Zoho imports retain their historical currency snapshots without manufacturing settlement differences.
- Purchase invoices now keep a free-text description per line plus optional Supplier Invoice Number and Purchase Order Number references; sales invoices now keep an optional Sales Order Reference. The fields flow through editing, searching, views, PDFs, duplication and imports.
- Added independent Download sales invoice and Download purchase invoice permissions. Download controls and direct PDF routes now enforce these permissions while allowing authorized downloads in every document status.
- Company users can configure exchange gain and loss accounts from Currency Rates. Customer and vendor forms can set a preferred document currency.

### Changed
- Paying a foreign-currency invoice or bill keeps the payment amount in the document currency (USD 273.97 stays USD 273.97) whatever exchange rate is entered; only the base-currency equivalent changes, and the difference posts as exchange gain or loss. Adding a foreign invoice or bill switches the payment to its currency, invoices in another currency cannot be mixed in, and the server compares allocations in the payment currency, so payments at a lower rate (an exchange loss) are no longer rejected. Beside the base-currency equivalent, the form shows the resulting exchange gain in green or exchange loss in red. A foreign-currency payment can be received into or paid from a base-currency bank or cash account; that account's register moves by the base-currency equivalent. Accounts without a number no longer show empty brackets in the payment forms. In the customer and vendor payment lists, a foreign-currency payment shows its base-currency amount under the payment amount, green for an exchange gain and red for a loss.
- Customer and vendor payment forms show foreign-currency invoice, bill, credit note and debit note balances like the invoice lists: the base-currency amount first with the document currency underneath, instead of the foreign amount with the base currency symbol.
- Foreign-currency sales and purchase invoice PDFs and print views now show every amount in the invoice currency (for example USD 273.97) instead of the company currency symbol.
- Foreign-currency sales and purchase invoices whose prices are entered in base currency now keep the entered base total exactly: the stored rate is the base total over the rounded document total (QAR 1,000 at 3.65 is USD 273.97 at 3.65003468), so lists, journals and payments show QAR 1,000.00 instead of 999.99.
- Sales and purchase invoice lists now show company base-currency amounts first and place the selected foreign document currency underneath in smaller text for multi-currency transactions.
- Migration setup now requires the default warehouse's real address, city and postal code and accepts optional phone/email, matching normal warehouse creation instead of saving placeholder location text. Reusing a legacy placeholder warehouse repairs only its missing location fields.
- Sales and purchase invoice line inputs remain in the company base currency; foreign-currency totals are derived by dividing by the entered historical rate, and the backend stores the converted document values while retaining the base snapshot.
- QAR invoice amounts use the configured Qatari Riyal symbol instead of the `QAR` code, and base-equivalent hints are hidden when the transaction already uses the company base currency.
- Sales and purchase invoice line totals and create/edit summaries now use the selected transaction currency, with the base-currency equivalent shown separately beneath the total.
- Purchase invoice line controls now align to the top of each row, matching sales invoices when an item description makes the row taller.
- Renamed the visible Double Entry module label, headings and breadcrumbs to Reports while retaining all internal routes, permissions and technical accounting names.
- Bulk Import upload guide (`ZohoUploadGuide`): every upload step lists the file it takes, with where to export it in Zoho Books and what to watch for. Steps that take several files (Credit applications, Final reconciliation) list them in order, marked required or optional, and show which are attached. Choosing a file first opens this guide; the file picker opens from it.
- Files can be removed from a step before it is imported (new `DELETE zoho-migrations/{batch}/files/{file}` route, with confirmation). The file, its rows and its issues are deleted, and the step's other files are checked again. Removing a newer export of an imported Expenses step returns the step to its import. Removal is refused for imported files, imported steps, and while a check or import is running. An imported step no longer offers an upload, except Expenses.
- A file that is not a Zoho report this step takes, added to a step that takes several files (Credit applications, Final reconciliation), is now set aside with a message in the activity list. The step's other files are kept and re-checked; previously the whole step showed "Failed".
- The Expenses step can take a newer full Zoho export after it was imported (new `top_up` stage flag; the upload button reads "Upload newer export"). Expenses imported earlier are recognised by their number and Zoho journal, checked against the export (amount, date, expense account, paid-through account) and skipped. Only new expenses are imported. An imported expense that changed in Zoho, or that is missing from the newer export, is reported instead of being silently left out. An export with nothing new completes the step without writing anything. Rehearsed on Brill Creations' fresh export: 1,567 lines skipped and the 27 newer expenses (40,513.03) imported. With the discount repost, the Final reconciliation then matches Zoho on all 102 checks. Later steps that show as locked while the step is redone no longer get a false "imported successfully" message when they unlock again.
- Made the Final reconciliation step executable (`ZohoReconciliationService`). It changes no data. It takes Zoho's Trial Balance report and, optionally, the AR and AP Aging Summary reports. Uploads now keep a Zoho report's title row (new `report_title` column; run `php artisan migrate`), which gives the report's date.
  - **Account by account.** Every ERP account balance on that date is compared with Zoho: Zoho's Accounts Receivable / Payable against the control accounts 1100 / 2000, and Bad Debt against 5700. "Total for …" subtotal rows are skipped.
  - **"As of" reports** show income and expenses for the current year only, so earlier years are closed into Retained Earnings on the ERP side too. A "From … To …" report must start at or before the first transaction.
  - **Unlisted accounts.** ERP accounts with a balance that Zoho does not list are reported.
  - **Customers and vendors.** Each open balance (open invoices or bills less open credits) is compared with the aging totals.
  - **Confirming.** The step is confirmed ("Confirm reconciliation") only with no difference left.
  - Rehearsed on Brill Creations: the AR and AP aging match for all 20 customers and Newoon. The only trial balance differences left are the item discounts on invoices imported before discount posting was added, and the 27 expenses missing from the older expense export, each reported to the cent.
- The Warehouse stock step now lists every stock-tracked product in ERP (services hold no stock) with a quantity box per warehouse, pre-filled with any stock already recorded, instead of a paste box. Only the quantities typed in are imported, through the existing stock checks. A services-only company completes the step with `No stock to enter`, which is refused while ERP has any stock-tracked item.
- Made the Credit applications and refunds step executable (`ZohoCreditApplicationService`). It closes the credit notes and vendor credits that steps 14/15 import open, the way Zoho used them.
  - **Sources.** The step takes Zoho's Refund History report (new `refund_history.v1` profile) and, only when needed, a credit split sheet in Zoho's "Credit Applied Invoices" layout (Credit Note Number, Invoice Number, Amount Credited). Credit notes, vendor credits, invoices and bills are read from the exports already uploaded in steps 12–15.
  - **Refunds** post Dr receivables 1100 / Cr the refund bank account, with the bank register line.
  - **Splits.** What each credit gave each invoice comes from the split sheet first, then is worked out while exactly one answer exists. A credit with one open invoice left gives it what it has left; an invoice with one credit left takes what it still needs to reach its Zoho balance. A split that cannot be worked out is reported and never guessed.
  - **Recording.** Applications are recorded as the ERP records them: a zero-cash payment (`CNA-<credit note>` / `DNA-<debit note>`) allocating the credit to its invoices or bill, with the credit note or debit note application, and no journal. The import refuses to finish unless every credit, invoice and bill it touches ends at its Zoho balance.
  - **Several files per step.** Steps can now take several kinds of file (`multi_source`): a new upload replaces only the earlier file of the same kind, and the page lists each current file.
  - Rehearsed on Brill Creations' data: 25 credit notes (149,057.50 applied to 28 invoices, 4,450 refunded) and 3 vendor credits (62,535) close. CN-00010's split is worked out from CN-00004's, and every invoice then equals its Zoho balance.
- Imported invoices and customer credit notes now post as Zoho does: revenue at full price, with item discounts on Zoho's Discount account. A full rehearsal of steps 18 → 22 on Brill Creations' exports matches Zoho's Detailed General Ledger on all 120 accounts to the cent, apart from the 27 expenses missing from the older expense export.
- Made the Manual journals step executable from the Zoho Journals export (new `journals.v1` profile, ranked above the generic trial-balance profile it also matches). Each published Zoho journal posts as an ERP manual journal entry, listed on the Journal Entries page with its Zoho number (BJV…) as the reference.
  - Cash-basis-only journals are skipped and listed, as they are absent from Zoho's accrual ledger.
  - Lines on Zoho's Accounts Receivable / Accounts Payable (identified by account type in the step 2 Chart of Accounts export) post to the control accounts the invoices and bills use (1100 / 2000), with their customer or vendor.
  - Zoho's built-in `Bad Debt` account, which has no code, posts to ERP 5700 Bad Debt Expense.
  - Lines on a bank account's ledger account add the bank register line.
  - Bills Zoho shows as paid but still open in ERP are settled by the vendor's Accounts Payable journal debit that paid them (exact amount, dated on or after the bill) and noted as paid by that journal. A paid bill no journal explains is reported instead of left open.

  Rehearsed on Brill Creations' export: 845 journals (3,710,218.84) match Zoho on all 111 accounts to the cent. INV 163/2026 and INV 293/2026 are settled by BJV937 / BJV978, leaving only INV 359/2026 open, as in Zoho.
- Made the Bank transfers and petty cash step executable from Zoho's Detailed General Ledger report (new `ledger_report.v1` profile). Zoho reports put a one-cell title above their column headers, so uploads now read headers from row 2 when row 1 is a lone title. Report rows are one account leg each; they are paired by Zoho transaction id and their accounts matched through the Zoho account id in the migration's Chart of Accounts export, not by name (the ERP chart holds ERP default and Zoho accounts with the same names). Only bank activity no other step imports is taken, and everything else in the report is skipped:
  - transfers between two ERP bank accounts (cash, petty cash and credit card included) become completed ERP bank transfers (`ZTRF-<Zoho id>`) with their journal and both bank register lines;
  - transfers to or from other accounts (partner loans, staff accounts), owner contributions and expense refunds post as journals with their bank register line, since the ERP has no screen for them;
  - other and interest income become posted ERP revenues, with the revenue category created if missing.

  A transfer with only one side in the file (a single account's transactions report) is refused. Rehearsed on Brill Creations' ledger: 123 transactions (257,352.77): 106 transfers, 3 owner contributions, 12 expense refunds and 2 income items. These match the Zoho ledger on all 19 accounts to the cent.
- Made the Revenues and expenses step executable as `Expenses` (Zoho has no separate revenue transactions; its other income arrives through invoices, deposits and journals). Uses the new `expenses.v1` profile for the Zoho Expenses export. Every Zoho expense line becomes a posted ERP expense (`ZEXP-<entry number>`, or `ZEXP-<entry>-<line>` for itemized expenses, since an ERP expense has one account). Each line posts its own balanced journal (debit the Zoho expense account, credit the paid-through account's ledger account), adds a bank register line and updates the bank balance, as ERP expense posting does. Categories are matched by Zoho account name. Expenses booked to non-expense Zoho accounts (Salary Payable, Staff Advance, employee loans, fixed assets, …) get an expense category created for that account, coded with the Zoho account code and linked to it. Expenses paid through a non-bank account (Salary Payable, a staff current account) use an internal clearing bank account. Taxed and billable expenses are refused rather than imported incompletely. Bank matching, clearing accounts and bank register lines are now shared with the payment import (`ResolvesZohoBankAccounts`). Rehearsed on Brill Creations' export: 1,360 expenses (1,899,482.74) as 1,567 ERP expenses, matching the Zoho ledger on all 53 accounts to the cent.
- Made the Customer payments and Vendor payments steps executable (shared `ZohoPaymentService`) from Zoho's payment exports. Each Zoho payment becomes one cleared ERP payment on the bank account matched by Zoho account code, allocated to its invoices or bills exactly as Zoho applied it, which updates their paid amount, balance and paid/partial status the way the ERP payment screens do. Each payment also gets its own balanced journal: customer payments debit the deposit account and credit receivables 1100; vendor payments debit payables 2000 and credit the paid-through account. A vendor payment's unapplied amount is debited to Zoho's `Advances paid to vendors`, as Zoho posts it. Each payment adds a bank register line and updates the bank balance, as the ERP's cleared payments do. A payment made through a non-bank Zoho account (such as `Projects on Process`) uses an internal clearing bank account linked to that ledger account, following the existing migration convention. Validation rejects over-applied, draft or unknown invoices and bills, payments whose applications plus unused amount differ from the payment amount, bank charges, and unapplied customer payments. The `payments_received.v1` and `vendor_payments.v1` profiles now recognise Zoho's `CustomerPayment ID` / `VendorPayment ID` columns, and vendor payment exports are no longer mistaken for bills exports. Rehearsed on the Brill Creations exports: 460 customer payments (2,261,522.36) and 33 vendor payments (130,197.81) match Zoho to the cent, by account and per invoice.
- Made the Credit notes and Vendor credits steps executable (shared `ZohoCreditDocumentService`): Zoho credit notes become ERP credit notes (debit each line's Zoho revenue account, credit receivables 1100) and Zoho vendor credits become ERP debit notes (debit payables 2000, credit each line's Zoho account), each with its own balanced journal — the ERP's own note posting is hard-wired to default accounts 4100/1200. Lines use the `Zoho free-text line` service (ERP note lines need a product and hold no text), with every Zoho line's text and the Zoho application list kept in the notes; totals match Zoho to the cent. Credits import approved with their full balance open for the Credit applications step; drafts import as drafts; void credits are skipped. Credits link to their single Zoho invoice or bill. Vendor credit numbers are `VC-<Zoho number>`, with the company id appended only when another company already owns that globally unique number. Currency fields, which the ERP note models do not mass-assign, are set explicitly. The `credit_notes.v1` profile now also recognises Zoho's `CreditNotes ID` column, without which the export was rejected.
- Made the Purchase invoices and bills step executable from the Zoho Bills export: bill lines import as true free-text lines (no product) charged to their own Zoho account with the Zoho item name and description kept, matching Zoho to the cent. Live bills import posted with their own balanced journal entry — debit each line's Zoho account, credit payables 2000 (with the vendor as contact) — and stay unpaid until the Vendor payments step; drafts import as drafts; void bills are skipped and listed. A bill is identified by vendor and bill number together, since vendors number their own bills; taxed Zoho bills are refused rather than imported without their tax. Journal posting for imported documents is shared (`ZohoJournalWriter`), and the old generic sales/bill row checks were removed.
- Made the Sales invoices step executable from the Zoho Invoices export: lines use the shared `Zoho free-text line` service with Zoho's item name and description, and carry Zoho's item and invoice-level discounts as fixed discounts so every line and invoice matches Zoho to the cent. Live invoices (closed, overdue, open, sent, partially paid) import posted with their own balanced journal entry — debit receivable 1100, credit each line's Zoho revenue account, in the base currency — and stay unpaid until the Customer payments step (Zoho's typed balances are not trusted). Draft, rejected and pending invoices import as drafts with no journal; void and internally approved invoices are skipped and listed by number. Invoices link to their imported quotation (one invoice per quotation, as the ERP allows; a second invoice from the same estimate records the link in its notes). Journal numbers include the company id (`ZOHO-<company>-<Zoho id>-<date>`) so they never collide with another company's imported entries or consume the shared `JE-<year>-###` sequence.
- Made the Quotations step executable from the Zoho Estimates export (new `estimates.v1` profile): each estimate becomes an ERP quotation whose lines all use one `Zoho free-text line` service with the Zoho item name and description kept on the line, and whose line amounts carry Zoho's item and estimate-level discounts as fixed discounts so every line and total matches Zoho to the cent (the import refuses to keep a quotation whose ERP total differs). Statuses map draft/pending → draft, sent/expired → sent, approved/accepted → accepted, invoiced → accepted and converted to invoice, declined → rejected, with the Zoho status, terms and a missing-expiry note kept in the notes; ISO dates are read directly and other date styles use the migration's configured format.
- Made the Revenue and expense categories step executable from the Zoho Chart of Accounts export: Income / Other Income accounts become revenue categories and Expense / Other Expense / Cost Of Goods Sold accounts become expense categories, each named exactly as its Zoho account (ERP revenues and expenses find categories by name), coded with the Zoho account code and linked to the step 2 chart account; other account types are skipped, and expenses booked to them get their category in the Revenues and expenses step.
- Made the Bank, cash and payment accounts step executable from the Zoho Chart of Accounts export: its Bank, Cash and Credit Card rows become ERP bank accounts (other rows are skipped) linked by account code to the chart accounts imported in step 2, with the Zoho code in `zoho_gl_code`, the account number left empty until finance verifies it, and the bank name `Pending verification` (or `Cash`), matching the existing migration bank-account convention. A source file is now unique per stage rather than per migration, so the same Chart of Accounts export can be attached to both stages.
- Made the Vendors step executable with the same Zoho contact import as Customers (shared base service), through the ERP vendor import rules. Because the ERP matches vendors by email, two vendors sharing an email in one file are flagged instead of being merged.
- Made the Customers step executable: Zoho contact exports are checked with the ERP customer rules and imported all-or-nothing as customers keyed by Display Name (contact person, email, mobile, payment terms and billing address carried over; non-base currency, inactive status, website and partial shipping addresses kept in the notes). Contacts sharing an email stay separate, case-only duplicate names and non-customer contacts are flagged, and the import re-checks every row from the original Zoho values first. Only stages with an importer now show the Import button, and uploaded date cells are staged as readable dates instead of Excel serial numbers.
- Made the Warehouses step a supported manual stage like Taxes: warehouse rows (name, address, city, zip code, optional phone and email) typed or pasted in bulk from Excel, validated with the ERP warehouse import rules and imported together, or `Continue with existing warehouses` when ERP already has one (such as the setup default).
- Added a supported tax prerequisite stage with an explicit `No taxes used` completion path or editable tax-name/rate rows that are validated and imported together before later document stages.
- Merged the units and categories stages into one `Categories and units` step (categories section first, then units, imported together and validated per record type), and moved `Products and services` to step 5 with a short version of the ERP item form (type, name, SKU with Generate, tax, category, prices, unit, short description) and an `Upload Excel file` option modelled on the ERP Items import (XLSX/CSV template with an `ERP names` sheet listing existing categories, units and taxes; columns matched by name or alias in any order; a validate-only preview with Total/Valid/Invalid/New/Existing counts, row results and a downloadable error report; then an all-or-nothing import through the migration's validation, audit and stage completion). Services need no unit, matching the ERP item form. When ERP already holds categories and units, the step can continue with them instead of re-entering names that duplicate blocking would reject. Manual imports now refuse to run, before writing anything, when the migration has no record for the step. Every Bulk Import button now shows a success toast when its import finishes. Run `php artisan migrate` to move existing migrations onto the new step order.
- Added guarded copy-and-paste imports for Products & Services and Warehouse Stock when Zoho cannot provide those exports. Both stages enforce fixed column order, row-level validation, duplicate/reference checks, explicit accuracy confirmation, audit events, and accepted/rejected/imported/updated counts before later migration stages can rely on the data.
- Allowed migration setup to accept a default warehouse name directly, reusing an exact company warehouse or creating an active warehouse that is immediately available throughout ERP.
- Added a dedicated Bulk Import workspace with full and standalone Zoho migration modes, a strict 24-stage vertical journey, durable progress/issue/audit tracking, stage-aware CSV/XLSX uploads, source previews, supported-profile gating, ERP record links, and responsive progress/status panels. Existing page-level invoice, bill, and warehouse import buttons now route all migration work through the controlled workspace.
- Removed the standalone currencies and exchange-rates stage. The migration configuration owns the base currency, while foreign currency and transaction exchange rates are validated with their invoices, bills, payments, and journals.
- Completed the Chart of Accounts workspace flow with visible upload/processing progress, automatic queued inspection and validation, row-level correction guidance and downloadable issue reports, corrected-file replacement, guarded execution, and automatic journey completion only after the ERP transaction succeeds.
- Added explicit Zoho-to-ERP account-type mapping for all 16 types present in the verified Brill Creations export, preserving normal-balance semantics while reporting genuinely missing account codes for correction.
- Added the tenant-scoped Zoho migration workspace foundation with immutable accounting-mode configuration, private hashed uploads, conservative export-profile detection, durable staged source rows, dedicated permissions, and queued file inspection. This foundation does not create financial records.

### Fixed
- Sales and purchase invoice create/update now always treat line prices as company base-currency inputs and convert them to the selected foreign document currency before persistence, preventing QAR 1,000 at 3.65 from being stored as USD 1,000 / QAR 3,650.
- Multi-currency invoice lists now fall back to the configured company base currency and saved exchange rate when legacy rows lack base-currency snapshot fields, keeping QAR on the main line with the selected foreign currency beneath it.
- After a large upload, the page now updates on its own when validation finishes. The step previously left "processing" as soon as the file was read, so the page stopped refreshing and showed "Recheck corrections" until reloaded.
- Expenses booked to an account that is also an internal clearing bank account (e.g. Salary Payable) now add that account's bank register line, so clearing account registers equal their ledger. Clearing accounts are created before any expense posts.
- Validation of a step with several files no longer lets rows of one file overwrite rows of another that share a row number.
- Imported Zoho journals that post the same account on more than one line now update that account's balance by every line. Each line previously wrote back a stale copy of the account. Earlier steps never post one account twice in a journal, and their balances were verified unaffected.
- Guided migrations no longer refuse a stage whose directly preceding stage is not yet supported; the server now gates on the nearest supported stage, matching the journey display.
- Manual pastes and entry forms whose last row ends in an empty cell are no longer rejected for a missing column: neither the import parser nor the global `TrimStrings` middleware (now excluding `pasted_data`) strips that trailing tab.
- Captured the active migration stage inside the transactional Chart of Accounts importer so a successful row pass can mark the stage complete instead of rolling back with an undefined-variable error.
- Allowed an unchanged, previously attached spreadsheet to be selected again after an unimported stage failure, restarting inspection instead of showing a duplicate-file error.
- Made replacement uploads explicit and race-safe: choosing a new spreadsheet replaces the current unimported source, stale inspection jobs stop without changing the stage, and completed imports remain protected.
- Allowed queued Chart of Accounts execution to continue after the controller marks the stage as processing, preventing a fully validated import from immediately failing before any ERP records are written.
- Removed standalone import buttons from all module and setup pages so every migration import is entered through the central Bulk Import workspace.
- Made HRM plan initialization idempotent and faster by synchronizing the HR role's permissions in one operation, preventing repeated company plan assignment from timing out or failing on duplicate permission links.
- Shortened the purchase-order invoice-link unique index name so clean MySQL installations complete without exceeding the 64-character identifier limit, including safe recovery when the table was created before the original migration failed.
- Accepted genuine Excel imports even when PHP detects an XLSX workbook as a ZIP container or the workbook uses another Excel-readable format, while continuing to reject arbitrary renamed or unreadable files.
- Showed line discounts the same way everywhere: a fixed (cash) discount shows only its amount and a percentage discount shows only its percentage, on invoices, quotations, proposals, retainers, bills, credit and debit notes, sales and purchase returns, their print pages and template PDFs. Imported invoice lines that stored a discount amount with 0% are now marked as fixed-amount discounts of the same amount, so they no longer show "0.00%" and keep their discount when edited or returned (amounts and totals unchanged).
- Removed the extra blank trailing page from quotation and invoice PDF downloads that use document templates.
- Fixed the Assets list: searching no longer fails with a server error (it now matches name, serial code, location and category), the default order is newest purchase first with a stable tie-breaker so pages never shuffle, and Category, Purchase Date, Serial Code, Quantity, Unit Price and Purchase Cost can be sorted.
- Stopped quotation, proposal and invoice PDFs from slicing text across pages: Notes, Bank Details and Terms move to the next page as a whole when they would be split (longer ones break only between lines), item descriptions and the subject break only between lines, and total rows stay whole.
- Aligned footer contact icons with their text and corrected the vertical position of all text in quotation and invoice PDFs, which html2canvas was drawing too low under Tailwind's image styles.
- Restored card padding so page toolbars (search, view toggle, per-page and filters) are vertically centred and data tables run edge to edge without side or bottom gaps.
- Kept the accounting report Download button at full width with even icon spacing, and kept Generate, Clear and Download on one line across Double Entry reports.
- Included accounts with a one-cent balance on generated balance sheets so totals balance exactly.
- Ended sidebar tree lines at the last submenu item and centred each branch on its menu icon.
- Expanded transaction-list searches to match customer, vendor, company, contact, employee, reference, category, and account names in addition to document and payment numbers.
- Expanded Invoice Aging to show customer account codes and individual outstanding invoice numbers, with customer subtotals and grand totals in both the report and PDF.
- Removed the duplicate scrollbar and excess empty space from Video Production record forms.
- Counted Video Production deliverables by their Episodes / Reels Count: the pipeline headline now shows reels delivered and each workflow status shows its number of reels instead of list rows; date columns in production lists show the date without a midnight time.
- Opened the customer edit form for customers saved without billing or shipping addresses (such as production client logins) instead of showing a blank page.
- Allowed enabled production clients assigned to production projects to sign in with the credentials created from Video Production, without requiring a separate accounting customer record or User Management edit.
- Displayed the Production Client role label in User Management list and card views instead of the generic Client account type.
- Aligned Video Production delivered-reel KPIs and pipeline totals to count Reel episodes consistently from delivered status or final delivery date, while keeping static posts separate.
- Separated Video Production overview access from read-only production record access and limited production-only roles to production projects within the Project section.
- Add payment-mode selection and receiving-account labeling when recording customer payments.
- Populate the customer and receiving-account dropdowns when recording a payment from a sales invoice.
- Allow complete Purchase Order terms to be preserved when converting to a draft Purchase Invoice instead of failing when the text exceeds 255 characters.
- Made Purchase Order / LPO activation migrations safely retryable after a partial or interrupted production activation.
- Show the Purchase Orders / LPO navigation entry only for companies whose active plan includes the add-on, preventing dashboard redirects from unavailable module links.

### Added
- Added a Contact (vendor or customer) to each manual journal entry line, like Zoho: lines can optionally name one of the company's vendors or customers, the journal view and list show the contacts (by company name), the list search matches contact and company names, and account transactions show the contact under each line's description.
- Added an Account to each bill (purchase invoice) line, like Zoho: lines can be charged to an expense, inventory, prepaid or fixed asset account, and posting the bill debits each line's net amount to its own account instead of putting everything into Inventory, with tax to input tax and the total to Accounts Payable. Stock products default to Inventory; service lines must have an account. The account is shown on the bill details.
- Added a percentage or fixed-amount (QAR) discount choice to each bill line, matching sales invoices; fixed discounts cannot exceed the line subtotal, bills converted from a Purchase Order keep a fixed PO discount as a fixed amount, and the bill details and print show the amount without a percentage.
- Added period account transactions to the Chart of Accounts account view: choose From and To dates to see the opening balance, each posted transaction with a running Dr/Cr balance, period totals and the closing balance, using the same opening rule as the Trial Balance; the account's PDF and Excel downloads show the same figures. Account names in the Trial Balance, Profit & Loss and Balance Sheet now open these transactions for the report's period.
- Added opening balance, net debit, net credit and closing balance columns to the Trial Balance screen, PDF and Excel export, grouped by Assets, Liabilities, Equity, Income and Expense: balance sheet accounts carry their balance forward, income and expense accounts start each year at zero with earlier results in Retained Earnings opening, and balances show Dr/Cr.
- Added a Revenue Category to sales invoices: create, edit and quotation conversion offer the company's sales revenue accounts (defaulting to the usual service or product account), the posted invoice credits the chosen account so revenue can be reported by category, and the category is shown on the invoice details.
- Added payment-mode selection (Cash, Bank Transfer, Cheque) to vendor payments, with a required cheque number for cheques, shown on payment details, vouchers, bank transaction descriptions and reports; customer receipts now print the selected payment mode as well.
- Added opening and closing dates to Balance Sheet generation: balances are calculated as of the closing date, profit earned within the period is shown as Net Income for the Period under Equity, and the period appears in the balance sheet selector, lists, comparisons, PDF and Excel exports.
- Added manual multi-currency transaction entry with effective-dated company exchange rates, historical rate snapshots, base-currency journal posting, currency-aware bank accounts, and a dry-run-safe live-data backfill command.
- Added CR Number and private supporting-document uploads to customer and vendor create, edit, and detail views, including validated files and authorized preview, download, and removal actions.
- Added a live searchable customer filter, invoice-date-range and aging-bucket filters, plus selectable report columns to Invoice Aging, with matching filtered PDF output.
- Added transaction-date filtering and filtered CSV/Excel downloads to Bank Transactions, with all matching records included in each export.
- Added SKU generation to the quotation and sales invoice quick product/service creation dialog.
- Added production-project client login management with a dedicated permission, restricted read-only production portals, password/profile controls, welcome emails, and login-as-client support.
- Added a tenant-scoped Purchase Order / LPO add-on with draft-to-issued approval workflow, vendor and catalogue/custom lines, taxes and discounts, attachments, billing status, activity history, and partial or full conversion into draft purchase invoices without changing stock or accounting until invoice posting.
- Added per-account PDF/Excel transaction downloads from Chart of Accounts, with date filters, company checks, opening/closing balances, period totals and Dr/Cr labels. Corrected General Ledger opening snapshots to use the current schema.
- Added private supporting documents to journal creation and viewing: PDF/images, Word, Excel and ZIP files, with authorized downloads, supported previews, removal audit metadata, and rollback cleanup.
- Added an in-page product picker to quotation and sales invoice create/edit flows with multi-select, name/SKU search, category filtering, stock and service visibility, automatic price/tax population, row-click selection, and permission-controlled quick product creation.
- Added a separate square watermark image field for quotation and invoice templates, rendered large and centered in previews and PDFs without changing the header logo.
- Applied the reference-matched professional layout to every managed sales invoice and quotation template, including dynamic company branding, dates, subject and item descriptions, totals, notes, watermark, signatures where applicable, and the company contact footer.
- Added editable footer contact fields for phone, postal address, email, and website across invoice and quotation templates.
- Added unified Download menus with PDF and Excel formats across Double Entry accounting reports, including General Ledger, statements, balances, cash flow, Profit & Loss, Trial Balance, Ledger Summary, expenses, journals, and Balance Sheets.
- Added editable per-item descriptions that update the selected product/service catalogue record, plus percentage or fixed-currency discounts for quotations and sales invoices, including document conversion, printing, templates, imports, and sales returns.
- Added bulk import support for Bank Transfers and Journal Entries, including import permissions and page-level Import actions.
- New features currently in development will be listed here before release.
- Added a Duplicate action to sales invoices (list and grid views) that copies an invoice into a new draft dated today with the same customer, lines, discounts and taxes; payments, posting and the quotation link are not copied.

### Changed
- Shortened the Banking submenu labels to Accounts, Transactions and Transfers, and gave third-level sidebar items more room so labels are no longer cut off.
- Added searchable customer/vendor selectors with in-place party creation and automatic selection in sales invoices and customer/vendor payment forms.
- Grouped related company menu items under Sales and Purchases, kept child items visible for users with limited permissions, and added connector lines between sidebar subitems.
- Simplified Purchase Order terminology by removing "/ LPO" from the sidebar, page headings, grid column, and search text.
- Renamed the Sales menu and grid labels to "Invoice Returns" and "Payment Received" for clearer, compact accounting terminology without changing the underlying invoice or payment workflows.
- Decoupled customer and vendor creation from User Management: party forms automatically maintain linked internal identities with optional ERP portal login, transactional detail/status synchronization, and protected user editing/deletion. Client/vendor roles are excluded from manual user creation and staff-seat limits. Imports create disabled portal accounts, and `accounts:backfill-party-portals --dry-run` previews the idempotent backfill without changing existing user or financial record IDs.
- Replaced customer and vendor count cards on Account dashboards with live Accounts Receivable, Accounts Payable, overdue amounts, Cash Balance, and Bank Balance summaries.
- Renamed the displayed Payment Date label to Received Date across customer and vendor payment forms, lists, details, and payment template previews.
- Added a dedicated Production Client portal that opens directly on the assigned production overview, provides sidebar-only Overview, Shooting Log and Deliverables navigation, and enforces one client login per production project.
- Removed reusable Production Client attachment, project switching, generic Project navigation, breadcrumbs and production tabs from Production Client accounts while preserving the staff management interface.
- Simplified downloaded quotation and sales invoice PDF filenames to `EST-YYYY-MM-NNN.pdf` and `INV-YYYY-MM-NNN.pdf` while preserving internal document numbers.
- Hidden purchase-price inputs from product and service create/edit interfaces while preserving existing stored costs and purchase/accounting behavior.
- Added warehouse-aware product stock editing and automatic default stock locations, prevented zero-stock selection, made quick-created products appear immediately in quotation and sales invoice lines, and deduct stock atomically when product invoices are posted.
- Aligned quotation and sales invoice customer selectors with Accounts customer master data, showing the real contact email only when present and hiding internal `@import.local` placeholder addresses.
- Changed Product/Service tax assignment to an optional single-select dropdown backed by the company tax master, with `_` representing no tax across create, edit, quick-create, and item details.
- Matched quotation and invoice typography to the Zoho Books reference using bundled Noto Sans and its measured 28pt, 10pt, 9pt, 8pt, and 7pt document hierarchy.
- Expanded the Item & Description column to the Zoho-style table proportion for cleaner multiline product descriptions.
- Removed the printed page border, reduced oversized document titles, and widened the Item & Description column in quotation and invoice previews and PDFs.
- Deduplicated configured document table columns to prevent repeated headings and unnecessarily narrow item descriptions.
- Updated the Payment template to match the reference receipt with a black corner mark, neutral payer styling, Bank Account details, and no fixed amount-in-words text.
- Restored the Payment template type and its receipt/voucher preview alongside the redesigned quotation and invoice templates.
- Removed the unused Primary Color control from the fixed-design quotation and invoice template editor.
- Ensured saved terms and conditions appear in both the live preview and downloaded PDF for invoice and quotation templates.
- Removed placeholder notes from the template editor preview so Notes only appear when entered in the template or sales document.
- Ensured saved bank details appear in both the live preview and downloaded PDF for quotation templates.
- Centered the company watermark in invoice live previews and downloaded PDFs to match quotations.
- Enforced a full A4 minimum page height for quotation and invoice previews and PDFs, including documents with only one item.
- Matched invoice Bank Details, Notes, and Terms typography and spacing to the quotation template.
- Preserved the true A4 210:297 page proportion in responsive quotation and invoice previews while keeping PDF output at full A4 height.
- Prevented overflowing terms from being clipped, added spacing between invoice signatures and the footer, and enabled A4 PDF pagination for longer invoices and quotations.
- Added a signature toggle that conditionally shows signature inputs and signature blocks across quotation and invoice previews and PDFs.
- Removed the underline beneath authorized and client signatures in quotation and invoice templates.
- Reduced the signature-to-footer gap and anchored signatures directly above the footer on full A4 pages.
- Added server-enforced logout from other devices with session version checks, API token revocation, active session status polling, and forced session-ended modal.
- Added a staff-only time clock with clock-in, pause/resume, official-duty tracking, clock-out, daily work updates, HR date-wise review, correction approvals, and immutable attendance history.
- Expanded Zoho Books-style bulk import support for vendors, warehouses, accounting master data, sales and purchase invoices, customer and vendor payments, revenues, and expenses.
- Customer payments can now be recorded as unallocated deposits and applied to outstanding invoices later.
- Sales returns now support fractional and partial item quantities with remaining-quantity protection.
manual-journal-entries
- Accountants can now record manual double-entry journal entries with balanced debit and credit lines.
- Purchase invoices now support attaching supplier invoices, receipts, and other supporting documents.
- Projects now support main contractors and subcontractors backed by vendors, with contract scopes, dates, accounting-linked payments, and remaining balances.
- Contracts now track amount paid, with the remaining balance calculated automatically.
- Contracts now include a required plain-text Scope of Work field with multiline input support and preserved formatting on detail pages.
- Added Zoho-style Security Settings with password changes, active sessions, logout-other-devices support, login history, and admin reset-link sending.
- Added bulk import support for Petty Cash records, including templates, validation, duplicate handling, and the Petty Cash page import action.
- Added bulk import support for quotations, including templates, validation, duplicate handling, and the Quotations page import action.
main
- Shortened PDF, Excel and CSV download names: documents are named by their number (e.g. CP-2026-10-002.pdf) and reports by a short code and ISO dates (e.g. GL-1010-2026-10-01-to-2026-10-31.xlsx).

### Improved
- Enhancements to existing workflows will be listed here before release.
- Login page now uses saved brand theme colors and logo styling with a responsive desktop/mobile layout.
- Customer create, edit, and view dialogs now use a single vertical scrollbar.
- Customer billing and shipping addresses now use a searchable country selector with official Qatar and Saudi Arabia address formats.
- Qatar customer addresses now require an 11-digit QID number, while Saudi addresses require a 10-digit National ID or Iqama number.
- Project property information now uses the reusable country-specific address fields and includes plot, property, map link, and generated location QR details.
- Improved authenticated mobile layouts with a compact language selector, single-line breadcrumbs, and contained attendance tables to prevent page-level horizontal overflow.
- Replaced HR dashboard quick actions with a compact, live view of every employee's attendance state for today.
- Changed HR attendance to an employee-first view with date-filtered profile history, period summaries, and a retained Daily Records view.
- Restricted staff time-clock actions and work updates to desktop and laptop browsers while retaining read-only attendance history on mobile devices and tablets.
- Bulk imports now support multi-line invoice records, reference resolution, import permissions, and clearer failed-row reporting.
- Sales invoices now identify customers by company and contact person across selection, list, detail, and print views.
- purchase-product-service-label
- Purchase invoices now identify vendors by company and contact person across selection, list, detail, and print views.
- Item and purchase invoice fields now clearly identify that products, services, and parts can be selected.
- Service items no longer display or require an inventory unit.
- Service-wise sales invoices now label service selections correctly instead of calling them products.
- Purchase invoice lists and print views now display "No Tax" when no tax is selected.
- Sales returns can now be created without a warehouse; returned stock is updated only when a warehouse is selected.
- main
- Sales invoice lines now support optional configured tax types and display a dash when no tax applies.
- Sales invoice lines support optional configured tax types, and invoice or quotation documents display a dash when no tax is selected.
- Product and service items can now be created or edited without assigning a tax.
- Updated profile/security separation so profile details and password security are managed on separate pages.

### Fixed
- Prevented footer contact icons from being clipped and balanced the C.R. number badge padding around its text.
- Corrected the visual baseline between footer contact icons and their text in quotation and invoice previews and PDFs, including html2canvas exports.
- Prevented managed quotation and invoice PDFs from creating a second page for the footer by removing the duplicate html2pdf margin around the already padded A4 template.
- Bug fixes and production stability improvements will be listed here before release.
- Invoice and quotation template previews now place the document badge on the left when the header is aligned right.
- Document template previews now use saved currency settings instead of hardcoded dollar formatting.
- Fixed PHP 8.5 database configuration deprecation output for MySQL SSL CA attributes.

---

## [2026-06] Feature Update

### Added
- Added bulk import support for Bank Transfers and Journal Entries, including import permissions and page-level Import actions.
- Social login for Google and Microsoft.
- Admin-controlled social login settings for enabling providers and managing credentials.
- Bulk import for customers and products/services.
- Document template management for quotations and invoices.
- Signature image support for document templates.
- Template selection for quotation and invoice workflows.
- In-place customer creation from quotation screens.
- In-place warehouse creation from quotation screens.

### Improved
- Warehouse is now optional in quotation and related business document workflows.
- Products and services can be selected without warehouse stock restrictions when no warehouse is selected.
- Quotation-to-invoice conversion flow now includes a review step.
- Template save flow now shows a confirmation message before redirecting.
- Template delete and default actions are clearer and permission-aware.
- Bulk import validation is more flexible and closer to Zoho-style importing.

### Fixed
- Media and image preview issues on the hosted server.
- Module favicon and image loading issues in production.
- Initial plan selection issue for first-time users.
- Local frontend cache visibility issues during development.
- Template signature URL database compatibility issue.

## Developer Notes

- Some features require running database migrations.
- Bulk import requires a running Laravel queue worker.
- Frontend changes require rebuilding Vite assets for production.
- Media and module asset fixes require correct public storage handling on hosting.
