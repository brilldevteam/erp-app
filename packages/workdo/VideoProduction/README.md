# Video Production Add-on

Tenant-scoped video production management for the DOC Medical workflow.

## Phase 1

- Add-on registration and Project menu integration
- Company-specific production settings and DOC Medical defaults
- Production job CRUD and workflow statuses
- Optional, tenant-validated Taskly project link
- Granular company permissions

## Planned Phases

1. Shooting sessions, crew assignments, calendar, and evidence
2. Shot lists, shot completion, equipment, and call sheets
3. Deliverables, versions, approvals, and delivery links
4. Revisions, included/out-of-scope rules, and compliance
5. Time entries, client waiting time, and overtime
6. Monthly dashboard KPIs and exports

All business records must remain scoped by `created_by`. Cross-package references
must be nullable and validated to the current tenant so the add-on can be disabled
without breaking core ERP or Taskly routes.
