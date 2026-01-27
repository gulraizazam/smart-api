# Plans Filter Issue Analysis

## Problem
Filters are not working in the optimized plans datatable.

## Root Cause Analysis

### 1. Frontend Filter Submission
**File:** `public/assets/js/pages/admin_settings/create-plan.js` - Lines 1140-1158

```javascript
function applyFilters(datatable) {
    $('#apply-filters').on('click', function () {
        let filters = {
            delete: '',
            id: $("#search_id").val(),
            patient_id: $("#search_patient_id").val(),
            patient_name: $("#search_patient_id").text(),
            package_id: $("#search_plan_id").val(),
            location_id: $("#search_location_id").val(),
            status: $("#search_status").val(),
            created_at: $("#date_range").val(),
            filter: 'filter',  // ✅ This is sent
        }
        
        datatable.search(filters, 'search');  // Uses KTDatatable
    });
}
```

**Issue:** KTDatatable sends filters in `query` parameter, but also needs proper parameter mapping.

### 2. Backend Filter Reading
**File:** `app/Http/Controllers/Api/PlansController.php` - Lines 145-182

```php
protected function getFilters(Request $request): array
{
    $filters = [];

    // Get query parameters
    $query = $request->get('query', []);
    
    if (is_array($query)) {
        $filters = array_merge($filters, $query);  // ✅ Merges query params
    }

    // Extract specific filter fields
    $filterFields = ['package_id', 'location_id', 'status', 'created_at'];
    
    foreach ($filterFields as $field) {
        if ($request->has($field)) {
            $filters[$field] = $request->get($field);
        }
    }
    
    return $filters;
}
```

**Status:** ✅ This should work if filters are in `query` parameter.

### 3. Service Layer Filter Application
**File:** `app/Services/Plan/PlanService.php` - Lines 486-499

```php
protected function shouldApplyFilter(array $filters): bool
{
    if (!isset($filters['action'])) {
        return false;  // ❌ Returns false if no 'action' key
    }

    $action = $filters['action'];

    if (is_array($action) && isset($action[0]) && $action[0] === 'filter_cancel') {
        return true;
    }

    return $action === 'filter';  // ✅ Returns true if action === 'filter'
}
```

**Issue:** The method checks for `action` key, but frontend sends `filter: 'filter'`.

### 4. Filter Application Logic
**File:** `app/Services/Plan/PlanService.php` - Lines 269-280

```php
// Location filter
if ($this->hasFilter($filters, 'location_id')) {
    $where[] = ['location_id', '=', $filters['location_id']];
    Filters::put($userId, $filename, 'location_id', $filters['location_id']);
} else {
    if ($applyFilter) {  // ❌ If shouldApplyFilter() returns false, this won't clear old filters
        Filters::forget($userId, $filename, 'location_id');
    } else {
        if ($locationId = Filters::get($userId, $filename, 'location_id')) {
            $where[] = ['location_id', '=', $locationId];  // Uses cached filter
        }
    }
}
```

## The Actual Problem

The issue is in the **filter key name mismatch**:

1. Frontend sends: `filter: 'filter'`
2. Backend expects: `action: 'filter'`

This causes `shouldApplyFilter()` to return `false`, which means:
- New filters are not applied
- Old cached filters are used instead
- Filters appear to not work

## Solution

Change the frontend to send `action: 'filter'` instead of `filter: 'filter'`.

OR

Update the backend to check for both `action` and `filter` keys.
