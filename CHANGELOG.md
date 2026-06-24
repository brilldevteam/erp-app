# Changelog

All notable changes to the Wazely ERP application are documented here.

## [Unreleased]

### Added
- New features currently in development will be listed here before release.
- Sales returns now support fractional and partial item quantities with remaining-quantity protection.

### Improved
- Enhancements to existing workflows will be listed here before release.
- Sales invoices now identify customers by company and contact person across selection, list, detail, and print views.
- Purchase invoices now identify vendors by company and contact person across selection, list, detail, and print views.
- Item and purchase invoice fields now clearly identify that products, services, and parts can be selected.
- Service items no longer display or require an inventory unit.
- Service-wise sales invoices now label service selections correctly instead of calling them products.
- Purchase invoice lists and print views now display "No Tax" when no tax is selected.
- Sales invoice lines now support optional configured tax types and display a dash when no tax applies.
- Product and service items can now be created or edited without assigning a tax.

### Fixed
- Bug fixes and production stability improvements will be listed here before release.

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
