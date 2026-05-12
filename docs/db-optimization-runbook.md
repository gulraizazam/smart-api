# Database Optimization Runbook

This document lists every DB optimization migration applied locally between
**2026-04-08** and **2026-04-12**, grouped by category, so the same changes
can be rolled out to staging/production in order.

All files live in `database/migrations/`. Run them with:

```bash
php artisan migrate --path=database/migrations/<file>.php
```

or run the whole batch in timestamp order with a plain `php artisan migrate`.

> **Before running on live:** take a full DB backup. Several migrations rewrite
> column types, add FK constraints, and drop tables — none are reversible
> without the backup.

---

## 1. Index Cleanup & Additions

Goal: remove duplicate/redundant indexes and add composite indexes that match
actual query patterns (dashboards, reports, datatable filters).

| File | Purpose |
|------|---------|
| `2026_04_08_100001_drop_duplicate_indexes.php` | Drop 20 duplicate `idx_*` indexes across 6 tables (now idempotent). |
| `2026_04_08_100002_add_audit_trails_indexes.php` | Indexes on `audit_trails` / `audit_trail_changes` for lookups. |
| `2026_04_08_100004_add_deleted_at_composite_indexes.php` | `(col, deleted_at)` composites to speed up soft-delete filters. |
| `2026_04_08_100005_add_pivot_and_active_indexes.php` | Pivot-table and `is_active` composite indexes. |
| `2026_04_08_100010_add_report_performance_indexes.php` | Indexes supporting report queries (revenue, stock, etc.). |
| `2026_04_08_100016_add_created_at_indexes.php` | `created_at` indexes for time-range reports. |
| `2026_04_08_100018_add_composite_query_indexes.php` | Composite indexes for hot multi-column WHEREs. |
| `2026_04_08_100020_drop_redundant_indexes.php` | Second-pass redundant-index cleanup. |
| `2026_04_08_100025_add_missing_indexes_batch2.php` | Missing FK-column indexes (batch 2). |
| `2026_04_08_100033_drop_redundant_indexes_batch2.php` | Third-pass redundant-index cleanup. |
| `2026_04_09_100039_add_missing_indexes_batch3.php` | Missing indexes batch 3. |
| `2026_04_09_100042_optimize_slow_query_indexes.php` | Indexes derived from slow-query log. |
| `2026_04_09_105755_add_remaining_fk_indexes_batch4.php` | Remaining FK-column indexes (batch 4). |

---

## 2. Foreign Key Constraints

Goal: add real FK constraints where only FK-named columns existed. Done in
batches because many were blocked by orphan rows / signed-int mismatches and
had to follow column fixes.

| File | Purpose |
|------|---------|
| `2026_04_08_100008_add_foreign_key_constraints.php` | Batch 1. |
| `2026_04_08_100014_add_more_foreign_key_constraints.php` | Batch 2. |
| `2026_04_08_100019_add_fk_constraints_batch3.php` | Batch 3. |
| `2026_04_08_100022_add_fk_constraints_batch4.php` | Batch 4. |
| `2026_04_08_100024_fix_pivot_table_constraints.php` | Pivot-table FKs. |
| `2026_04_08_100026_add_fk_constraints_batch5.php` | Batch 5. |
| `2026_04_08_100037_add_fk_constraints_batch6.php` | Batch 6. |
| `2026_04_08_100038_add_fk_constraints_batch7.php` | Batch 7. |

> If FK creation fails on live, the cause is almost always orphan rows. Find
> them with a `LEFT JOIN … WHERE parent.id IS NULL` and clean or null them out.

---

## 3. Column Type Fixes

Goal: stop storing money in `varchar`/`double`, booleans in `tinyint(1)` that
were actually `int`, and FK columns as `bigint unsigned` when parents are
signed `int`.

| File | Purpose |
|------|---------|
| `2026_04_08_100006_convert_varchar_money_to_decimal.php` | `varchar` money → `DECIMAL(15,2)`. |
| `2026_04_08_100011_fix_boolean_column_types.php` | Normalize boolean columns. |
| `2026_04_08_100015_fix_remaining_boolean_and_int_types.php` | Second-pass bool/int fixes. |
| `2026_04_08_100017_fix_signed_int_fk_columns.php` | Align FK column signedness. |
| `2026_04_08_100023_fix_remaining_signed_int_columns.php` | Remaining signed-int fixes. |
| `2026_04_08_100030_convert_financial_doubles_to_decimal.php` | `double` money → `DECIMAL`. |
| `2026_04_08_100031_convert_double_to_decimal.php` | Remaining `double` → `DECIMAL`. |
| `2026_04_08_100036_fix_bigint_to_int_fk_columns.php` | `bigint` FK → `int` to match parents. |
| `2026_04_08_100034_fix_parent_id_and_planid.php` | `parent_id` / `plan_id` type fixes. |
| `2026_04_09_140000_widen_services_sort_no_to_int.php` | `services.sort_no` → `int`. |

---

## 4. Column Rightsizing & Encoding

Goal: shrink oversized `varchar(255)`/`TEXT`, normalize collation, widen only
the columns that actually need it (encrypted fields).

| File | Purpose |
|------|---------|
| `2026_04_08_100003_fix_collation_mismatch.php` | Align collation across tables (fixes join warnings). |
| `2026_04_08_100013_rightsize_varchar_columns.php` | Trim oversized `varchar`. |
| `2026_04_08_100021_rightsize_text_columns.php` | Convert `TEXT` → right-sized `varchar` where safe. |
| `2026_04_08_100027_convert_text_to_json_columns.php` | `TEXT` holding JSON → native `JSON`. |
| `2026_04_08_100035_rightsize_varchar191_columns.php` | Shrink `varchar(191)` where shorter is enough. |
| `2026_04_09_100041_convert_clean_varchars_to_enum.php` | `varchar` with fixed value set → `ENUM`. |
| `2026_04_09_100043_widen_encrypted_columns.php` | Widen columns that hold AES ciphertext. |
| `2026_04_09_120000_widen_operator_settings_password_for_encryption.php` | Same — operator_settings. |
| `2026_04_09_120001_widen_users_cnic_for_encryption.php` | Same — users.cnic. |

---

## 5. Archival & Partitioning

Goal: keep hot tables small; move historical rows to archive tables and
partition the biggest one by month.

| File | Purpose |
|------|---------|
| `2026_04_08_100007_create_audit_trail_archive_tables.php` | Archive shell for audit trail. |
| `2026_04_08_100009_partition_audit_trail_changes.php` | `RANGE` partition `audit_trail_changes` by month. |
| `2026_04_08_100012_create_sms_logs_archive_table.php` | Archive shell for sms_logs. |

> Partitioning DDL can take minutes on a large table — run during a quiet window.

---

## 6. Dead-Table / Dead-Column Cleanup

| File | Purpose |
|------|---------|
| `2026_04_08_100028_drop_pabao_records_tables.php` | Drop legacy pabao_* tables. |
| `2026_04_08_100029_drop_unused_tables.php` | Drop unused tables (batch 1). |
| `2026_04_08_100030_drop_dead_tables_batch2.php` | Drop unused tables (batch 2). |
| `2026_04_08_100032_make_account_id_not_null.php` | Backfill + enforce NOT NULL on `account_id`. |

> Run these only after confirming nothing in the codebase references those tables.

---

## 7. Integrity / Safety

| File | Purpose |
|------|---------|
| `2026_04_09_100000_add_cashflow_audit_log_immutability_triggers.php` | Triggers that block UPDATE/DELETE on `cashflow_audit_logs`. |
| `2026_04_09_100040_db_optimization_safe_batch.php` | Grab-bag of safe, low-risk tweaks (OPTIMIZE, small column nudges). |
| `2026_04_09_111911_rename_tax_percenatage_to_tax_percentage.php` | Column typo fix. |

---

## Suggested Live Run Order

1. Take full backup (mariadb-dump or snapshot).
2. **Category 1** (indexes) — safe, online.
3. **Category 3** (column types) — medium risk; test rollback plan.
4. **Category 4** (rightsizing/encoding) — medium risk.
5. **Category 2** (FK constraints) — must come after type fixes.
6. **Category 5** (archival/partitioning) — schedule maintenance window.
7. **Category 6** (dead tables) — only after grep confirms zero references.
8. **Category 7** (integrity triggers) — last.
9. Run `php artisan migrate:status` to confirm every file is in the `Ran` column.

## After Live Run

- `ANALYZE TABLE` the largest tables (`audit_trail_changes`, `sms_logs`,
  `package_advances`, `appointments`, `patients`, `invoices`).
- Watch slow-query log for 24h; most queries that previously ran full scans
  should now show `Using index`.
