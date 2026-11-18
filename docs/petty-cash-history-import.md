# Petty Cash History Import (Brill, Petty Cash - Jeanne)

Shows the petty cash journals of Brill Creations (company 664, account 1005 "Petty Cash - Jeanne") in the petty cash section. Journals are only read: the accounts, Trial Balance and P&L do not change. Agreed with Sithari on 2026-11-18.

What it creates:

- one top-up record per money-in line (`PCJ-001` ...), with opening, added, spent and closing balance;
- one approved request plus its expense per spend line (`PCJ-R-0001` ...), split when one journal paid several expense accounts;
- one category per account spent on, linked to it when it is an expense account.

It replaces the summary record `PC-JEANNE`. `PC-MAIN` (old fund) is left as it is.

Rehearsed on 2026-11-18 against the live backup of that day: 30 top-ups, 293 spend lines, 12 categories; money in 20,530.00, money out 19,353.25, balance 1,176.75 - all equal to the journals; 316 journal lines unchanged; rollback restored the original rows.

## Before

1. Deploy the release with the `petty_cash_categories.account_id` migration (`php artisan migrate --force`).
2. Ask the team not to approve anything in the petty cash section until this is done, and to record no new petty cash journals while it runs.
3. Back up the database. Then, in phpMyAdmin:

```sql
CREATE TABLE petty_cashes_backup_20261118 AS SELECT * FROM petty_cashes WHERE created_by = 664;
```

## Run (as the site user)

```bash
cd /home/brillcrew-dasherp/htdocs/dasherp.brillcrew.com
sudo -u brillcrew-dasherp php artisan petty-cash:import-ledger --company=664 --account-code=1005 --prefix=PCJ --replace=PC-JEANNE
```

This is a dry run. Every row in its table must say `yes` / `unchanged`, and the balance must equal the Petty Cash - Jeanne balance in the General Ledger. Then save:

```bash
sudo -u brillcrew-dasherp php artisan petty-cash:import-ledger --company=664 --account-code=1005 --prefix=PCJ --replace=PC-JEANNE --apply
```

## After

- Petty Cash shows `PCJ-001` to the last `PCJ-0xx`; the newest closing balance equals the ledger balance.
- Expenses and Requests list the history; Categories shows 12 categories.
- From now on petty cash is recorded in the petty cash section (requests, reimbursements, top-ups), not as journals. Payable categories have no expense account and cannot be used for new approvals.

## Rollback

```sql
START TRANSACTION;
DELETE e FROM petty_cash_expenses e JOIN petty_cash_requests r ON r.id = e.request_id
 WHERE r.created_by = 664 AND r.request_number LIKE 'PCJ-R-%';
DELETE FROM petty_cash_requests WHERE created_by = 664 AND request_number LIKE 'PCJ-R-%';
DELETE FROM petty_cashes WHERE created_by = 664 AND pettycash_number LIKE 'PCJ-%';
DELETE c FROM petty_cash_categories c
 WHERE c.created_by = 664
   AND NOT EXISTS (SELECT 1 FROM petty_cash_requests r WHERE r.categorie_id = c.id)
   AND NOT EXISTS (SELECT 1 FROM petty_cash_reimbursements m WHERE m.category_id = c.id);
INSERT INTO petty_cashes SELECT b.* FROM petty_cashes_backup_20261118 b
 WHERE b.pettycash_number = 'PC-JEANNE' AND NOT EXISTS (SELECT 1 FROM petty_cashes p WHERE p.id = b.id);
COMMIT;
```

Drop `petty_cashes_backup_20261118` once the import is accepted.
