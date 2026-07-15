# Changelog

All notable changes to the Wazely ERP application are documented here.

## [Unreleased]

### Added
- New features currently in development will be listed here before release.
- Added a staff-only time clock with clock-in, pause/resume, official-duty tracking, clock-out, daily work updates, HR date-wise review, correction approvals, and immutable attendance history.
- Expanded Zoho Books-style bulk import support for vendors, warehouses, accounting master data, sales and purchase invoices, customer and vendor payments, revenues, and expenses.
- Customer payments can now be recorded as unallocated deposits and applied to outstanding invoices later.
- Sales returns now support fractional and partial item quantities with remaining-quantity protection.
manual-journal-entries
- Accountants can now record manual double-entry journal entries with balanced debit and credit lines.
- Purchase invoices now support attaching supplier invoices, receipts, and other supporting documents.
main

### Improved
- Enhancements to existing workflows will be listed here before release.
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

### Fixed
- Bug fixes and production stability improvements will be listed here before release.
- Invoice and quotation template previews now place the document badge on the left when the header is aligned right.

---

## [2026-06] Feature Update

### Added
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
