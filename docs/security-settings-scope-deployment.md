# Settings Scope Phase 1 Deployment

This release changes future settings writes to private by default and keeps only superadmin brand rows public for the anonymous login page. Existing company brand rows must be made private during deployment.

## Before Deployment

1. Back up the production database and confirm the backup can be opened.
2. Record the affected-row count without changing data:

```sql
SELECT COUNT(*) AS affected_rows
FROM settings s
INNER JOIN users u ON u.id = s.created_by
WHERE u.type = 'company'
  AND s.is_public = 1
  AND s.`key` IN (
    'logo_dark', 'logo_light', 'favicon', 'titleText', 'footerText',
    'sidebarVariant', 'sidebarStyle', 'layoutDirection', 'themeMode',
    'themeColor', 'customColor'
  );
```

## Apply

Create the backup table after deploying the application. MySQL commits `CREATE TABLE ... AS SELECT` immediately, so this must happen before the update transaction:

```sql
CREATE TABLE settings_scope_20261115_backup AS
SELECT s.*
FROM settings s
INNER JOIN users u ON u.id = s.created_by
WHERE u.type = 'company'
  AND s.is_public = 1
  AND s.`key` IN (
    'logo_dark', 'logo_light', 'favicon', 'titleText', 'footerText',
    'sidebarVariant', 'sidebarStyle', 'layoutDirection', 'themeMode',
    'themeColor', 'customColor'
  );

START TRANSACTION;

UPDATE settings s
INNER JOIN users u ON u.id = s.created_by
SET s.is_public = 0
WHERE u.type = 'company'
  AND s.is_public = 1
  AND s.`key` IN (
    'logo_dark', 'logo_light', 'favicon', 'titleText', 'footerText',
    'sidebarVariant', 'sidebarStyle', 'layoutDirection', 'themeMode',
    'themeColor', 'customColor'
  );

COMMIT;
```

Clear application caches and verify the anonymous login page uses the superadmin logo, title, footer and theme without exposing company fields.

## Rollback

```sql
UPDATE settings s
INNER JOIN settings_scope_20261115_backup b ON b.id = s.id
SET s.is_public = b.is_public;
```

After the release is accepted and the rollback window has closed, the deployment operator may remove `settings_scope_20261115_backup` according to the normal retention policy.
