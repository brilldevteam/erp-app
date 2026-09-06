# Changelog

All notable changes to the Wazely ERP application are documented here.

## [Unreleased]

### Added
- Added an in-page quotation product picker with multi-select, name/SKU search, category filtering, stock and service visibility, automatic price/tax population, and permission-controlled quick product creation for both create and edit flows.
- Added a separate square watermark image field for quotation and invoice templates, rendered large and centered in previews and PDFs without changing the header logo.
- Applied the reference-matched professional layout to every managed sales invoice and quotation template, including dynamic company branding, dates, subject and item descriptions, totals, notes, watermark, signatures where applicable, and the company contact footer.
- Added editable footer contact fields for phone, postal address, email, and website across invoice and quotation templates.
- Added unified Download menus with PDF and Excel formats across Double Entry accounting reports, including General Ledger, statements, balances, cash flow, Profit & Loss, Trial Balance, Ledger Summary, expenses, journals, and Balance Sheets.
- Added editable per-item descriptions that update the selected product/service catalogue record, plus percentage or fixed-currency discounts for quotations and sales invoices, including document conversion, printing, templates, imports, and sales returns.
- Added bulk import support for Bank Transfers and Journal Entries, including import permissions and page-level Import actions.
- New features currently in development will be listed here before release.

### Changed
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
