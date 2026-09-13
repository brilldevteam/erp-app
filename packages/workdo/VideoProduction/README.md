# Video Production Add-on

Tenant-scoped video production management for company shooting and delivery workflows.

## Features

- Add-on registration and Project menu integration
- Company-specific production settings and targets
- Company-wide shooting logs and deliverables
- Proof image uploads and supporting evidence links
- Monthly production metrics
- Granular company permissions

All business records must remain scoped by `created_by`. Cross-package references
must be nullable and validated to the current tenant so the add-on can be disabled
without breaking core ERP or Taskly routes.
