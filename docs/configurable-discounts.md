# Configurable Discounts (Buy X Get Y)

## Overview

Configurable discounts allow creating promotional offers like "Buy 3 sessions of Laser Treatment, Get 1 Facial Free" or "Buy 2 Get 1 at 50% off".

## Features

- **Dedicated Button**: "Add Configurable Discount" button on the Discounts list page
- **Separate Modal**: Clean, dedicated modal for creating/editing configurable discounts
- **Dynamic GET Services**: Add multiple "GET" services with different discount types
- **No Bundle Dependency**: Works directly with services, no longer requires matching bundles

## Database Structure

### Tables Used

1. **discounts** - Main discount record with `type = 'Configurable'`
2. **base_discount_services** - Stores "BUY" services (what customer pays for)
3. **get_discount_services** - Stores "GET" services (what customer receives free/discounted)

### Schema

```sql
-- base_discount_services
- id
- discount_id (FK to discounts)
- service_id (FK to services)
- service_price
- sessions
- bundle_id (nullable, no longer required)

-- get_discount_services
- id
- discount_id (FK to discounts)
- service_id (FK to services)
- service_price
- base_service_id (FK to services)
- sessions
- discount_type ('complimentory' or 'custom')
- discount_amount (percentage if custom)
- bundle_id (nullable, no longer required)
```

## Files Modified/Created

### Views
- `resources/views/admin/discounts/index.blade.php` - Added button and modal includes
- `resources/views/admin/discounts/configurable.blade.php` - **NEW** Create modal
- `resources/views/admin/discounts/edit-configurable.blade.php` - **NEW** Edit modal

### JavaScript
- `public/assets/js/pages/admin_settings/discounts.js` - Added configurable discount functions
- `public/assets/js/pages/crud/forms/validation/admin_settings/discounts.js` - Added validation

### Model
- `app/Models/Discounts.php` - Fixed `createConfigurableDiscount()` and `updateConfigurableDiscount()`
  - Removed bundle dependency
  - Fixed hardcoded account_id
  - Added proper transaction handling
  - Added audit trail logging

## Usage

### Creating a Configurable Discount

1. Go to **Settings > Discounts**
2. Click **"Add Configurable Discount"** button
3. Fill in:
   - **Discount Name**: e.g., "Buy 3 Get 1 Free Laser"
   - **BUY Section**: Select sessions count and base service
   - **GET Section**: Add one or more services with:
     - Sessions count
     - Service selection
     - Discount type (Free or % Off)
   - **Validity Period**: Start and end dates
4. Click **Save Discount**

### Editing a Configurable Discount

1. Click the **Edit** action on any configurable discount
2. The system automatically opens the configurable edit modal
3. Modify fields as needed
4. Click **Update Discount**

## How It Works in Plans

When a configurable discount is applied to a plan:

1. User selects the **base service** and the **configurable discount**
2. System automatically adds ALL services from the discount:
   - Base services at full price
   - GET services at discounted/free price
3. Each service becomes a separate line item in the plan

### Example

**Discount**: "Buy 3 Laser, Get 1 Facial Free"

When applied to a plan:
| Service | Price | Discount | Final |
|---------|-------|----------|-------|
| Laser Treatment | $100 | - | $100 |
| Laser Treatment | $100 | - | $100 |
| Laser Treatment | $100 | - | $100 |
| Facial (FREE) | $80 | Complimentary | $0 |
| **Total** | | | **$300** |

## Key Changes from Previous Implementation

1. **No Bundle Dependency**: Previously required matching Bundle records for each service. Now works directly with services.
2. **Proper Account ID**: Fixed hardcoded `account_id = 1` to use authenticated user's account.
3. **Transaction Safety**: All operations wrapped in database transactions.
4. **Audit Trail**: Proper logging for create/edit operations.
5. **Dedicated UI**: Separate modal instead of hidden fields in regular discount form.

## Future Improvements (Plans Integration)

The Plans module (`PackagesController::savepackages_service`) still references bundles when applying configurable discounts. This will need to be updated to work directly with services for full independence from the Bundles module.
