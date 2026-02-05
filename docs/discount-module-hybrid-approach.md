# Discount Module - Allocation-Based Approach

## Overview

This document describes the allocation-based approach implemented for the discount module, where discount type and amount are set at the allocation level (per location/service) rather than at the discount level.

## Problem Statement

**Previous Flow:**
- Create discount with fixed type (Fixed/Percentage) and amount
- Allocate to location + service combinations
- Same discount value applied everywhere

**Limitation:** A 40% discount meant 40% on ALL allocated services - no flexibility per service/location.

## Solution: Allocation-Based Approach

The new approach moves type, amount, and slug from the discount creation form to the allocation form:
1. **Discount creation** - Only name, applicable on (Treatment/Consultancy/Inventory), date range, roles, and active status
2. **Allocation** - Centre, Service, Type (Fixed/Percentage), Amount, and Slug (Fixed/Custom)

### How It Works

| Step | Fields |
|------|--------|
| Create Discount | Name, Applicable On, From, To, Roles, Active |
| Allocate | Centre, Service, Type, Amount, Slug |

## Database Changes

### Migration 1: `2026_02_05_171800_add_type_and_amount_to_discount_has_locations_table.php`

Added to `discount_has_locations` table:
- `type` (enum: 'Fixed', 'Percentage') - Discount type for this allocation
- `amount` (double) - Discount amount for this allocation

### Migration 2: `2026_02_05_172800_add_slug_to_discount_has_locations_table.php`

Added to `discount_has_locations` table:
- `slug` (string, default: 'default') - Fixed or Custom discount type

```sql
ALTER TABLE discount_has_locations 
ADD COLUMN type ENUM('Fixed', 'Percentage') NULL AFTER service_id,
ADD COLUMN amount DOUBLE(11,2) NULL AFTER type;
```

## Files Modified

### Backend

1. **`app/Models/DiscountHasLocations.php`**
   - Added `type` and `amount` to fillable fields

2. **`app/Http/Controllers/Admin/DiscountsController.php`**
   - `saveDservices()` - Now accepts and saves `allocation_type` and `allocation_amount`
   - Returns type/amount in response for display

3. **`app/Helpers/Widgets/DiscountWidget.php`**
   - Added `loadPlanDiscountAllocationsByLocationService()` - Returns allocation records with type/amount
   - Original `loadPlanDsicountByLocationService()` kept for backward compatibility

4. **`app/Http/Controllers/Admin/PackagesController.php`**
   - `getserviceinfo_for_plan()` - Uses allocation-level type/amount if set
   - `getdiscountinfo_for_plan()` - Uses allocation-level type/amount if set

### Frontend

1. **`resources/views/admin/discounts/allocate.blade.php`**
   - Added Discount Type dropdown (Fixed/Percentage/Use Default)
   - Added Amount input field
   - Added info box showing default values
   - Updated table to show Type and Amount columns

2. **`public/assets/js/pages/admin_settings/discounts.js`**
   - `setAllocateData()` - Displays default values and allocation overrides
   - `serviceLocationWithTypeAmount()` - New function for table rows with type/amount

3. **`public/assets/js/pages/crud/forms/validation/admin_settings/discounts.js`**
   - `submitData()` - Sends `allocation_type` and `allocation_amount` to backend

## Usage

### Creating a Discount (unchanged)
1. Enter name, applicable on (Treatment/Consultancy/Inventory)
2. Select discount type (Fixed/Percentage) - **this becomes the DEFAULT**
3. Enter amount - **this becomes the DEFAULT**
4. Set date range and roles
5. Save

### Allocating with Override (new)
1. Click "Allocate" on a discount
2. Select Centre and Service
3. **Optional:** Select different Discount Type (or leave "Use Default")
4. **Optional:** Enter different Amount (or leave empty for default)
5. Click "Add Allocation"

### Example Use Case

**Discount:** "Summer Sale" (Default: 40% Percentage)

| Location | Service | Override Type | Override Amount | Effective |
|----------|---------|---------------|-----------------|-----------|
| All Centres | Laser Treatment | - | - | 40% |
| Lahore | Facial | - | 20 | 20% |
| Karachi | Botox | Fixed | 5000 | PKR 5000 off |

## API Response Changes

### `getserviceinfo_for_plan` Response
```json
{
  "discounts": [...],
  "dis_price_info": {
    "id": 1,
    "discount_type": "Percentage",
    "discount_price": 20,
    "net_amount": 8000,
    "allocation_override": true
  }
}
```

### `getdiscountinfo_for_plan` Response
```json
{
  "discount_type": "Percentage",
  "discount_price": 20,
  "net_amount": 8000,
  "allocation_override": true
}
```

## Backward Compatibility

- Existing discounts continue to work with their default type/amount
- Existing allocations (without type/amount) use discount defaults
- No data migration required for existing records

## Priority Order (Most Specific Wins)

When multiple allocations match, the most specific one is used:
1. **Specific Centre + Specific Service** (highest priority)
2. **Specific Centre + All Services**
3. **Region + Specific Service**
4. **Region + All Services**
5. **All Centres + Specific Service**
6. **All Centres + All Services** (lowest priority)

## Testing Checklist

- [ ] Create discount with default type/amount
- [ ] Allocate without override - verify default values used
- [ ] Allocate with type override only - verify type changed, amount default
- [ ] Allocate with amount override only - verify amount changed, type default
- [ ] Allocate with both overrides - verify both changed
- [ ] Create plan with service - verify correct discount values applied
- [ ] Verify existing discounts still work correctly
