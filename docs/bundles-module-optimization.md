# Bundles Module Optimization

## Overview

The Bundles module has been refactored to follow a service layer architecture pattern, consistent with the Leads and Services modules. This document outlines the changes made and the new structure.

## Architecture

### Directory Structure

```
app/
├── Exceptions/
│   └── BundleException.php              # Custom exception handling
├── Helpers/
│   └── BundleHelper.php                 # Utility functions with caching
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   └── BundlesController.php    # View-only controller (index)
│   │   └── Api/
│   │       └── BundlesController.php    # API controller (CRUD operations)
│   └── Requests/
│       └── Bundle/
│           ├── StoreBundleRequest.php
│           ├── UpdateBundleRequest.php
│           ├── StoreConfigurableBundleRequest.php
│           ├── UpdateConfigurableBundleRequest.php
│           └── UpdateBundleStatusRequest.php
├── Services/
│   └── Bundle/
│       └── BundleService.php            # Business logic
└── Models/
    ├── Bundles.php                      # Model (slimmed down)
    ├── BundleHasServices.php
    └── BundleServicesPriceHistory.php
```

## Components

### 1. BundleException (`app/Exceptions/BundleException.php`)

Custom exception class for bundle-specific errors with static factory methods:

- `notFound()` - Bundle not found (404)
- `validationFailed()` - Validation errors (422)
- `hasChildRecords()` - Cannot delete bundle with dependencies (409)
- `unauthorized()` - Permission denied (403)
- `invalidDateRange()` - Invalid date range (422)
- `serviceNotFound()` - Service not found (404)
- `operationFailed()` - General operation failure (500)

### 2. BundleHelper (`app/Helpers/BundleHelper.php`)

Utility class with caching for frequently accessed data:

**Cached Methods (1-hour TTL):**
- `getServices()` - Get active services list
- `getServicesForDropdown()` - Get services as key-value pairs
- `getTaxTreatmentTypes()` - Get tax treatment types
- `getActiveBundles()` - Get active bundles within date range

**Utility Methods:**
- `calculatePrices()` - Calculate proportional service prices
- `hasChildRecords()` - Check for dependent records (PackageBundles, Appointments)
- `formatForDatatable()` - Format bundle for datatable display
- `isValidDateRange()` - Validate start/end dates
- `calculateTotalServicesPrice()` - Sum service prices
- `getFilterValues()` - Get all filter dropdown values
- `clearCache()` - Clear all bundle-related caches

### 3. BundleService (`app/Services/Bundle/BundleService.php`)

Main business logic class with the following methods:

**Datatable:**
- `getDatatableRecords()` - Get paginated bundles with filters

**Simple Bundles:**
- `createBundle()` - Create a simple bundle
- `updateBundle()` - Update a simple bundle
- `getBundleForEdit()` - Get bundle data for editing

**Configurable Bundles:**
- `createConfigurableBundle()` - Create a configurable bundle
- `updateConfigurableBundle()` - Update a configurable bundle
- `getConfigurableBundleForEdit()` - Get configurable bundle data for editing

**Common Operations:**
- `deleteBundle()` - Delete a bundle (with child record check)
- `updateStatus()` - Activate/deactivate a bundle
- `getBundleDetails()` - Get bundle details for display

### 4. Form Requests (`app/Http/Requests/Bundle/`)

Dedicated validation classes:

| Request | Purpose |
|---------|---------|
| `StoreBundleRequest` | Validate simple bundle creation |
| `UpdateBundleRequest` | Validate simple bundle update |
| `StoreConfigurableBundleRequest` | Validate configurable bundle creation |
| `UpdateConfigurableBundleRequest` | Validate configurable bundle update |
| `UpdateBundleStatusRequest` | Validate status change (with permission check) |

### 5. API Controller (`app/Http/Controllers/Api/BundlesController.php`)

RESTful API controller with dependency injection:

| Method | Route | Description |
|--------|-------|-------------|
| `datatable` | POST `/bundles/datatable` | Get paginated bundles |
| `store` | POST `/bundles` | Create simple bundle |
| `storeConfigurable` | POST `/bundles/configurable` | Create configurable bundle |
| `edit` | GET `/bundles/{id}/edit` | Get bundle for editing |
| `editConfigurable` | GET `/bundles/editconf/{id}` | Get configurable bundle for editing |
| `update` | PUT `/bundles/{id}` | Update simple bundle |
| `updateConfigurable` | PUT `/bundles/configurable/{id}` | Update configurable bundle |
| `destroy` | DELETE `/bundles/{id}` | Delete bundle |
| `status` | POST `/bundles/status` | Change bundle status |
| `detail` | GET `/bundles/detail/{id}` | Get bundle details |

## Routes

### API Routes (`routes/api.php`)

```php
Route::prefix('bundles')->name('bundles.')->group(function () {
    Route::post('datatable', [BundlesController::class, 'datatable'])->name('datatable');
    Route::post('status', [BundlesController::class, 'status'])->name('status');
    Route::get('detail/{id}', [BundlesController::class, 'detail'])->name('detail');
    Route::get('{id}/edit', [BundlesController::class, 'edit'])->name('edit');
    Route::get('editconf/{id}', [BundlesController::class, 'editConfigurable'])->name('editconf');
    Route::post('/', [BundlesController::class, 'store'])->name('store');
    Route::post('configurable', [BundlesController::class, 'storeConfigurable'])->name('store.configurable');
    Route::put('{id}', [BundlesController::class, 'update'])->name('update');
    Route::put('configurable/{id}', [BundlesController::class, 'updateConfigurable'])->name('update.configurable');
    Route::delete('{id}', [BundlesController::class, 'destroy'])->name('destroy');
});
```

### Web Routes (`routes/web.php`)

```php
// View-only route
Route::get('bundles', [AdminBundlesController::class, 'index'])
    ->name('bundles.index')
    ->middleware('permission:packages_manage');
```

## Bug Fixes

### 1. Fixed `isChildExists()` Always Returning False

**Before:** The method was commented out and always returned `false`.

**After:** `BundleHelper::hasChildRecords()` properly checks:
- `PackageBundles` table for bundle usage in plans
- `Appointments` table for bundle usage in appointments

### 2. Fixed Wrong Permission Gates in Status Method

**Before:** Used `regions_inactive` and `regions_active` permissions.

**After:** Uses correct `packages_inactive` and `packages_active` permissions via `UpdateBundleStatusRequest`.

### 3. Fixed N+1 Query Issues

**Before:** Multiple `Services::find()` calls inside loops.

**After:** Batch fetching with `whereIn()` and `keyBy()` for O(1) lookups.

### 4. Added Database Transactions

All create/update/delete operations are now wrapped in `DB::transaction()` to ensure data integrity.

### 5. Fixed Hardcoded Account ID

**Before:** Hardcoded `account_id = 1` in some places.

**After:** Properly passes `$accountId` from authenticated user.

### 6. Removed Unused Import

Removed `use Symfony\Component\HttpKernel\Bundle\Bundle;` from Bundles model.

## Caching Strategy

| Cache Key | TTL | Description |
|-----------|-----|-------------|
| `bundle_services_{accountId}` | 1 hour | Active services list |
| `bundle_services_dropdown_{accountId}` | 1 hour | Services for dropdowns |
| `bundle_tax_treatment_types` | 1 hour | Tax treatment types |
| `active_bundles_{accountId}` | 1 hour | Active bundles |

Cache is automatically cleared on:
- Bundle create
- Bundle update
- Bundle delete
- Status change

## Migration Notes

1. **No database changes required** - The optimization is purely architectural.

2. **Route names preserved** - All existing route names (`admin.bundles.*`) are maintained for backward compatibility.

3. **Frontend compatibility** - The API responses maintain the same structure, so no frontend changes are required.

## Testing

To verify the optimization:

```bash
# Clear caches
php artisan cache:clear
php artisan route:clear

# Test routes
php artisan route:list --name=bundles
```

## Performance Improvements

1. **Reduced database queries** - N+1 queries eliminated with batch fetching
2. **Caching** - Frequently accessed lookup data cached for 1 hour
3. **Transactions** - Data integrity ensured with proper transaction handling
4. **Validation** - Early validation with Form Requests before hitting service layer
