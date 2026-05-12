# Inventory Module — Complete Analysis & Bug-Fix Plan

**Date:** 2026-03-24  
**Scope:** Products, Inventory, Stock, Orders, Transfers, Refunds, Reports

---

## 1. Module Overview

### Files Analyzed

| Layer | Files |
|-------|-------|
| **Models** | `Product.php`, `Inventory.php`, `Stock.php`, `ProductDetail.php`, `TransferProduct.php`, `Order.php`, `OrderDetail.php`, `OrderRefund.php`, `OrderRefundDetail.php`, `InventoryLog.php`, `InventoryLogTable.php`, `InventoryLogAction.php` |
| **Controllers** | `ProductsController.php`, `TransferProductsController.php`, `OrdersController.php`, `InventoryReportController.php`, `InventoryReportsController.php` |
| **Helpers** | `GeneralFunctions.php` (`stockCheck`, `inventoryCheck`, `stockC`) |
| **JS** | `products.js`, `transfer_products.js`, `inventory_report.js` + validation files |
| **Views** | `products/` (12 blade files), `reports/` (inventory_report, inventoryReport, inventory_sales) |
| **Routes** | `api.php` (~30 product/order/transfer routes), `web.php` (~15 view routes) |

### Core Data Flow

```
Product (master) 
  → Inventory (per-location quantity tracking)
  → Stock (historical in/out log entries)
  → ProductDetail (purchase batches)
  → OrderDetail (sales deduction)
  → TransferProduct (inter-location movement)
```

---

## 2. Critical Bugs (Data Integrity)

### BUG-01: Dual Quantity Tracking Creates Permanent Drift  
**Severity:** CRITICAL  
**Location:** Entire module — `inventories` table vs `stocks` table

**Problem:** The system maintains TWO independent quantity sources:
1. `inventories.quantity` — mutable counter, incremented/decremented directly
2. `stocks` table — append-only log with `stock_type` = 'in'/'out', quantity derived by SUM(in) - SUM(out)

These two sources are updated by **different code paths** and **inevitably drift apart**:
- `addStock()` updates BOTH `inventories.quantity` AND creates a `stocks` row
- `OrderDetail::createRecord()` updates `inventories.quantity` but does NOT create a `stocks` row with `stock_type='out'`
- `saveAllocate()` creates BOTH an inventory row and a stock row
- `transferProduct()` updates `inventories.quantity` but the `TransferProduct::createRecord()` does NOT create stock IN/OUT rows for the destination/source
- `OrderRefundDetail::refund()` updates `inventories.quantity` but does NOT create a stock row

**Impact:** Stock reports (`stockCheck`, `stockC`, `sumProductQuantity`) return numbers that don't match inventory reports. The `getProductsAjax()` method uses yet another formula (stocks IN - order_details quantity) which is a THIRD source of truth.

**Fix:** Choose ONE source of truth. Recommended: make `inventories.quantity` the single mutable counter. Remove all stock-based quantity calculations. The `stocks` table becomes a pure audit log (append-only, never used for quantity calculations).

---

### BUG-02: `Inventory::getTotalRecords()` — Swapped Logic  
**Severity:** HIGH  
**Location:** `app/Models/Inventory.php:14-21`

```php
if ($product_id == 0) {
    return self::join(...)->where('product_id', $product_id)->count(); // filters to product_id=0
} else {
    return self::join(...)->count(); // returns ALL records
}
```

**Problem:** Logic is inverted. When `$product_id == 0` (meaning "no filter"), it filters to `product_id = 0` (which matches nothing). When a specific product_id is given, it returns ALL records unfiltered.

**Fix:** Swap the conditions or rewrite:
```php
if ($product_id > 0) {
    return self::where('product_id', $product_id)->count();
} else {
    return self::count();
}
```

---

### BUG-03: `Stock::getTotalRecords()` — Same Swapped Logic  
**Severity:** HIGH  
**Location:** `app/Models/Stock.php:23-30`

Identical inverted logic as BUG-02.

---

### BUG-04: `Product::isChildExists()` — Broken `orWhere` Scope  
**Severity:** HIGH  
**Location:** `app/Models/Product.php:286-293`

```php
TransferProduct::where(['product_id' => $id, 'account_id' => $account_id])
    ->orwhere(['child_product_id' => $id])
    ->count()
```

**Problem:** The `orWhere` ignores the `account_id` constraint. This means it checks `child_product_id = $id` across ALL accounts, potentially blocking deletion for the wrong account's data.

**Fix:** Use a grouped where:
```php
TransferProduct::where('account_id', $account_id)
    ->where(function ($q) use ($id) {
        $q->where('product_id', $id)->orWhere('child_product_id', $id);
    })->count()
```

---

### BUG-05: `addStock()` — Hardcoded `account_id = 1` and Double-Write to `items`  
**Severity:** HIGH  
**Location:** `app/Http/Controllers/Admin/ProductsController.php:411-455`

```php
$purchase->items = $request->quantity;        // line 434 — sets items
$purchase->total_price = $request->quantity;  // line 436 — total_price = quantity?!
$purchase->items = $request->total_purchase_price; // line 437 — OVERWRITES items
```

AND in `saveAllocate()`:
```php
$stock->account_id = 1; // line 824 — hardcoded!
```

**Problems:**
1. `$purchase->items` is set twice — first to `quantity`, then immediately overwritten to `total_purchase_price`
2. `$purchase->total_price` is set to `$request->quantity` instead of the actual total price
3. `account_id = 1` is hardcoded in `saveAllocate()` instead of using `Auth::User()->account_id`
4. `$purchase->account_id` is set but `$purchase_detail->account_id` is set on `$purchase` object (line 441: `$purchase->account_id` instead of `$purchase_detail->account_id`)

**Fix:** Correct all field assignments and use dynamic account_id.

---

### BUG-06: `saveAllocate()` — Missing Stock `stock_type`  
**Severity:** HIGH  
**Location:** `app/Http/Controllers/Admin/ProductsController.php:822-828`

```php
$stock = new Stock();
$stock->account_id = 1;
$stock->product_id = $request->product_id;
$stock->quantity = $request->quantity;
$stock->location_id = $request->location_id;
$stock->save();
```

**Problem:** No `stock_type` is set. The `stocks.stock_type` column will be NULL, breaking all stock calculations that filter by `stock_type = 'in'` or `stock_type = 'out'`.

**Fix:** Add `$stock->stock_type = 'in';`

---

### BUG-07: Transfer Product — No Inventory Rows Created in Stocks Table  
**Severity:** HIGH  
**Location:** `TransferProductsController::store()` (lines 190-238) and `ProductsController::transferProduct()` (lines 606-663)

**Problem:** Both transfer methods update `inventories.quantity` for source and destination, but do NOT create `stocks` rows for the stock OUT at source or stock IN at destination. This means:
- Stock log is incomplete
- `stockCheck()` and `stockC()` return wrong numbers for transferred products
- Reports based on stocks table miss transfers entirely

**Fix:** After inventory quantity adjustments, create corresponding stock records:
```php
Stock::create([...product_id, location_id (source), quantity, stock_type => 'out'...]);
Stock::create([...product_id, location_id (destination), quantity, stock_type => 'in'...]);
```

---

### BUG-08: Transfer — No Stock Validation Against Actual Available Quantity  
**Severity:** HIGH  
**Location:** `TransferProduct::createRecord()` line 199

```php
$product_quantity = Inventory::where([$from_key => $from_value, 'product_id' => $parent_product_id])->sum('quantity');
```

**Problem:** Checks `inventories.quantity` but `TransferProductsController::store()` line 158 calls `GeneralFunctions::inventoryCheck()` which does `->first()->quantity`. If there are multiple inventory rows for the same product+location, `sum()` ≠ `first()->quantity`. Inconsistent validation.

Additionally, `TransferProductsController::update()` line 302 calls `GeneralFunctions::stockC()` which uses the stocks table — a completely different data source than what `store()` uses.

**Fix:** Use a single consistent method for available quantity calculation everywhere.

---

### BUG-09: Negative Inventory Quantities Allowed  
**Severity:** HIGH  
**Location:** Multiple places

Several operations subtract from `inventories.quantity` without checking if the result goes negative:
- `OrderDetail::createRecord()` line 56: `$updated_quantity = $inventory->quantity - $quantity`
- `ProductsController::transferProduct()` line 635: `$updated_quantity = $minus_inventory->quantity - $request->quantity`
- `TransferProductsController::store()` line 214: same pattern

**Problem:** No guard against `$updated_quantity < 0`. Negative inventory causes cascading issues in all reports and order availability checks.

**Fix:** Add validation: `if ($updated_quantity < 0) throw/return error`.

---

### BUG-10: `TransferProduct::DeleteRecord()` — Deletes After Null Check  
**Severity:** MEDIUM  
**Location:** `app/Models/TransferProduct.php:297-333`

```php
$transfer_product = self::getData($id);
$child_product_check = TransferProduct::where([...])->count(); // line 302 — uses $transfer_product BEFORE null check

if (!$transfer_product) { // line 304 — null check is TOO LATE
    return collect(['status' => false, ...]);
}
```

**Problem:** Line 302 accesses `$transfer_product->child_product_id` before the null check on line 304. If the record doesn't exist, this throws an exception.

Also, line 320-322: after `$transfer_product->delete()`, it calls `$transfer_product->childProduct()->delete()` on a deleted model, which may silently fail.

**Fix:** Move null check before any usage of the variable. Check soft-delete behavior.

---

### BUG-11: `Product::updateRecord()` — Mass Assignment Vulnerability  
**Severity:** HIGH  
**Location:** `app/Models/Product.php:225-251`

```php
$data = $request->all(); // or array
$record->update($data);
```

**Problem:** `$request->all()` includes ALL request parameters (including `type`, `message`, `_method`, etc.) which get passed directly to `update()`. While `$fillable` provides some protection, the `type` and `message` fields from audit logging are injected into the update data. If the products table has columns matching any request parameter name, they'll be silently overwritten.

**Fix:** Use explicit field lists or Form Request validation to whitelist only allowed fields.

---

### BUG-12: `TransferProduct::updateRecord()` — Updates With Unfiltered Data  
**Severity:** HIGH  
**Location:** `app/Models/TransferProduct.php:238-289`

```php
$data = $request->all();
unset($data['_method']);
unset($data['type']);
unset($data['message']);
// ...
self::where(['id' => $id])->update($data);
```

**Problem:** Only 3 keys are unset, but request may contain many other non-column keys (e.g., `product_type_option_from`, `product_type_option_to`) that will cause SQL errors or silently be ignored. No validation of quantity, no stock check before update.

**Fix:** Use explicit field list matching `$fillable`.

---

### BUG-13: `Product::lead_sources_filters()` — Broken Array Syntax  
**Severity:** MEDIUM  
**Location:** `app/Models/Product.php:149-163`

```php
$where[][] = ['product_type' => $filters['product_type']];
$where[][] = ['brand_id' => $filters['brand_id']];
```

**Problem:** `$where[][]` creates a nested array like `[0 => [0 => ['product_type' => 'x']]]`. When passed to `->where($where)`, Laravel expects `[['column', 'operator', 'value']]` format. This produces incorrect SQL or errors silently.

**Fix:** Use `$where[] = ['product_type', '=', $filters['product_type']]` format.

---

## 3. Race Conditions & Concurrency Issues

### RACE-01: Transfer Product — No Row Locking in `TransferProductsController::store()`  
**Severity:** HIGH  
**Location:** `TransferProductsController::store()` lines 196-213

```php
$minus_inventory = Inventory::where('product_id',...)->where('location_id',...)->first(); // NO lock
$update_inventory = Inventory::where('product_id',...)->where('location_id',...)->first(); // NO lock
```

**Problem:** Two concurrent transfers from the same location can both read the same quantity, both pass validation, and both deduct — resulting in negative inventory.

Note: `ProductsController::transferProduct()` correctly uses `lockForUpdate()` but `TransferProductsController::store()` does NOT.

**Fix:** Use `lockForUpdate()` consistently, or better yet, centralize transfer logic into a single service method.

---

### RACE-02: `addStock()` — No Row Lock on Inventory Update  
**Severity:** MEDIUM  
**Location:** `ProductsController::addStock()` lines 429-432

```php
$inventory = Inventory::where('product_id',$id)->where('id',$request->inventory_id)->first(); // no lock
$latest_quantity = $inventory->quantity + $request->quantity;
$inventory->update(['quantity' =>$latest_quantity]);
```

**Problem:** Classic read-then-write race condition. Two concurrent stock additions can lose one update.

**Fix:** Use `lockForUpdate()` or atomic increment: `$inventory->increment('quantity', $request->quantity)`.

---

## 4. Architectural Issues

### ARCH-01: Three Redundant Stock Functions in GeneralFunctions  
**Severity:** MEDIUM  
**Location:** `app/Helpers/GeneralFunctions.php`

Three functions that do almost the same thing:
- `stockCheck($id)` — returns array with stock_quantity and stock_available
- `stockC($id)` — returns just the integer quantity
- `inventoryCheck($request)` — uses inventories table instead of stocks table

Plus `Stock::sumProductQuantity()` in the Stock model.

**Problem:** Different callers use different functions that may return different results (stocks table vs inventories table).

**Fix:** Create ONE centralized method: `InventoryService::getAvailableQuantity($productId, $locationId)`.

---

### ARCH-02: Business Logic Embedded in Models (Fat Models)  
**Severity:** MEDIUM  
**Location:** All models

`Product`, `TransferProduct`, `Order`, `OrderDetail`, `ProductDetail` all contain `createRecord()`, `updateRecord()`, `DeleteRecord()`, `getRecords()`, `getTotalRecords()`, filter methods, etc. This is the same anti-pattern already fixed in the Leads, Services, and Bundles modules.

**Fix:** Extract to service classes following the established pattern:
- `InventoryService` — stock operations (add, transfer, allocate)
- `ProductService` — CRUD
- `OrderService` — order creation, refund
- Form Request classes for validation

---

### ARCH-03: Duplicate Transfer Logic  
**Severity:** MEDIUM  
**Location:** `ProductsController::transferProduct()` AND `TransferProductsController::store()`

Both methods implement the same transfer-product logic (validate stock, create transfer record, create product detail, update inventories). The code is nearly identical but with slight differences:
- `ProductsController` uses `lockForUpdate()`, `TransferProductsController` does not
- `ProductsController` wraps in `DB::transaction()`, `TransferProductsController` does not

**Fix:** Consolidate into a single `InventoryService::transferProduct()` method called from both controllers.

---

### ARCH-04: Duplicate Report Controllers  
**Severity:** LOW  
**Location:** `Admin\InventoryReportController.php` AND `InventoryReportsController.php`

Two separate controllers handling inventory reports:
- `Admin\InventoryReportController` — older, uses `Product.productDetail` and `Product.transferProduct` (commented out relationships)
- `InventoryReportsController` — newer, uses stocks table + order_details for calculations

The older controller references relationships that are commented out in the Product model (`productDetail`, `transferProduct`, `getAvailableStockAttribute`).

**Fix:** Remove the old controller, keep only `InventoryReportsController`.

---

### ARCH-05: Empty Models — Dead Code  
**Severity:** LOW  
**Location:** `InventoryLog.php`, `InventoryLogTable.php`, `InventoryLogAction.php`

All three models are completely empty (just `HasFactory`). No relationships, no methods, no $fillable, no $table. They appear unused.

**Fix:** Either implement proper inventory audit logging using these models, or remove them.

---

### ARCH-06: No `$guarded` / `$fillable` on Inventory Model  
**Severity:** MEDIUM  
**Location:** `app/Models/Inventory.php:11`

```php
protected $guarded = [];
```

**Problem:** `$guarded = []` means ALL fields are mass-assignable. Any request parameter can be injected.

**Fix:** Define explicit `$fillable` array.

---

### ARCH-07: `Stock` Model Missing `location_id` in `$fillable`  
**Severity:** MEDIUM  
**Location:** `app/Models/Stock.php:13`

```php
protected $fillable = ['account_id', 'product_id', 'order_id', 'quantity', 'stock_type', 'transfer_id', 'product_detail_id'];
```

**Problem:** `location_id` is NOT in `$fillable`, but code creates stocks with `location_id` (e.g., `saveAllocate()` line 827: `$stock->location_id = $request->location_id`). Direct assignment works but `Stock::create([...'location_id' => ...])` would silently drop it.

**Fix:** Add `location_id` to `$fillable`.

---

## 5. Validation Issues

### VAL-01: `ProductsController::store()` — Minimal Validation  
**Severity:** MEDIUM  
**Location:** `ProductsController.php:252-260`

```php
return Validator::make($request->all(), [
    'name' => 'required',
    'brand_id' => 'required',
    'sku' => 'required|unique:products,sku',
]);
```

**Missing validations:**
- `sale_price` — not required, not validated as numeric
- `brand_id` — not validated as `exists:brands,id`
- `name` — no max length
- No `product_type` validation
- SKU uniqueness check doesn't exclude the current record on edit (edit uses same `verifyFields` but there's no edit-specific validator)

---

### VAL-02: `TransferProductsController::verifyFields()` — Requires `to_location_id` Even for Warehouse Transfers  
**Severity:** MEDIUM  
**Location:** `TransferProductsController.php:252-260`

```php
'from_location_id' => 'required',
'to_location_id' => 'required'
```

**Problem:** Warehouse-to-warehouse or warehouse-to-branch transfers will always fail validation because `from_location_id` and `to_location_id` are required, but warehouse transfers use `from_warehouse_id` / `to_warehouse_id` instead.

**Fix:** Use conditional validation based on `product_type_option_from` and `product_type_option_to`.

---

### VAL-03: `saveAllocate()` — No Input Validation  
**Severity:** MEDIUM  
**Location:** `ProductsController.php:810-835`

No validation at all on `product_id`, `location_id`, `quantity`, `sale_price`. A request with negative quantity or non-existent product_id will silently create bad data.

**Fix:** Add proper validation.

---

### VAL-04: `addStock()` — Allows 0 Quantity  
**Severity:** LOW  
**Location:** `ProductsController.php:425-427`

```php
if ($request->quantity && $request->quantity < 0) {
```

**Problem:** Only checks `< 0`, allows `quantity = 0` which creates a zero-quantity stock entry and purchase record.

**Fix:** Change to `<= 0` (or use `min:1` validator).

---

### VAL-05: No SKU Uniqueness Check on Edit  
**Severity:** MEDIUM  
**Location:** `ProductsController::update()` — no validation call at all

The `update()` method doesn't call `verifyFields()` or any validator. SKU, name, brand_id are not validated on edit.

**Fix:** Add edit-specific validation that excludes current record ID from unique check.

---

## 6. Authorization Issues

### AUTH-01: Transfer Products — All Gate Checks Commented Out  
**Severity:** HIGH  
**Location:** `TransferProductsController.php`

Every Gate check in the TransferProductsController is commented out:
- `index()` — line 50-52
- `create()` — line 126-128
- `store()` — line 150-152
- `edit()` — line 270-272
- `update()` — line 294-296
- `destroy()` — line 343-345

**Problem:** ANY authenticated user can create, edit, and delete transfer products regardless of their permissions.

**Fix:** Uncomment all Gate checks.

---

### AUTH-02: Inconsistent Error Responses  
**Severity:** LOW  
**Location:** Multiple controllers

Some permission failures return `abort(401)`, others return `ApiHelper::apiResponse($this->unauthorized, ...)`, and transfer product store returns a `collect()` instead of an API response:
```php
return collect(['status' => false, 'message' => 'This product stock not available.']); // line 161
```

**Fix:** Standardize all error responses to use `ApiHelper::apiResponse()`.

---

## 7. Report Bugs

### RPT-01: `InventoryReportController::stockReportResult()` — References Dead Relationships  
**Severity:** HIGH  
**Location:** `InventoryReportController.php:98-106`

```php
$products = Product::with('order')
    ->withSum('productDetail', 'quantity')
    ->withSum('transferProduct', 'quantity')
```

**Problem:** 
- `productDetail` relationship is commented out in Product model (line 38-41)
- `transferProduct` relationship is commented out (line 56-59)
- `getAvailableStockAttribute()` is commented out (line 61-67)

This entire report is broken and will throw errors.

**Fix:** Either restore the relationships or rewrite the report to use the inventories/stocks tables.

---

### RPT-02: `InventoryReportsController::loadInventoryReport()` — Stock Report Ignores Transfers  
**Severity:** MEDIUM  
**Location:** `InventoryReportsController.php:67-119`

Stock report calculates: Opening = stocks(IN before start) - sales(before start). This ignores:
- Transfer OUT from the location (should reduce stock)
- Transfer IN to the location (should increase stock)
- Refunds (should increase stock)

**Fix:** Include transfer and refund quantities in the calculation.

---

### RPT-03: `InventoryReportsController::loadInventoryReport()` — Doctor Sales Report Location Filter Bug  
**Severity:** MEDIUM  
**Location:** `InventoryReportsController.php:179`

```php
->where('location_id', $locationId)
```

**Problem:** `$locationId` is an array (set on line 124), but `where('location_id', $locationId)` does an equality check, not `whereIn`. This will always return 0 results when no specific centre is selected.

**Fix:** Change to `->whereIn('location_id', $locationId)`.

---

### RPT-04: `Order::getRecord()` — Accesses Collection as Object  
**Severity:** HIGH  
**Location:** `app/Models/Order.php:392-401`

```php
$record = self::with('orderDetail')->where(['id' => $id])->first();
$record->quantity = Stock::sumProductQuantity($record->orderDetail->product_id);
```

**Problem:** `$record->orderDetail` is a HasMany collection, not a single object. Accessing `->product_id` on a collection returns null, causing `sumProductQuantity(null)`.

**Fix:** Either iterate over order details or change logic.

---

## 8. Data Consistency Issues

### DATA-01: `getProductsAjax()` Uses a THIRD Quantity Formula  
**Severity:** HIGH  
**Location:** `Product.php:297-362`

For the `from_id` branch (used by orders), available quantity = `stocks(IN at location) - order_details(at location)`. This:
- Ignores `stocks(OUT)` rows
- Ignores transfers
- Ignores refunds
- Is different from both `inventories.quantity` and `stockCheck()`

For the `product_id` and `request_from` branches, it uses `inventories.quantity` directly.

**Problem:** Same function returns quantities calculated differently depending on which branch executes.

**Fix:** Use single source (inventories.quantity) for all branches.

---

### DATA-02: `getTransferProductsAjax()` — Inconsistent Available Quantity  
**Severity:** MEDIUM  
**Location:** `Product.php:382-421`

For location transfers: `sum(inventories.quantity) - sum(order_details.quantity)` — ignores previous transfers.
For warehouse transfers: returns raw `inventories.quantity` from first matching row.

**Fix:** Centralize quantity calculation.

---

### DATA-03: Order Creation — `location_id` Unset Then Used  
**Severity:** HIGH  
**Location:** `Order.php:196-210`

```php
$location_id = $data['location_id'];
unset($data['location_id']);        // line 198 — removes location_id
$data[$data['location_type']] = $location_id;
// ...
$record->location_id = $data['location_id']; // line 210 — tries to use it again!
```

**Problem:** `$data['location_id']` was unset on line 198 but accessed on line 210. This will set `location_id` to null unless `$data['location_type']` happens to equal `'location_id'`.

**Fix:** Use `$location_id` variable directly: `$record->location_id = $location_id;`

---

### DATA-04: Refund Does Not Restore Stock Properly  
**Severity:** HIGH  
**Location:** `OrderRefundDetail::refund()` line 33

```php
$order_detail->update(['quantity'=>$update_quantity]); // COMMENTED OUT on line 33
```

The order detail quantity update is commented out. Only inventory is restored. This means the original order still shows the full quantity even after partial refund.

**Fix:** Uncomment or implement proper order detail quantity adjustment.

---

## 9. Performance Issues

### PERF-01: N+1 Queries in `getProductsAjax()`  
**Severity:** MEDIUM  
**Location:** `Product.php:321-360`

For each product, 3 separate queries are executed inside the foreach loop:
1. `stocks` SUM query
2. `order_details` JOIN + SUM query  
3. `inventories` query for price

For 100 products, that's 300 queries.

**Fix:** Use aggregate queries with GROUP BY outside the loop, or use Eloquent's `withSum()`.

---

### PERF-02: N+1 Queries in Transfer Product Datatable  
**Severity:** MEDIUM  
**Location:** `TransferProductsController::datatable()` lines 83-94

Inside `map()`, for each transfer product:
- `Product::where(['id' => ...])->select('name')->first()` — 1 query
- `ProductDetail::where(['id' => ...])->first()` — 1 query
- `TransferProduct::parentLocation()` — 1 query for transfer + dictionary lookups
- `TransferProduct::childLocation()` — 1 query for transfer + dictionary lookups

**Fix:** Use eager loading: `TransferProduct::with('parentProduct', 'childProduct')`.

---

### PERF-03: `Product::getRecords()` Has Contradicting OrderBy  
**Severity:** LOW  
**Location:** `Product.php:113-120`

```php
->orderBy('products.name', 'asc')
->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id', 'DESC')->get();
```

Two conflicting `orderBy` clauses. The second one (`id DESC`) will take precedence in MariaDB, making the first one (`name ASC`) useless.

**Fix:** Remove the duplicate/contradicting orderBy.

---

## 10. Missing Features / Incomplete Implementation

### MISS-01: No DB Transaction in Critical Operations  
**Location:** Multiple

- `ProductsController::addStock()` — no transaction (creates ProductDetail + Stock + updates Inventory + creates Purchase + PurchaseDetail)
- `TransferProductsController::store()` — no transaction
- `OrderDetail::createRecord()` — has a transaction (good)
- `saveAllocate()` — has a transaction (good)
- `ProductsController::transferProduct()` — has a transaction (good)

**Fix:** Wrap all multi-table operations in `DB::transaction()`.

---

### MISS-02: No Audit Trail for Inventory Changes  
**Severity:** MEDIUM

The `InventoryLog`, `InventoryLogTable`, `InventoryLogAction` models exist but are completely empty and unused. There is no tracking of:
- Who adjusted inventory
- When quantity changed
- What triggered the change (sale, transfer, manual adjustment, refund)

**Fix:** Implement inventory change logging using these models.

---

### MISS-03: Bulk Delete in Datatable Has No Transaction  
**Severity:** MEDIUM  
**Location:** `ProductsController::datatable()` lines 91-112

Bulk delete happens inside the datatable response method. If one product fails to delete, previously deleted ones are not rolled back.

**Fix:** Wrap in transaction, move to dedicated endpoint.

---

### MISS-04: `Product::DeleteRecord()` Doesn't Delete Inventories  
**Severity:** MEDIUM  
**Location:** `Product.php:259-276`

Deletes `stocks` and `product_details` but not `inventories` rows for the product.

**Fix:** Add `Inventory::where('product_id', $id)->delete();`

---

### MISS-05: No Edit/Update for Inventory Allocations  
**Severity:** LOW  
**Location:** `ProductsController::editInventory()`

The `editInventory()` method exists and returns data for editing, but there's no corresponding `updateInventory()` method or route to save changes.

**Fix:** Implement update endpoint.

---

## 11. Summary — Fix Priority

| Priority | Bug ID | Description |
|----------|--------|-------------|
| **P0 — Critical** | BUG-01 | Dual quantity tracking (inventories vs stocks) — choose one source of truth |
| **P0 — Critical** | BUG-05 | `addStock()` corrupts purchase records |
| **P0 — Critical** | BUG-09 | Negative inventory allowed |
| **P0 — Critical** | DATA-03 | Order creation uses unset `location_id` |
| **P1 — High** | BUG-02, 03 | Swapped logic in `getTotalRecords()` |
| **P1 — High** | BUG-04 | `isChildExists()` broken orWhere scope |
| **P1 — High** | BUG-06 | `saveAllocate()` missing stock_type |
| **P1 — High** | BUG-07 | Transfer doesn't create stock rows |
| **P1 — High** | BUG-08 | Inconsistent stock validation |
| **P1 — High** | AUTH-01 | All transfer product permissions disabled |
| **P1 — High** | RPT-01 | Old report controller references dead relationships |
| **P1 — High** | DATA-01 | Three different quantity formulas |
| **P1 — High** | DATA-04 | Refund doesn't update order detail quantity |
| **P1 — High** | RACE-01 | No row locking in transfer store |
| **P2 — Medium** | BUG-10-13, VAL-01-05, ARCH-01-07, RPT-02-04, RACE-02, PERF-01-02, MISS-01-05 | Various (see sections above) |

---

## 12. Recommended Architecture (Post-Fix)

```
app/
├── Services/
│   └── Inventory/
│       ├── InventoryService.php        # Single source of truth for quantity
│       ├── ProductService.php          # Product CRUD
│       ├── OrderService.php            # Order creation, refund
│       └── TransferService.php         # Transfer operations
├── Exceptions/
│   └── InventoryException.php          # Custom exceptions
├── Http/
│   ├── Requests/
│   │   └── Inventory/
│   │       ├── StoreProductRequest.php
│   │       ├── UpdateProductRequest.php
│   │       ├── AllocateInventoryRequest.php
│   │       ├── AddStockRequest.php
│   │       ├── TransferProductRequest.php
│   │       ├── StoreOrderRequest.php
│   │       └── RefundOrderRequest.php
│   └── Controllers/
│       └── Api/
│           ├── ProductsController.php  # Thin controller
│           ├── OrdersController.php
│           └── TransferProductsController.php
├── Helpers/
│   └── InventoryHelper.php             # Utility functions, caching
└── Models/
    ├── Inventory.php                   # SINGLE source for quantity
    ├── Stock.php                       # Audit log only (append-only)
    └── ...
```

**Key Principle:** `inventories.quantity` = single source of truth. `stocks` table = audit log only (never queried for quantity calculations).
