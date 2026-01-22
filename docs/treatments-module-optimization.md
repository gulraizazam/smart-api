# Treatments Module Optimization - Step 1: Datatable

## Overview

This document describes the optimization of the Treatments module datatable, focusing on performance improvements, code organization, and API-based implementation.

## Changes Made

### 1. New Service Class

**File:** `app/Services/Treatment/TreatmentService.php`

The `TreatmentService` class handles all business logic for the treatments datatable:

- **Optimized Query Building:** Single query builder pattern instead of duplicate queries for count and results
- **Eager Loading:** Prevents N+1 queries by loading all relationships in a single query
- **Caching:** Implements 1-hour TTL caching for:
  - Lookup data (regions, users, appointment statuses)
  - Filter dropdown values (cities, doctors, locations, services)
  - Treatment type ID
  - Paid invoice status
- **Filter Processing:** Centralized filter handling with proper storage

### 2. Custom Exception Class

**File:** `app/Exceptions/TreatmentException.php`

Provides proper exception handling with:
- Custom status codes
- Error data support
- Factory methods for common exceptions (notFound, unauthorized, validationFailed, operationFailed)

### 3. API Controller

**File:** `app/Http/Controllers/Api/TreatmentsController.php`

Lightweight controller that:
- Delegates all business logic to `TreatmentService`
- Handles authorization checks
- Provides proper error responses
- Includes cache clearing endpoint

### 4. API Routes

**File:** `routes/api.php`

New routes added under `api/treatments` prefix:
- `POST /api/treatments/datatable` - Get datatable data
- `POST /api/treatments/clear-cache` - Clear treatment caches

Route names:
- `admin.treatments.datatable`
- `admin.treatments.clear_cache`

### 5. JavaScript Update

**File:** `public/assets/js/pages/appointment/treatmentDatatable.js`

Updated to use the new optimized API endpoint:
```javascript
var table_url = route('admin.treatments.datatable');
```

## Performance Improvements

### Before (Issues Identified)

1. **N+1 Queries:** Each appointment accessed `doctor`, `city`, `location`, `service`, `appointment_type`, and `appointment_status` individually in the loop
2. **Duplicate Query Building:** Count query and result query were built separately with duplicated logic
3. **No Caching:** Lookup data (regions, users, statuses) was fetched on every request
4. **Mixed Concerns:** Business logic was embedded in the controller

### After (Optimizations Applied)

1. **Eager Loading:** All relationships loaded in single query using `with()`
2. **Single Query Builder:** Reusable query conditions applied to both count and result queries
3. **Caching Strategy:**
   - Lookup data cached for 1 hour
   - Filter values cached per account/user centres
   - Static data (treatment type ID, invoice status) cached
4. **Service Layer:** Clean separation of concerns with dedicated service class

## Relationships Eager Loaded

```php
->with([
    'patient:id,name,phone',
    'doctor:id,name',
    'city:id,name',
    'location:id,name',
    'service:id,name',
    'appointment_type:id,name',
    'appointment_status:id,name,parent_id',
    'invoice' => function ($q) use ($invoiceStatus) {
        $q->where('invoice_status_id', $invoiceStatus->id ?? 0);
    }
])
```

## Cache Keys

| Cache Key | TTL | Description |
|-----------|-----|-------------|
| `treatment_lookup_data_{account_id}` | 1 hour | Regions, users, appointment statuses |
| `treatment_filter_values_{account_id}_{centres_hash}` | 1 hour | Filter dropdown values |
| `treatment_type_id` | 1 hour | Treatment appointment type ID |
| `paid_invoice_status` | 1 hour | Paid invoice status record |

## API Response Structure

```json
{
    "data": [...],
    "meta": {
        "field": "appointments.created_at",
        "page": 1,
        "pages": 7,
        "perpage": 30,
        "total": 150,
        "sort": "desc"
    },
    "active_filters": {...},
    "filter_values": {
        "cities": {...},
        "regions": {...},
        "users": {...},
        "doctors": {...},
        "locations": {...},
        "services": {...},
        "appointment_statuses": {...},
        "appointment_types": {...},
        "consultancy_types": {...}
    },
    "permissions": {
        "edit": true,
        "delete": true,
        ...
    }
}
```

## Backward Compatibility

The old route `admin.treatment.datatable` still exists in `routes/api.php` and points to the original `AppointmentsController@treatmentDatatable` method. This ensures backward compatibility during the transition period.

## Next Steps (Future Optimization Phases)

1. **Step 2:** Optimize create/edit treatment functionality
2. **Step 3:** Optimize status update operations
3. **Step 4:** Optimize invoice creation flow
4. **Step 5:** Add Form Request validation classes
5. **Step 6:** Remove legacy web routes after full API migration
6. **Step 7:** Add comprehensive test coverage

## Testing

After deployment, verify:
1. Datatable loads correctly with all filters
2. Sorting works as expected
3. Pagination functions properly
4. All action buttons work (edit, delete, invoice, etc.)
5. Performance improvement is noticeable (check network tab)

## Clearing Cache

To clear treatment caches manually:
```bash
php artisan cache:forget treatment_lookup_data_{account_id}
php artisan cache:forget treatment_filter_values_{account_id}_{hash}
php artisan cache:forget treatment_type_id
php artisan cache:forget paid_invoice_status
```

Or use the API endpoint:
```
POST /api/treatments/clear-cache
```
