# Cash Flow & Expense Management Module — Implementation Plan

## Development Rules (MUST follow throughout)

### Rule 1: Zero Impact on Existing CRM
- **NEVER** modify existing models, controllers, services, routes, or JS files
- Only exceptions: single migration adding `is_advance_eligible` to `users` table, and appending sidebar entry
- Observer on `Locations` registered via new `CashflowServiceProvider` (not editing the model)
- All new code in isolated namespaces: `Models/CashFlow/`, `Services/CashFlow/`, `Requests/CashFlow/`
- Routes in a dedicated `routes/cashflow.php` file, `require`d inside the `auth.common` group in `api.php`
- Permissions added via seeder — no modification to existing permission records
- **Verify** existing CRM functionality is unaffected after each phase

### Rule 2: REUSE Everything Already in CRM (do NOT reinvent)
- **USE** `Locations` model directly for branches (queries, relationships, dropdowns)
- **USE** `User` model for staff, auth, `created_by` references
- **USE** `user_has_locations` for branch filtering — same scope pattern as other modules
- **USE** `PaymentModes` model for payment method dropdowns
- **USE** `PackageAdvances` model for patient payment inflows (read-only, filtered by go-live date)
- **USE** Spatie permission system: `Gate::allows()`, `@can()`, new permissions via seeder
- **USE** `Auth::user()`, `account_id` pattern exactly as other modules
- **USE** Metronic UI: datatables, modals, toasts, searchable dropdowns, same CSS classes
- **USE** `activeMenu()`/`openMenu()` helpers for sidebar
- **USE** existing helper patterns (`GeneralFunctions`, `Filters`, etc.) where applicable
- **REFERENCE** existing `AuditTrails` pattern (cashflow has its own immutable log but follows similar structure)

### Rule 3: Optimized Development Approach
- **Service layer** architecture (matching existing Lead, Bundle, Service module patterns)
- **Cached balances** via Observers — dashboard reads cache; reports use true SUM() calculation
- **Eager loading** on all list views (prevent N+1)
- **Database indexes** on frequently queried columns
- **Form Request** classes for all validation (not inline)
- **DB::transaction** for multi-table operations (expense + vendor payment, void + reversal, approval balance changes)
- **API controllers** for data operations, **web controllers** for view-only routes
- **Cache** lookup data (categories, pools, vendors) with 1-hour TTL
- **Chunked queries** for large report datasets
- **Queued jobs** for email sending (digest, monthly report)
- **Minimize JS/CSS** — use libraries already loaded in CRM (jQuery, Bootstrap, Chart.js/ApexCharts if present)

---

## CRM Integration Points Discovered

Before planning, I explored the existing codebase. Key findings:

| Spec Term | CRM Reality |
|-----------|------------|
| "Branches" | `locations` table, `Locations` model |
| User-branch assignment | `user_has_locations` pivot (user_id, location_id) |
| Permission system | **Spatie Laravel Permission** — parent/child hierarchy, `Gate::allows()` |
| Patient payments (inflows) | `package_advances` table, `cash_flow = 'in'`, has `payment_mode_id`, `location_id` |
| Payment methods | `payment_modes` table, `PaymentModes` model |
| UI framework | Metronic theme, Line-awesome icons, `activeMenu()`/`openMenu()` sidebar helpers |
| Notification system | **Does NOT exist** — must build from scratch |
| Service layer pattern | `app/Services/{Module}/{Module}Service.php` |
| Scheduled jobs | Laravel Kernel.php scheduler already in use |

---

## Phase 1: Database & Foundation

**Goal:** All tables, models, observers, permissions, audit service. No UI yet.

### Step 1.1 — Migrations (14 tables)

Create all migrations in a single batch. Order matters for foreign keys.

1. `cashflow_settings` — key-value config (go_live_date, thresholds, digest config)
2. `cash_pools` — type (branch_cash/head_office_cash/bank_account), location_id (nullable FK→locations), name, cached_balance DECIMAL(15,2), opening_balance, is_active, soft deletes
3. `expense_categories` — name (unique), description, vendor_emphasis (boolean), is_active, sort_order, soft deletes
4. `vendors` — name (unique), contact_person, phone, email, address, payment_terms (enum), category, opening_balance, cached_balance, is_active, notes, account_id, soft deletes
5. `expenses` — expense_date, amount DECIMAL(15,2), category_id FK, paid_from_pool_id FK, for_branch_id (nullable FK→locations, null=General), payment_method_id FK→payment_modes, vendor_id (nullable FK), staff_id (nullable FK→users), description, reference_no, attachment_url, notes, status (enum: approved/pending/rejected), verified_by (nullable FK→users), rejection_reason, is_flagged, flag_reason, created_by FK→users, voided_at, voided_by, void_reason, edit_reason, account_id, soft deletes
6. `cash_transfers` — transfer_date, amount DECIMAL(15,2), from_pool_id FK, to_pool_id FK, method (enum: physical_cash/bank_deposit), reference_no, attachment_url, description, created_by FK→users, account_id, soft deletes
7. `vendor_transactions` — vendor_id FK, type (enum: purchase/payment), amount DECIMAL(15,2), expense_id (nullable FK), description, reference_no, created_by FK→users, account_id, soft deletes
8. `vendor_requests` — name, phone, note, requested_by FK→users, status (enum: pending/approved/dismissed), admin_notes, account_id
9. `category_requests` — name, description, requested_by FK→users, status (enum: pending/approved/dismissed), admin_notes, account_id
10. `staff_advances` — user_id FK→users, pool_id FK, amount DECIMAL(15,2), description, created_by FK→users, account_id, soft deletes
11. `staff_returns` — user_id FK→users, pool_id FK, amount DECIMAL(15,2), description, created_by FK→users, account_id, soft deletes
12. `period_locks` — month (1-12), year, locked_by FK→users, balance_snapshot JSON, unlock_reason (nullable), unlocked_by (nullable FK→users), unlocked_at (nullable)
13. `cashflow_audit_logs` — timestamp, user_id (nullable FK→users), action (enum), entity_type, entity_id, old_values JSON, new_values JSON, reason (nullable), ip_address — **NO soft deletes, NO update**
14. `cashflow_notifications` — user_id FK→users, type, title, message, data JSON, read_at (nullable)
15. Migration to add `is_advance_eligible` boolean (default 0) to `users` table

### Step 1.2 — Eloquent Models (14 models)

All in `app/Models/CashFlow/` namespace to keep organized:

- `CashPool`, `ExpenseCategory`, `Expense`, `CashTransfer`, `Vendor`, `VendorTransaction`, `VendorRequest`, `CategoryRequest`, `StaffAdvance`, `StaffReturn`, `PeriodLock`, `CashflowAuditLog`, `CashflowSetting`, `CashflowNotification`

Each with: fillable, relationships, scopes, SoftDeletes (where applicable).
`CashflowAuditLog`: NO SoftDeletes, NO update method override.

### Step 1.3 — Observers

- **LocationObserver** — on Locations model: when new active location created → auto-create cash_pool (type=branch_cash). When deactivated → soft-deactivate pool, warn if balance > 0.
- **ExpenseObserver** — on Expense: update cash_pool.cached_balance, vendor.cached_balance
- **CashTransferObserver** — update both pool cached_balances
- **VendorTransactionObserver** — update vendor.cached_balance
- **StaffAdvanceObserver** — update pool.cached_balance
- **StaffReturnObserver** — update pool.cached_balance

### Step 1.4 — Permissions Seeder

Create parent permission `cashflow_manage` + children following existing pattern:

```
cashflow_manage (parent, main_group=1)
├── cashflow_dashboard
├── cashflow_fdm_view
├── cashflow_expense_create
├── cashflow_expense_edit
├── cashflow_expense_approve
├── cashflow_expense_void
├── cashflow_transfer_create
├── cashflow_vendor_manage
├── cashflow_vendor_ledger_view
├── cashflow_vendor_transaction
├── cashflow_staff_advance
├── cashflow_category_manage
├── cashflow_pool_manage
├── cashflow_period_lock
├── cashflow_audit_view
├── cashflow_settings
├── cashflow_reports
└── cashflow_reports_export
```

### Step 1.5 — Core Services

- `app/Services/CashFlow/CashflowAuditService.php` — write-only logging service
- `app/Services/CashFlow/CashflowSettingService.php` — get/set helpers
- `app/Helpers/CashflowHelper.php` — branch filtering trait/scope, payment method helpers
- `app/Rules/GoogleDriveUrlRule.php` — custom validation rule
- `app/Exceptions/CashflowException.php` — custom exception

### Step 1.6 — Seed Default Data

- 13 default expense categories with vendor_emphasis flags
- Default settings: go_live_date (null until set), approval_threshold=10000, backdate_flag_days=7, daily_auto_approved_limit=50000, advance_aging_days=15, cumulative_advance_threshold=100000, dormant_vendor_days=90, void_alert_days=7, digest_send_time=08:00, digest_recipients=''

### Step 1.7 — Test Phase 1

- Run migrations, verify all 14+1 tables created
- Test LocationObserver: create active location → verify pool auto-created
- Test cached_balance observer updates
- Test branch filtering scope
- Verify permissions seeded correctly

---

## Phase 2: Settings Screen + Core Expense Flow

**Goal:** Admin can configure module. Accountant can enter and manage expenses.

### Step 2.1 — Routes

**Web routes** (view routes only):
```
Route::prefix('cashflow')->group(function() {
    Route::get('/', 'CashFlowController@dashboard')->name('admin.cashflow.dashboard');
    Route::get('/expenses', 'CashFlowController@expenses')->name('admin.cashflow.expenses');
    Route::get('/transfers', 'CashFlowController@transfers')->name('admin.cashflow.transfers');
    Route::get('/vendors', 'CashFlowController@vendors')->name('admin.cashflow.vendors');
    Route::get('/staff', 'CashFlowController@staff')->name('admin.cashflow.staff');
    Route::get('/reports', 'CashFlowController@reports')->name('admin.cashflow.reports');
    Route::get('/settings', 'CashFlowController@settings')->name('admin.cashflow.settings');
    Route::get('/fdm', 'CashFlowController@fdmView')->name('admin.cashflow.fdm');
});
```

**API routes** (data operations):
```
Route::prefix('cashflow')->group(function() {
    // Settings
    Route::get('settings/data', ...)->name('admin.cashflow.settings.data');
    Route::post('settings/update', ...)->name('admin.cashflow.settings.update');
    Route::post('settings/reset-module', ...)->name('admin.cashflow.settings.reset');
    
    // Pools
    Route::get('pools', ...)->name('admin.cashflow.pools.index');
    Route::post('pools/{id}/update', ...)->name('admin.cashflow.pools.update');
    
    // Categories
    Route::get('categories', ...)->name('admin.cashflow.categories.index');
    Route::post('categories/store', ...)->name('admin.cashflow.categories.store');
    Route::post('categories/{id}/update', ...)->name('admin.cashflow.categories.update');
    Route::post('categories/{id}/toggle', ...)->name('admin.cashflow.categories.toggle');
    
    // Expenses
    Route::get('expenses/data', ...)->name('admin.cashflow.expenses.data');
    Route::post('expenses/store', ...)->name('admin.cashflow.expenses.store');
    Route::post('expenses/{id}/approve', ...)->name('admin.cashflow.expenses.approve');
    Route::post('expenses/{id}/reject', ...)->name('admin.cashflow.expenses.reject');
    Route::post('expenses/{id}/edit', ...)->name('admin.cashflow.expenses.edit');
    Route::post('expenses/{id}/void', ...)->name('admin.cashflow.expenses.void');
    Route::post('expenses/{id}/resubmit', ...)->name('admin.cashflow.expenses.resubmit');
    
    // Notifications
    Route::get('notifications', ...)->name('admin.cashflow.notifications.index');
    Route::post('notifications/mark-read', ...)->name('admin.cashflow.notifications.markread');
});
```

### Step 2.2 — Controllers

- `app/Http/Controllers/Admin/CashFlowController.php` — web views (8 screens)
- `app/Http/Controllers/Api/CashFlowController.php` — API endpoints

### Step 2.3 — Services

- `app/Services/CashFlow/CashflowSettingService.php` — settings CRUD
- `app/Services/CashFlow/PoolService.php` — pool management, balance calc
- `app/Services/CashFlow/CategoryService.php` — category CRUD
- `app/Services/CashFlow/ExpenseService.php` — expense CRUD, approval flow, void, flagging
- `app/Services/CashFlow/NotificationService.php` — create/read/mark-read notifications

### Step 2.4 — Form Requests

- `StoreExpenseRequest` — all validation rules per spec
- `UpdateExpenseRequest` — admin edit validation
- `RejectExpenseRequest` — rejection_reason mandatory
- `VoidExpenseRequest` — void_reason mandatory (min 10 chars)

### Step 2.5 — Views

- `resources/views/admin/cashflow/settings.blade.php` — Settings screen (Screen 7)
  - Go-live date, pool management, categories with vendor emphasis toggle, thresholds, advance-eligible users, payment method mapping
- `resources/views/admin/cashflow/expenses.blade.php` — Expenses screen (Screen 2)
  - Expense modal form, list with search/filters/badges
- Add sidebar entry for Cash Flow module

### Step 2.6 — JavaScript

- `public/assets/js/pages/admin_settings/cashflow-settings.js`
- `public/assets/js/pages/admin_settings/cashflow-expenses.js`
- `public/assets/js/pages/crud/forms/validation/admin_settings/cashflow-expenses.js`

### Step 2.7 — Notification UI (Bell Icon)

- Add bell icon to header partial (will be reusable by other modules)
- AJAX polling every 30-60 seconds
- Unread count badge
- Dropdown list of recent notifications

### Step 2.8 — Test Phase 2

- Settings: save/load all settings, go-live date, pool balances, categories CRUD
- Expense full lifecycle: create below/above 10k, approve, reject, resubmit, edit (admin), void
- Permission enforcement: accountant vs admin vs branch manager
- Cash attachment mandatory for cash payments
- Notification delivery on expense events
- Audit trail entries for all actions

---

## Phase 3: Transfers + Vendor & Staff Ledgers

**Goal:** All transaction types working. Complete data entry capability.

### Step 3.1 — API Routes (additions)

```
// Transfers
Route::get('transfers/data', ...);
Route::post('transfers/store', ...);

// Vendors
Route::get('vendors/data', ...);
Route::post('vendors/store', ...);
Route::post('vendors/{id}/update', ...);
Route::post('vendors/{id}/toggle', ...);
Route::get('vendors/{id}/ledger', ...);
Route::post('vendors/{id}/purchase', ...);
Route::post('vendor-requests/store', ...);
Route::get('vendor-requests/data', ...);
Route::post('vendor-requests/{id}/action', ...);

// Categories suggestions
Route::post('category-requests/store', ...);
Route::get('category-requests/data', ...);
Route::post('category-requests/{id}/action', ...);

// Staff
Route::get('staff/data', ...);
Route::get('staff/{id}/ledger', ...);
Route::post('staff/advance', ...);
Route::post('staff/return', ...);
```

### Step 3.2 — Services

- `app/Services/CashFlow/TransferService.php` — transfer CRUD
- `app/Services/CashFlow/VendorService.php` — vendor CRUD, ledger, purchase, payment linkage
- `app/Services/CashFlow/StaffLedgerService.php` — advance, return, balance

### Step 3.3 — Form Requests

- `StoreTransferRequest`, `StoreVendorRequest`, `UpdateVendorRequest`
- `StoreVendorPurchaseRequest`, `StoreStaffAdvanceRequest`, `StoreStaffReturnRequest`
- `StoreVendorSuggestionRequest`, `StoreCategorySuggestionRequest`

### Step 3.4 — Views

- `resources/views/admin/cashflow/transfers.blade.php` — Screen 3
- `resources/views/admin/cashflow/vendors.blade.php` — Screen 4 (master-detail)
- `resources/views/admin/cashflow/staff.blade.php` — Screen 5 (master-detail)

### Step 3.5 — JavaScript

- `cashflow-transfers.js`, `cashflow-vendors.js`, `cashflow-staff.js`
- Corresponding validation files

### Step 3.6 — Test Phase 3

- Transfer: both methods, reference+attachment mandatory, balance updates
- Vendor: full lifecycle including request→approve→link flow
- Vendor upfront shortcut (purchase+payment in DB::transaction)
- Staff: advance→expense→return cycle, warn existing balance, return cannot exceed
- Perfect-match advance flag
- Cross-model balance accuracy

---

## Phase 4: Dashboard + FDM Screen + Reports

**Goal:** Visualize all data. Export capabilities.

### Step 4.1 — Services

- `app/Services/CashFlow/DashboardService.php` — widget data, cached calculations
- `app/Services/CashFlow/ReportService.php` — cash flow statement, 8 secondary reports
- `app/Services/CashFlow/ExportService.php` — PDF/Excel generation

### Step 4.2 — Views

- `resources/views/admin/cashflow/dashboard.blade.php` — Screen 1
  - Quick-action bar (modals), pending actions widget, summary cards
  - Pool balance cards, daily trend chart, category charts
  - Vendor outstanding, staff advances, recent entries
  - Reconciliation check button (admin)
- `resources/views/admin/cashflow/fdm.blade.php` — Screen 8
  - Big balance card, 10-day transaction log, read-only
- `resources/views/admin/cashflow/reports.blade.php` — Screen 6
  - Cash flow statement + 8 secondary reports

### Step 4.3 — JavaScript

- `cashflow-dashboard.js` — charts (Chart.js or ApexCharts), widget interactions
- `cashflow-reports.js` — filters, export triggers
- `cashflow-fdm.js` — simple read-only display

### Step 4.4 — API Routes (additions)

```
// Dashboard
Route::get('dashboard/data', ...);
Route::get('dashboard/reconciliation', ...);

// FDM
Route::get('fdm/data', ...);

// Reports
Route::get('reports/cashflow-statement', ...);
Route::get('reports/branch-comparison', ...);
Route::get('reports/category-trend', ...);
Route::get('reports/vendor-outstanding', ...);
Route::get('reports/staff-advance', ...);
Route::get('reports/daily-movement', ...);
Route::get('reports/transfer-log', ...);
Route::get('reports/flagged-entries', ...);
Route::get('reports/dormant-vendors', ...);
Route::get('reports/export/{type}', ...);
```

### Step 4.5 — Test Phase 4

- Dashboard loads < 2 seconds
- All widgets show correct data
- Branch filtering per role (admin=all, branch manager=own, FDM=own)
- FDM sees only own branch, read-only
- Reports match manual calculations
- PDF/Excel export works
- Reconciliation check: green/red result

---

## Phase 5: Security Automation + Scheduled Jobs + Polish

**Goal:** Harden everything. Automate detection. Final touches.

### Step 5.1 — Auto-Flagging Service

`app/Services/CashFlow/FlaggingService.php`:

| Trigger | Logic |
|---------|-------|
| Backdated | created_at - expense_date > 7 days |
| No attachment (cash) | payment_method=cash, attachment null |
| Advance perfect-match | expenses = advance, zero return |
| Negative pool | cached_balance < 0 |
| Vendor overpayment | payment > outstanding |
| Duplicate payment | same vendor + same amount within 24hrs |
| Admin self-approval | created_by = verified_by |
| Daily splitting | total auto-approved in 1 day > 50k |
| Vendor pending | expense without vendor (request pending) |

### Step 5.2 — Period Locking

- Lock: sequential, snapshot balances, freeze opening balances + go-live after first lock
- Unlock: mandatory reason, both logged in audit
- Module reset: first month only, double confirmation, disabled after first lock

### Step 5.3 — Scheduled Jobs

- `app/Console/Commands/CashflowDailyDigest.php` — daily email at configurable time
- `app/Console/Commands/CashflowMonthlyReport.php` — 1st of each month
- `app/Mail/CashflowDailyDigest.php` — Mailable
- `app/Mail/CashflowMonthlyReport.php` — Mailable
- Register in Kernel.php

### Step 5.4 — Keyboard Shortcuts

- Alt+E → Add Expense modal
- Alt+T → Add Transfer modal
- Ctrl+Enter → Save form
- Escape → Close modal

### Step 5.5 — Security Testing

- Permission bypass via URL/API
- Branch filtering: users cannot see other branches' data
- Audit trail integrity (no delete/update routes)
- Period lock enforcement
- Opening balance freeze after first lock

### Step 5.6 — Performance Optimization

- Verify indexes on: (expense_date, pool), (expense_date, for_branch_id), (vendor_id), (staff_id), (is_flagged), (status), (voided_at)
- EXPLAIN on critical queries
- N+1 detection on list views
- Dashboard < 2s, expense list < 1s, reports < 5s

### Step 5.7 — Test Documentation

- Full test documentation: what tested, expected vs actual, pass/fail

---

## File Structure Summary

```
app/
├── Console/Commands/
│   ├── CashflowDailyDigest.php
│   └── CashflowMonthlyReport.php
├── Exceptions/
│   └── CashflowException.php
├── Helpers/
│   └── CashflowHelper.php
├── Http/
│   ├── Controllers/
│   │   ├── Admin/CashFlowController.php         (web views)
│   │   └── Api/CashFlowController.php           (API endpoints)
│   └── Requests/CashFlow/
│       ├── StoreExpenseRequest.php
│       ├── UpdateExpenseRequest.php
│       ├── RejectExpenseRequest.php
│       ├── VoidExpenseRequest.php
│       ├── StoreTransferRequest.php
│       ├── StoreVendorRequest.php
│       ├── UpdateVendorRequest.php
│       ├── StoreVendorPurchaseRequest.php
│       ├── StoreStaffAdvanceRequest.php
│       ├── StoreStaffReturnRequest.php
│       ├── StoreVendorSuggestionRequest.php
│       └── StoreCategorySuggestionRequest.php
├── Mail/
│   ├── CashflowDailyDigest.php
│   └── CashflowMonthlyReport.php
├── Models/CashFlow/
│   ├── CashPool.php
│   ├── ExpenseCategory.php
│   ├── Expense.php
│   ├── CashTransfer.php
│   ├── Vendor.php
│   ├── VendorTransaction.php
│   ├── VendorRequest.php
│   ├── CategoryRequest.php
│   ├── StaffAdvance.php
│   ├── StaffReturn.php
│   ├── PeriodLock.php
│   ├── CashflowAuditLog.php
│   ├── CashflowSetting.php
│   └── CashflowNotification.php
├── Observers/
│   ├── LocationCashflowObserver.php
│   ├── ExpenseObserver.php
│   ├── CashTransferObserver.php
│   ├── VendorTransactionObserver.php
│   ├── StaffAdvanceObserver.php
│   └── StaffReturnObserver.php
├── Rules/
│   └── GoogleDriveUrlRule.php
└── Services/CashFlow/
    ├── CashflowAuditService.php
    ├── CashflowSettingService.php
    ├── PoolService.php
    ├── CategoryService.php
    ├── ExpenseService.php
    ├── TransferService.php
    ├── VendorService.php
    ├── StaffLedgerService.php
    ├── NotificationService.php
    ├── DashboardService.php
    ├── ReportService.php
    ├── ExportService.php
    └── FlaggingService.php

database/migrations/
├── xxxx_create_cashflow_settings_table.php
├── xxxx_create_cash_pools_table.php
├── xxxx_create_expense_categories_table.php
├── xxxx_create_vendors_table.php
├── xxxx_create_expenses_table.php
├── xxxx_create_cash_transfers_table.php
├── xxxx_create_vendor_transactions_table.php
├── xxxx_create_vendor_requests_table.php
├── xxxx_create_category_requests_table.php
├── xxxx_create_staff_advances_table.php
├── xxxx_create_staff_returns_table.php
├── xxxx_create_period_locks_table.php
├── xxxx_create_cashflow_audit_logs_table.php
├── xxxx_create_cashflow_notifications_table.php
└── xxxx_add_is_advance_eligible_to_users_table.php

database/seeders/
├── CashflowPermissionsSeeder.php
├── CashflowCategoriesSeeder.php
└── CashflowSettingsSeeder.php

resources/views/admin/cashflow/
├── dashboard.blade.php        (Screen 1)
├── expenses.blade.php         (Screen 2)
├── transfers.blade.php        (Screen 3)
├── vendors.blade.php          (Screen 4)
├── staff.blade.php            (Screen 5)
├── reports.blade.php          (Screen 6)
├── settings.blade.php         (Screen 7)
└── fdm.blade.php              (Screen 8)

public/assets/js/pages/admin_settings/
├── cashflow-dashboard.js
├── cashflow-expenses.js
├── cashflow-transfers.js
├── cashflow-vendors.js
├── cashflow-staff.js
├── cashflow-reports.js
├── cashflow-settings.js
└── cashflow-fdm.js

public/assets/js/pages/crud/forms/validation/admin_settings/
├── cashflow-expenses.js
├── cashflow-transfers.js
├── cashflow-vendors.js
└── cashflow-staff.js
```

---

## Execution Strategy

We will work **one phase at a time, one step at a time**, testing each step before moving to the next.

**Within each phase:**
1. Create migrations first, run them
2. Create models with relationships
3. Create services with business logic
4. Create form requests for validation
5. Create API controller endpoints
6. Create web controller + views
7. Create JavaScript files
8. Test everything in that phase

**Conversation management:** Given the size of this module (~60+ files), we'll likely need multiple conversations. Each phase is self-contained and testable. We'll maintain this doc as a living reference.

**Estimated effort:** This is a large module. Roughly:
- Phase 1: 15-20 files (migrations, models, observers, seeders, core services)
- Phase 2: 15-20 files (settings + expenses — the biggest phase)
- Phase 3: 10-15 files (transfers, vendors, staff)
- Phase 4: 10-15 files (dashboard, FDM, reports)
- Phase 5: 5-10 files (flagging, scheduled jobs, polish)
