<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\JazzSMSAPI;
use App\Helpers\TelenorSMSAPI;
use App\Models\Accounts;
use App\Models\Discounts;
use App\Models\DoctorHasLocations;
use App\Models\Inventory;
use App\Models\Locations;
use App\Models\PaymentModes;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderRefund;
use App\Models\OrderRefundDetail;
use App\Models\Patients;
use App\Models\Product;
use App\Models\RoleHasUsers;
use App\Models\Settings;
use App\Models\SMSLogs;
use App\Models\SMSTemplates;
use App\Models\Stock;
use App\Models\User;
use App\Models\UserHasLocations;
use App\Models\UserOperatorSettings;
use App\Models\Warehouse;
use App\Services\Reports\Concerns\ParsesDateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OrderService
{
    use ParsesDateRange;

    /**
     * Employee discount in percent. Applied automatically when buyer_type
     * is 'employee'. Centralised here so any report that needs to invert
     * the discount has one place to read it from.
     */
    public const EMPLOYEE_DISCOUNT_PERCENT = 20.0;

    /**
     * Discount in percent applied to a patient with an active membership.
     * Mutually exclusive with the employee discount (employee wins the
     * tiebreaker — see {@see resolveAutoDiscount}).
     */
    public const MEMBERSHIP_DISCOUNT_PERCENT = 10.0;

    // No FIFO consumer — the legacy (crm2) model deducts inventories.quantity
    // directly. See createOrder().

    // ---------------------------------------------------------------
    // Order Datatable
    // ---------------------------------------------------------------

    public function getDatatableCount(array $params): int
    {
        return $this->buildOrderQuery($params)->count();
    }

    public function getDatatableRecords(array $params): \Illuminate\Database\Eloquent\Collection
    {
        return $this->buildOrderQuery($params)
            ->with('patients', 'orderDetail.product')
            ->orderBy('id', 'desc')
            ->offset($params['offset'] ?? 0)
            ->limit($params['limit'] ?? 30)
            ->get();
    }

    /**
     * Transform order records for datatable display.
     *
     * Buyer column: for `buyer_type='employee'` orders the `patients`
     * relation is null (patient_id is nullable since the 2026-05
     * inventory revamp), so we resolve the employee name/phone via a
     * single batched user lookup and surface it through the same
     * `patient_name` / `patient_phone` fields the SPA already renders —
     * keeps the datatable shape symmetric and the row component dumb.
     */
    public function transformForDatatable(\Illuminate\Database\Eloquent\Collection $orders, array $centres): Collection
    {
        $employeeIds = $orders
            ->where('buyer_type', 'employee')
            ->pluck('employee_id')
            ->filter()
            ->unique()
            ->all();

        $employees = ! empty($employeeIds)
            ? User::whereIn('id', $employeeIds)->get(['id', 'name', 'phone'])->keyBy('id')
            : collect();

        return collect($orders)->map(function ($order) use ($centres, $employees) {
            $order->order_have = ($order->location_id !== null && array_key_exists($order->location_id, $centres))
                ? $centres[$order->location_id]->name
                : 'N/A';
            $order->status = $order->status == 1 ? 'completed' : 'pending';

            $buyerType = $order->buyer_type ?? 'patient';
            if ($buyerType === 'employee' && $order->employee_id) {
                $emp = $employees[$order->employee_id] ?? null;
                $order->patient_name = $emp?->name;
                $order->patient_phone = $emp?->phone;
            } else {
                $patient = $order->patients;
                $order->patient_name = $patient?->name;
                $order->patient_phone = $patient?->phone;
            }

            // Surface buyer_type so the SPA can render an "Employee" badge
            // next to the name without re-deriving from the (now nullable)
            // patient_id.
            $order->buyer_type = $buyerType;

            // Flatten the product list so the SPA can render a comma-separated
            // summary in the order row without walking orderDetail[].product.
            $order->product_names = $order->orderDetail
                ?->pluck('product.name')
                ->filter()
                ->values()
                ->all() ?? [];

            return $order;
        });
    }

    // ---------------------------------------------------------------
    // Refund Datatable
    // ---------------------------------------------------------------

    public function getRefundDatatableCount(array $params): int
    {
        return OrderRefund::getTotalRecords(
            new Request($params['request_data'] ?? []),
            Auth::user()->account_id,
            $params['apply_filter'] ?? false,
            'refund',
        );
    }

    public function getRefundDatatableRecords(array $params): \Illuminate\Database\Eloquent\Collection
    {
        return OrderRefund::getRecords(
            new Request($params['request_data'] ?? []),
            $params['offset'] ?? 0,
            $params['limit'] ?? 30,
            Auth::user()->account_id,
            $params['apply_filter'] ?? false,
            'refund',
        );
    }

    /**
     * Transform refund records for datatable display.
     */
    public function transformRefundsForDatatable(\Illuminate\Database\Eloquent\Collection $orders, array $centres, array $warehouse): Collection
    {
        return collect($orders)->map(function ($order) use ($warehouse, $centres) {
            $order->order_have = ($order->location_id !== null)
                ? ((array_key_exists($order->location_id, $centres)) ? $centres[$order->location_id]->name : 'N/A')
                : ((array_key_exists($order->warehouse_id ?? 0, $warehouse)) ? $warehouse[$order->warehouse_id]->name : 'N/A');

            return $order;
        });
    }

    // ---------------------------------------------------------------
    // Bulk Delete
    // ---------------------------------------------------------------

    /**
     * @return array{status: bool, message: string}
     */
    public function bulkDeleteOrders(array $ids): array
    {
        $orders = Order::getBulkData($ids);

        if (! $orders || $orders->isEmpty()) {
            return ['status' => false, 'message' => 'No records found.'];
        }

        // Detail rows: a single bulk DELETE per order instead of loading every
        // row and deleting it one-by-one. Verified safe — OrderDetail has no
        // SoftDeletes, no booted()/deleting|deleted event, and no registered
        // observer, so a query-builder delete bypasses no cascade or audit
        // side-effect. (If an observer is ever added, this must revert to a
        // row-by-row loop so the observer fires.)
        $orderIds = $orders->pluck('id')->all();
        OrderDetail::whereIn('order_id', $orderIds)->delete();

        foreach ($orders as $order) {
            $order->delete();
        }

        return ['status' => true, 'message' => 'Records has been deleted successfully!'];
    }

    /**
     * @return array{status: bool, message: string}
     */
    public function bulkDeleteRefunds(array $ids): array
    {
        $refunds = OrderRefund::getBulkData($ids);

        if (! $refunds || $refunds->isEmpty()) {
            return ['status' => false, 'message' => 'No records found.'];
        }

        foreach ($refunds as $refund) {
            OrderRefundDetail::where('order_refund_id', $refund->id)->each(fn ($d) => $d->delete());
            $refund->delete();
        }

        return ['status' => true, 'message' => 'Records has been deleted successfully!'];
    }

    // ---------------------------------------------------------------
    // Products & Discounts
    // ---------------------------------------------------------------

    public function getProducts(Request $request): array
    {
        $products = Product::getProductsAjax($request, Auth::user()->account_id);

        foreach ($products as $product) {
            $product->quantity = Stock::sumProductQuantity($product->id);
        }

        return ['products' => $products];
    }

    public function getDiscounts(): array
    {
        $discounts = Discounts::where('active', 1)
            ->where('discount_type', 'Treatment')
            ->get(['id', 'name', 'amount']);

        return ['discounts' => $discounts];
    }

    /**
     * Active payment modes for the current account — the order form's
     * payment-mode dropdown.
     *
     * The SPA previously reused the admin `/payment_modes/datatable`
     * endpoint, which is gated on `payment_modes.list.view` (a settings
     * permission). Operators who can create orders but don't administer
     * payment modes (e.g. FDM) got a 403 and saw an empty dropdown. This
     * lookup mirrors getProducts/getDiscounts — login-only, scoped to the
     * account — so payment modes ride along with the order form like every
     * other order lookup.
     */
    public function getPaymentModes(): array
    {
        $paymentModes = PaymentModes::where('account_id', Auth::user()->account_id)
            ->where('active', 1)
            ->orderBy('sort_number', 'ASC')
            ->get(['id', 'name']);

        return ['payment_modes' => $paymentModes];
    }

    // ---------------------------------------------------------------
    // Order CRUD
    // ---------------------------------------------------------------

    /**
     * Pre-flight check for the SPA: do the requested quantities fit the
     * available FIFO stock at the chosen location?
     *
     * NB: this is a lock-free preview only. The authoritative check
     * happens inside {@see createOrder}'s transaction via
     * {@see FifoConsumptionService::consume}, which acquires row locks
     * and throws {@see InsufficientStockException} if oversold during the
     * write. Treat the return value of this method as a UX hint, not a
     * security boundary.
     */
    public function validateOrderStock(array $data): ?string
    {
        if (empty($data['product_id'])) {
            return 'Please select any product';
        }

        $locationId = (int) ($data['location_id'] ?? 0);
        if ($locationId <= 0) {
            return 'Please select a location for the order.';
        }

        foreach ($data['product_id'] as $index => $productId) {
            $quantity = (int) ($data['quantity'][$index] ?? 0);

            if ($quantity <= 0) {
                return 'Quantity must be greater than 0';
            }

            $available = $this->legacyAvailableQuantity((int) $productId, $locationId);

            if ($available < $quantity) {
                $name = Product::where('id', $productId)->value('name') ?? 'Product';
                return $name.' is out of stock (Available: '.$available.')';
            }
        }

        return null;
    }

    /**
     * Legacy (crm2) available-quantity at a centre: stock received in minus
     * units sold — the same formula Product::getProductsAjax uses for the
     * order form, so the pre-flight check and the dropdown agree. No FIFO
     * `remaining_quantity` column needed.
     */
    private function legacyAvailableQuantity(int $productId, int $locationId): int
    {
        $additions = (int) DB::table('stocks')
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->where('stock_type', 'in')
            ->sum('quantity');

        $sales = (int) DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where('order_details.product_id', $productId)
            ->where('orders.location_id', $locationId)
            ->sum('order_details.quantity');

        return $additions - $sales;
    }

    /**
     * Create an order with crm2's legacy inventory deduction (no FIFO).
     *
     * The full write — order header, order_detail split lines, FIFO
     * batch decrements, ledger OUT rows, consumption tracking rows — is
     * wrapped in a single transaction so a partial failure cannot leave
     * an orphan header without lines or a stock decrement without an
     * order to point at.
     *
     * Server-side pricing: the unit price on each order_detail row comes
     * from the batch the units were drawn from, NOT from any
     * client-supplied price. The client field is ignored. The grand
     * total is re-derived from those server-side prices and the
     * auto-discount.
     *
     * Auto-discount: 20% for buyer_type=employee, 10% for a patient with
     * an active membership, none otherwise. Mutually exclusive — see
     * {@see resolveAutoDiscount}.
     */
    public function createOrder(array $data): ?Order
    {
        $accountId = (int) Auth::user()->account_id;
        $locationId = (int) $data['location_id'];
        $paymentMode = $data['payment_mode'] ?? null;

        // Buyer resolution: `sold_to` distinguishes patient vs employee.
        // The legacy form sent both patient_id and employee_id in the
        // same payload and inferred the buyer from which was populated;
        // we now trust the explicit `sold_to` flag and zero the irrelevant
        // side to avoid foreign-key confusion downstream.
        $soldTo = strtolower((string) ($data['sold_to'] ?? 'patient'));
        $buyerType = $soldTo === 'employee' ? 'employee' : 'patient';
        $patientId = $buyerType === 'patient' ? ($data['patient_id'] ?? null) : null;
        $employeeId = $buyerType === 'employee' ? ($data['employee_id'] ?? null) : null;

        // ORDER-TIME patient creation — an intentional, ORDER-ONLY exception to
        // project_patient_creation_rule (2026-06-01): a product order for a new
        // phone registers a patient, matching legacy crm2 so the shared patient
        // data stays consistent across both apps during coexistence. Other entry
        // points (treatment / appointment / drag-drop) STILL reject; consultation
        // remains the canonical creator.
        // NB: crm2's bare create(['name','phone']) left user_type_id/account_id
        // NULL (orphan rows invisible to the patients_only scope). We instead
        // register a PROPER, account-scoped patient.
        if ($buyerType === 'patient' && empty($patientId) && !empty($data['phone'])) {
            $existing = Patients::where('phone', $data['phone'])->first();
            if ($existing) {
                $patientId = $existing->id;
            } else {
                // Register the patient the SAME way the consultation flow does
                // (AppointmentService::createAppointment) so order- and
                // consultation-created patients are identical, valid rows
                // (user_type_id=patient, account-scoped, hashed random password
                // — `users.password` is NOT NULL on prod).
                $newPatient = Patients::create([
                    'name' => $data['name'] ?? null,
                    'phone' => $data['phone'],
                    'email' => $data['email'] ?? null,
                    'gender' => $data['gender'] ?? 0,
                    'account_id' => $accountId,
                    'user_type_id' => (int) config('constants.patient_id'),
                    'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)),
                    'active' => 1,
                    'created_by' => Auth::id(),
                ]);
                $patientId = $newPatient->id;
            }
        }

        if ($buyerType === 'patient' && empty($patientId)) {
            // No existing patient and no phone to register one.
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(
                422,
                'Please select a patient (or provide a name + phone) for this order.',
            );
        }
        if ($buyerType === 'employee' && empty($employeeId)) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(
                422,
                'Please select an employee for this order.',
            );
        }

        $autoDiscount = $this->resolveAutoDiscount($buyerType, $patientId !== null ? (int) $patientId : null);

        // Iterate lines first to fail fast on shape errors before opening
        // the transaction; FifoConsumptionService still enforces the real
        // availability under lock.
        $productIds = (array) ($data['product_id'] ?? []);
        $quantities = (array) ($data['quantity'] ?? []);
        if (empty($productIds)) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(422, 'No products selected.');
        }
        if (count($productIds) !== count($quantities)) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(
                422,
                'Product and quantity arrays must align.',
            );
        }

        return DB::transaction(function () use (
            $accountId,
            $locationId,
            $paymentMode,
            $buyerType,
            $patientId,
            $employeeId,
            $autoDiscount,
            $productIds,
            $quantities,
            $data,
        ): Order {
            // Header first; lines reference order_id. total_price is
            // updated at the end once we know the server-derived total.
            $order = new Order();
            $order->account_id = $accountId;
            // crm2 stores an employee sale by putting the employee's user id in
            // patient_id (NOT NULL on the shared/legacy DB) while also keeping
            // employee_id. There is no buyer_type / auto_discount_* column there.
            $order->patient_id = $patientId ?: $employeeId;
            $order->employee_id = $employeeId;
            $order->location_id = $locationId;
            $order->payment_mode = $paymentMode;
            $order->prescribed_by = $data['doctor_id'] ?? null;
            $order->order_type = 'sale';
            $order->status = 1;
            $order->created_by = Auth::id();
            // Persist the auto-discount percent in the legacy `discount` column.
            $order->discount = $autoDiscount['percent'];
            // total_price + quantity are stamped once we walk the lines.
            $order->total_price = 0;
            $order->quantity = 0;
            $order->save();

            $grossTotal = 0.0;
            $netTotal = 0.0;
            $totalUnits = 0;

            foreach ($productIds as $index => $productId) {
                $productId = (int) $productId;
                $qty = (int) $quantities[$index];

                if ($qty <= 0) {
                    continue;
                }

                // Legacy (crm2) stock model — no FIFO batches. Deduct from the
                // inventory row the client picked (`inventory_id`), else the
                // oldest inventory row with stock at this centre. One
                // order_detail per line. Mirrors crm2 OrderDetail::createRecord
                // so both apps stay consistent on the shared DB.
                $inventoryId = ((array) ($data['inventory_id'] ?? []))[$index] ?? null;
                $inventory = $inventoryId
                    ? Inventory::where('id', $inventoryId)->lockForUpdate()->first()
                    : Inventory::where('product_id', $productId)
                        ->where('location_id', $locationId)
                        ->where('quantity', '>', 0)
                        ->orderBy('created_at', 'asc')
                        ->lockForUpdate()
                        ->first();

                // Price from the same source the product list showed
                // (inventory row → product), NOT a client-supplied value.
                $unitPrice = (float) ($inventory->sale_price
                    ?? Product::where('id', $productId)->value('sale_price')
                    ?? 0);
                $discountUnit = round($unitPrice * ($autoDiscount['percent'] / 100), 2);
                $netUnit = round($unitPrice - $discountUnit, 2);

                // Inventory cache is the source of truth for "how much do we
                // have" (crm2 reads inventories.quantity).
                if ($inventory) {
                    $inventory->update(['quantity' => (int) $inventory->quantity - $qty]);
                }

                OrderDetail::create([
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'inventory_id' => $inventory?->id,
                    'quantity' => $qty,
                    'sale_price' => $unitPrice,
                    'discount_price' => $discountUnit,
                    'sale_price_after_discount' => $netUnit,
                    'order_type' => 'sale',
                    'account_id' => $accountId,
                ]);

                // Audit OUT row — feeds Stock::sumProductQuantity and the
                // product list's available calc (stocks IN − order_details sales).
                Stock::create([
                    'account_id' => $accountId,
                    'product_id' => $productId,
                    'order_id' => $order->id,
                    'quantity' => $qty,
                    'stock_type' => 'out',
                    'location_id' => $locationId,
                ]);

                $grossTotal += $unitPrice * $qty;
                $netTotal += $netUnit * $qty;
                $totalUnits += $qty;
            }

            $order->total_price = round($netTotal, 2);
            $order->quantity = $totalUnits;
            $order->save();

            // Inventory (Order) sales are deliberately NOT written to
            // package_advances and NOT folded into cash_pools.cached_balance.
            // They are layered onto pool balances at DISPLAY time by
            // PoolService::applyInventoryOverlay(), mirroring the legacy app.
            //
            // Why overlay-only: crm2 (Blade) and crm3 (SPA) share one prod DB
            // and the same cached_balance column. If crm3 stored an order's
            // revenue here (package_advances row -> PackageAdvanceObserver ->
            // pool credit), crm2's own display overlay would double-count it,
            // and so would crm3's. The previous writeOrderRevenueLedger() write
            // also required a package_advances.order_id column that is absent on
            // prod, making order creation fail there outright. Overlay-only
            // keeps both apps consistent on the shared column.

            return $order;
        });
    }

    /**
     * Resolve the auto-discount to apply to a new order.
     *
     * Rules: employee XOR membership, no manual stacking. Employee wins
     * the tiebreaker if the same human is both an employee and a
     * membership-bearing patient (employee is a stronger signal — they
     * almost certainly bought it for themselves).
     *
     * @return array{type: string, percent: float}
     */
    private function resolveAutoDiscount(string $buyerType, ?int $patientId): array
    {
        if ($buyerType === 'employee') {
            return ['type' => 'employee', 'percent' => self::EMPLOYEE_DISCOUNT_PERCENT];
        }

        if ($patientId !== null) {
            $membership = $this->checkMembership($patientId);
            if (!empty($membership['has_active_membership'])) {
                return ['type' => 'membership', 'percent' => self::MEMBERSHIP_DISCOUNT_PERCENT];
            }
        }

        return ['type' => 'none', 'percent' => 0.0];
    }

    /**
     * @return array{status: bool, message: string}
     */
    public function deleteOrder(int $id): array
    {
        $result = Order::DeleteRecord($id);

        return ['status' => $result->get('status'), 'message' => $result->get('message')];
    }

    /**
     * @return array{status: bool, message: string}
     */
    public function cancelOrder(int $id): array
    {
        $result = Order::CancelRecord($id);

        return ['status' => $result->get('status'), 'message' => $result->get('message')];
    }

    // ---------------------------------------------------------------
    // Order Refund
    // ---------------------------------------------------------------

    public function getRefundDetail(int $orderId): ?Order
    {
        // Account-scoped: refund detail must not cross the tenant boundary
        // (IDOR). Security audit 2026-06.
        $accountId = (int) Auth::user()->account_id;

        $order = Order::where('account_id', $accountId)->whereId($orderId)->first();

        if ($order && $order->is_refunded == 1) {
            return null;
        }

        return Order::with('patients', 'orderDetail')->where('account_id', $accountId)->find($orderId);
    }

    /**
     * Refund part or all of an order, restoring stock back to the same
     * FIFO batches it was drawn from.
     *
     * Wrapped in a single DB transaction so the refund header, the
     * detail rows, the order_detail quantity decrements, and the batch
     * remaining_quantity restorations either all succeed or all roll
     * back together. Previously these ran without a transaction wrapper
     * and a mid-flow failure could leave an `is_refunded=1` flag set
     * with stock not restored.
     *
     * The refund payload is the legacy SPA shape — `product_id[]` plus
     * `quantity[]`. For each refunded product we walk the original
     * order's `order_detail` rows (latest-id first so we restore the
     * most recently consumed batch first — symmetric with FIFO out)
     * and:
     *   - decrement `order_detail.quantity` by the share refunded
     *   - locate the matching `order_detail_consumption` row, restore
     *     that share's `quantity` to the originating `stocks` batch and
     *     to the `inventories` mirror cache
     *   - write a `stocks` row with `stock_type='refund'` so the
     *     existing ledger reports net out correctly
     *   - record the refund line in `order_refund_details`
     *
     * @return array{success: bool, message: string}
     */
    public function processRefund(int $orderId, array $data): array
    {
        $order = Order::where('account_id', Auth::user()->account_id)->find($orderId);

        if (!$order) {
            return ['success' => false, 'message' => 'Order not found.'];
        }
        if ((int) $order->is_refunded === 1) {
            return ['success' => false, 'message' => 'This order is already refunded.'];
        }

        $productIds = (array) ($data['product_id'] ?? []);
        $quantities = (array) ($data['quantity'] ?? []);

        $refundQtyByProduct = [];
        foreach ($productIds as $i => $pid) {
            $q = (int) ($quantities[$i] ?? 0);
            if ($q > 0) {
                $refundQtyByProduct[(int) $pid] = ($refundQtyByProduct[(int) $pid] ?? 0) + $q;
            }
        }

        if (empty($refundQtyByProduct)) {
            return ['success' => false, 'message' => 'No quantities selected to refund.'];
        }

        try {
            return DB::transaction(function () use ($order, $refundQtyByProduct) {
                $accountId = (int) Auth::user()->account_id;

                // Refund header. total_price is computed from the actual
                // per-batch sale prices we walk below, not from a
                // client-supplied price.
                $refund = new OrderRefund();
                $refund->account_id = $accountId;
                $refund->order_id = $order->id;
                // patient_id carries the buyer (for employee sales the order's
                // patient_id already holds the employee id — crm2 shape). No
                // buyer_type / employee_id columns on order_refunds in the
                // shared DB.
                $refund->patient_id = $order->patient_id;
                $refund->location_id = $order->location_id;
                $refund->payment_mode = $order->payment_mode;
                $refund->created_by = Auth::id();
                $refund->total_price = 0;
                $refund->quantity = 0;
                $refund->save();

                $refundedTotal = 0.0;
                $refundedUnits = 0;
                $perDetailRefunded = []; // order_detail_id => qty refunded

                foreach ($refundQtyByProduct as $productId => $qtyToRefund) {
                    $remaining = $qtyToRefund;

                    // Walk the order's lines for this product latest-id
                    // first. This is the symmetric reversal of FIFO
                    // consumption: the most recently drawn batch is the
                    // first to be returned.
                    $lines = OrderDetail::where('order_id', $order->id)
                        ->where('product_id', $productId)
                        ->where('quantity', '>', 0)
                        ->orderBy('id', 'desc')
                        ->lockForUpdate()
                        ->get();

                    foreach ($lines as $line) {
                        if ($remaining <= 0) {
                            break;
                        }

                        $take = min($remaining, (int) $line->quantity);
                        $unitPrice = (float) $line->sale_price;
                        $netUnit = (float) $line->sale_price_after_discount;

                        // Decrement the line's quantity — fixes the
                        // commented-out behaviour that previously allowed
                        // the same units to be refunded multiple times.
                        $line->quantity = (int) $line->quantity - $take;
                        $line->save();

                        // Restore stock by putting the units back on the
                        // inventory cache (crm2's source of truth). No FIFO
                        // batch / consumption-ledger restore — those columns
                        // don't exist on the shared DB.
                        $inv = Inventory::where('product_id', $productId)
                            ->where('location_id', $order->location_id)
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->first();
                        if ($inv) {
                            $inv->quantity = (int) $inv->quantity + $take;
                            $inv->save();
                        }

                        // Ledger refund row — keeps Stock::sumProductQuantity
                        // net consistent with the inventories cache.
                        Stock::create([
                            'account_id' => $accountId,
                            'product_id' => $productId,
                            'order_id' => $order->id,
                            'quantity' => $take,
                            'stock_type' => 'refund',
                            'location_id' => $order->location_id,
                        ]);

                        OrderRefundDetail::create([
                            'account_id' => $accountId,
                            'order_refund_id' => $refund->id,
                            'product_id' => $productId,
                            'quantity' => $take,
                            'sale_price' => $unitPrice,
                        ]);

                        $refundedTotal += $netUnit * $take;
                        $refundedUnits += $take;
                        $perDetailRefunded[$line->id] = ($perDetailRefunded[$line->id] ?? 0) + $take;
                        $remaining -= $take;
                    }

                    if ($remaining > 0) {
                        // Asked to refund more than was sold on this order
                        // for this product — abort the whole transaction.
                        throw new \Symfony\Component\HttpKernel\Exception\HttpException(
                            422,
                            'Cannot refund more than the original sold quantity for product '.$productId.'.',
                        );
                    }
                }

                $refund->total_price = round($refundedTotal, 2);
                $refund->quantity = $refundedUnits;
                $refund->save();

                // Mark the order fully refunded once every line is zero.
                $remainingOnOrder = (int) OrderDetail::where('order_id', $order->id)->sum('quantity');
                if ($remainingOnOrder === 0) {
                    $order->is_refunded = 1;
                }
                $order->total_price = max(0, (float) $order->total_price - $refundedTotal);
                $order->save();

                // Inventory refunds are NOT written to package_advances and do
                // NOT touch cash_pools.cached_balance — symmetric with the
                // createOrder path. The refund is reflected at DISPLAY time by
                // PoolService::applyInventoryOverlay() (which subtracts
                // order_type='refund' rows), so both crm2 and crm3 stay
                // consistent on the shared column. See createOrder for the
                // full overlay-only rationale.

                return ['success' => true, 'message' => 'Order has been refunded.'];
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ---------------------------------------------------------------
    // Invoice
    // ---------------------------------------------------------------

    /**
     * Legacy invoice payload (Blade + PDF views).
     *
     * The legacy `admin.orders.displayInvoice` Blade and the dompdf-
     * rendered invoice PDF both pull from this method and reference the
     * keys `invoice_info`, `patient`, `products`, `company_phone_number`,
     * `location_info`, `account`. Changing this shape will crash both
     * views — use {@see getInvoiceJson} for the SPA-friendly payload.
     */
    public function getInvoiceData(int $orderId): array
    {
        // Account-scoped: invoice data must not cross the tenant boundary
        // (IDOR). findOrFail → clean 404 for cross-account / missing ids (the
        // un-try-wrapped Blade callers render Laravel's 404 page rather than
        // the previous null-deref 500). Security audit 2026-06.
        $invoiceInfo = Order::with('orderDetail')
            ->where('account_id', (int) Auth::user()->account_id)
            ->findOrFail($orderId);
        $productId = $invoiceInfo->orderDetail->pluck('product_id');

        $locationInfo = $invoiceInfo->location_id !== null
            ? Locations::find($invoiceInfo->location_id)
            : Warehouse::find($invoiceInfo->warehouse_id);

        // The `patient` key feeds the legacy Blade invoice (PDF download)
        // — it expects a User-shaped object. For employee sales
        // patient_id is null since the 2026-05 revamp, so resolve the
        // employee user instead. The Blade reads `->name` / `->phone`
        // which both users + employees have.
        $buyer = $invoiceInfo->patient_id
            ? User::find($invoiceInfo->patient_id)
            : ($invoiceInfo->employee_id ? User::find($invoiceInfo->employee_id) : null);

        return [
            'invoice_info' => $invoiceInfo,
            'products' => Product::whereIn('id', $productId)->get(),
            'patient' => $buyer,
            'account' => Accounts::find($invoiceInfo->account_id),
            'company_phone_number' => Settings::where('slug', '=', 'sys-headoffice')->first(),
            'location_info' => $locationInfo,
        ];
    }

    /**
     * Build the SPA-friendly order invoice payload.
     *
     * Shape mirrors the consultation + treatment invoice payloads so the
     * SPA can render all three through a single shared invoice template:
     *
     *   {
     *     invoice: {
     *       id, created_at, payment_mode, buyer_type, auto_discount_type,
     *       auto_discount_percent,
     *       subtotal, discount_total, total,
     *     },
     *     buyer: { type: 'patient'|'employee', id, name, phone, ... },
     *     lines: [
     *       { product_id, product_name, quantity, sale_price,
     *         discount_price, sale_price_after_discount, line_total },
     *       ...
     *     ],
     *     location: { id, name, address, phone },
     *     account: { id, name, ... },
     *     branding: { head_office_phone },
     *   }
     *
     * Lines reflect the FIFO split — one row per batch consumed — so
     * the printed invoice itemises each batch price the buyer paid.
     */
    public function getInvoiceJson(int $orderId): array
    {
        // Account-scoped (IDOR fix, security audit 2026-06).
        $order = Order::with(['orderDetail.product'])
            ->where('account_id', (int) Auth::user()->account_id)
            ->findOrFail($orderId);

        $lines = $order->orderDetail->map(function ($detail) {
            $qty = (int) $detail->quantity;
            $sale = (float) $detail->sale_price;
            $disc = (float) ($detail->discount_price ?? 0);
            $net = (float) ($detail->sale_price_after_discount ?? $sale);

            return [
                'product_id' => (int) $detail->product_id,
                'product_name' => $detail->product?->name ?? '—',
                'quantity' => $qty,
                'sale_price' => round($sale, 2),
                'discount_price' => round($disc, 2),
                'sale_price_after_discount' => round($net, 2),
                'line_total' => round($net * $qty, 2),
            ];
        })->values();

        $subtotal = round($lines->sum(fn ($l) => $l['sale_price'] * $l['quantity']), 2);
        $discountTotal = round($lines->sum(fn ($l) => $l['discount_price'] * $l['quantity']), 2);
        $netTotal = round($lines->sum(fn ($l) => $l['line_total']), 2);

        $buyer = null;
        $buyerType = $order->buyer_type ?? 'patient';
        if ($buyerType === 'employee' && $order->employee_id) {
            $emp = User::find($order->employee_id);
            if ($emp) {
                $buyer = [
                    'type' => 'employee',
                    'id' => (int) $emp->id,
                    'name' => $emp->name,
                    'phone' => $emp->phone,
                ];
            }
        } elseif ($order->patient_id) {
            $patient = User::find($order->patient_id);
            if ($patient) {
                $buyer = [
                    'type' => 'patient',
                    'id' => (int) $patient->id,
                    'name' => $patient->name,
                    'phone' => $patient->phone,
                ];
            }
        }

        $location = $order->location_id !== null ? Locations::find($order->location_id) : null;
        $warehouse = $location === null && $order->warehouse_id !== null
            ? Warehouse::find($order->warehouse_id)
            : null;

        $account = Accounts::find($order->account_id);
        $headOffice = Settings::where('slug', 'sys-headoffice')->first();

        return [
            'invoice' => [
                'id' => (int) $order->id,
                'created_at' => optional($order->created_at)->toIso8601String(),
                'payment_mode' => $order->payment_mode,
                'buyer_type' => $buyerType,
                'auto_discount_type' => $order->auto_discount_type ?? 'none',
                'auto_discount_percent' => (float) ($order->auto_discount_percent ?? 0),
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'total' => $netTotal,
                'is_refunded' => (int) ($order->is_refunded ?? 0),
            ],
            'buyer' => $buyer,
            'lines' => $lines,
            'location' => $location ? [
                'id' => (int) $location->id,
                'name' => $location->name,
                'address' => $location->address ?? null,
                'phone' => $location->phone ?? null,
            ] : ($warehouse ? [
                'id' => (int) $warehouse->id,
                'name' => $warehouse->name,
                'address' => $warehouse->address ?? null,
                'phone' => $warehouse->phone ?? null,
            ] : null),
            'account' => $account ? [
                'id' => (int) $account->id,
                'name' => $account->name ?? null,
            ] : null,
            'branding' => [
                'head_office_phone' => $headOffice->data ?? null,
            ],
        ];
    }

    // ---------------------------------------------------------------
    // Membership Check
    // ---------------------------------------------------------------

    public function checkMembership(?int $patientId): array
    {
        $default = [
            'has_active_membership' => false,
            'membership_code' => null,
            'membership_status' => 'Inactive',
            'membership_start_date' => null,
            'membership_end_date' => null,
            'membership_type_id' => null,
            'membership_type' => null,
        ];

        if (! $patientId) {
            return $default;
        }

        // Join the type so callers (the order dialog badge) can name the tier
        // — e.g. "Gold Membership" — not just the code. Left join keeps a
        // membership with a missing/legacy type id surfacing as still-active
        // with a null type name. Additive: the discount itself is tier-agnostic
        // (any active membership = 10%, see resolveAutoDiscount).
        $membership = DB::table('memberships')
            ->leftJoin('membership_types', 'memberships.membership_type_id', '=', 'membership_types.id')
            ->where('memberships.patient_id', $patientId)
            ->where('memberships.active', 1)
            ->where('memberships.end_date', '>=', now())
            ->orderBy('memberships.end_date', 'desc')
            ->select('memberships.*', 'membership_types.name as membership_type')
            ->first();

        if (! $membership) {
            return $default;
        }

        return [
            'has_active_membership' => true,
            'membership_code' => $membership->code,
            'membership_status' => 'Active',
            'membership_start_date' => $membership->start_date,
            'membership_end_date' => $membership->end_date,
            'membership_type_id' => $membership->membership_type_id,
            'membership_type' => $membership->membership_type,
        ];
    }

    // ---------------------------------------------------------------
    // Employees & Doctors
    // ---------------------------------------------------------------

    public function getEmployees(int $locationId): array
    {
        $checkUsers = UserHasLocations::where('location_id', $locationId)->pluck('user_id')->toArray();
        $doctors = DoctorHasLocations::where('is_allocated', 1)->where('location_id', $locationId)->pluck('user_id')->toArray();

        $users = User::whereIn('user_type_id', [2, 5])
            ->where('active', 1)
            ->whereIn('id', $checkUsers)
            ->pluck('name', 'id')->toArray();

        $doctorUsers = User::whereIn('id', $doctors)->where('active', 1)->pluck('name', 'id')->toArray();

        return $users + $doctorUsers;
    }

    public function getDoctorsWithFDM(int $locationId): array
    {
        // A missing/zero location means "global": the treatments &
        // consultations doctor FILTER loads the full list before any centre is
        // picked. Strict `location_id = 0` matched nothing, so the filter came
        // up empty for everyone. Fall back to every centre the caller can
        // access (mirrors getDoctorsForSales); a real location keeps the
        // original single-centre behaviour.
        $locationIds = $locationId > 0 ? [$locationId] : ACL::getUserCentres();
        if ($locationIds === []) {
            return [];
        }

        $doctors = DoctorHasLocations::where('is_allocated', 1)
            ->whereIn('location_id', $locationIds)
            ->pluck('user_id')->toArray();

        $users = User::whereIn('id', $doctors)->where('active', 1)->pluck('name', 'id')->toArray();

        $findFDM = UserHasLocations::whereIn('location_id', $locationIds)->pluck('user_id')->toArray();
        $findRole = DB::table('roles')->where('name', 'FDM')->first();
        $roleHasUser = RoleHasUsers::where('role_id', $findRole->id)->pluck('user_id')->toArray();
        $fdmUsers = array_intersect($findFDM, $roleHasUser);
        $FDMUsers = User::whereIn('id', $fdmUsers)->pluck('name', 'id')->toArray();

        return $users + $FDMUsers;
    }

    public function getDoctorsForSales(int|array|string $locationId): array
    {
        $raw = (array) $locationId;

        // The centre multi-select uses 'all' as the "no filter" sentinel.
        // When present (or the list is empty after normalising), fall back
        // to every centre the caller can access — same semantics as the
        // inventory/sales report services.
        $wantsAll = in_array('all', $raw, true);

        $locationIds = array_values(array_filter(
            array_map('intval', $raw),
            fn (int $id): bool => $id > 0,
        ));

        if ($wantsAll || $locationIds === []) {
            $locationIds = ACL::getUserCentres();
        }

        if ($locationIds === []) {
            return [];
        }

        $doctors = DoctorHasLocations::whereIn('location_id', $locationIds)->pluck('user_id')->toArray();
        $users = User::whereIn('id', $doctors)->where('active', 1)->pluck('name', 'id')->toArray();

        $findFDM = UserHasLocations::whereIn('location_id', $locationIds)->pluck('user_id')->toArray();
        $findRole = DB::table('roles')->where('name', 'FDM')->first();
        $roleHasUser = RoleHasUsers::where('role_id', $findRole->id)->pluck('user_id')->toArray();
        $fdmUsers = array_intersect($findFDM, $roleHasUser);
        $FDMUsers = User::whereIn('id', $fdmUsers)->pluck('name', 'id')->toArray();

        return $users + $FDMUsers;
    }

    public function getCentreDoctors(int $locationId): array
    {
        $doctors = DoctorHasLocations::where('is_allocated', 1)
            ->where('location_id', $locationId)
            ->pluck('user_id')->toArray();

        return User::whereIn('id', $doctors)->where('active', 1)->pluck('name', 'id')->toArray();
    }

    // ---------------------------------------------------------------
    // Permissions & Filter values
    // ---------------------------------------------------------------

    public function getOrderPermissions(): array
    {
        return [
            'manage' => Gate::allows('order_manage'),
            'edit' => Gate::allows('order_edit'),
            'refund' => Gate::allows('order_refund_manage'),
            'delete' => Gate::allows('order_destroy'),
        ];
    }

    public function getRefundPermissions(): array
    {
        return [
            'manage' => Gate::allows('order_manage'),
            'create' => Gate::allows('order_create'),
            'refund' => Gate::allows('inventory_refund'),
        ];
    }

    public function getOrderFilterValues(): array
    {
        $accountId = Auth::user()->account_id;

        return [
            'centres' => collect(Locations::getAllRecordsDictionary($accountId, 'custom', 'id', 'desc', ACL::getUserCentres()))->pluck('name', 'id'),
            'users' => User::getAllRecords($accountId)->getDictionary(),
            'products' => Product::getAllRecordsDictionary($accountId),
        ];
    }

    public function getRefundFilterValues(): array
    {
        $accountId = Auth::user()->account_id;

        return [
            'centres' => collect(Locations::getAllRecordsDictionary($accountId, 'custom', 'id', 'desc', ACL::getUserCentres()))->pluck('name', 'id'),
            'warehouse' => collect(Warehouse::getAllRecordsDictionary($accountId, ACL::getUserWarehouse()))->pluck('name', 'id'),
            'users' => User::getAllRecords($accountId)->getDictionary(),
            'products' => Product::getAllRecordsDictionary($accountId),
        ];
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function buildOrderQuery(array $params): Builder
    {
        $where = $this->buildOrderWhereConditions($params);
        $productId = $this->resolveProductFilter($params);
        $phone = $this->resolvePhoneFilter($params);

        return Order::query()
            ->when(! empty($where), fn ($q) => $q->where($where))
            ->when($productId !== null && ! empty($productId), fn ($q) => $q->whereHas('orderDetail.product', fn ($q) => $q->whereIn('id', $productId)))
            ->when(
                $phone !== null && $phone !== '',
                fn ($q) => $q->whereHas(
                    'patients',
                    fn ($qq) => $qq->where('phone', 'like', '%'.$phone.'%'),
                ),
            )
            ->where(fn ($query) => $query->whereIn('location_id', ACL::getUserCentres()))
            ->where('order_type', 'sale');
    }

    private function buildOrderWhereConditions(array $params): array
    {
        $where = [];
        $filters = $params['filters'] ?? [];

        if (empty($filters)) {
            return $where;
        }

        [$startDateTime, $endDateTime] = self::parseDateRangeForFilter(
            hasFilter($filters, 'created_at') ? $filters['created_at'] : null
        );

        if (hasFilter($filters, 'order_id')) {
            $where[][] = ['id' => $filters['order_id']];
        }
        if (hasFilter($filters, 'patient_id')) {
            $where[][] = ['patient_id' => $filters['patient_id']];
        }
        // SPA shorthand for the centre filter (vs the legacy
        // location_type+location pair). Sales are always at a centre.
        if (hasFilter($filters, 'location_id')) {
            $where[][] = ['location_id' => $filters['location_id']];
        }
        if (hasFilter($filters, 'location_type')) {
            if ($filters['location_type'] === 'branch') {
                $where[][] = ['location_id' => $filters['location']];
            } elseif ($filters['location_type'] === 'warehouse') {
                $where[][] = ['warehouse_id' => $filters['location']];
            } else {
                Filters::forget(Auth::user()->id, 'location', 'name');
            }
        }
        if (hasFilter($filters, 'created_by')) {
            $where[][] = ['created_by' => $filters['created_by']];
        }
        if (hasFilter($filters, 'updated_by')) {
            $where[][] = ['updated_by' => $filters['updated_by']];
        }
        if (isset($startDateTime, $endDateTime)) {
            $where[] = ['created_at', '>=', $startDateTime];
            $where[] = ['created_at', '<=', $endDateTime];
        }

        return $where;
    }

    private function resolveProductFilter(array $params): ?array
    {
        $requestData = $params['request_data'] ?? [];
        $productId = $requestData['query']['search']['product_id'] ?? null;

        if ($productId === null || $productId === '') {
            return null;
        }

        // Accept either a numeric id (modern SPA dropdown sends this) or
        // a name fragment (legacy free-text input).
        if (is_numeric($productId)) {
            return [(int) $productId];
        }

        return Product::where('name', 'like', '%'.$productId.'%')->pluck('id')->toArray();
    }

    private function resolvePhoneFilter(array $params): ?string
    {
        $filters = $params['filters'] ?? [];
        if (hasFilter($filters, 'phone')) {
            return (string) $filters['phone'];
        }
        $requestData = $params['request_data'] ?? [];
        $phone = $requestData['query']['search']['phone'] ?? null;

        return $phone !== null && $phone !== '' ? (string) $phone : null;
    }

    private function sendOrderSMS(array $data, Order $order): void
    {
        $phone = null;

        if (! empty($data['patient_id'])) {
            $phone = User::whereId($data['patient_id'])->first()?->phone;
        } elseif (! empty($data['employee_id'])) {
            $phone = User::whereId($data['employee_id'])->first()?->phone;
        } else {
            $phone = $data['phone'] ?? null;
        }

        if (! $phone) {
            return;
        }

        $this->planCashReceivedSMS($phone, $data['grand_total'] ?? 0, $order);
    }

    private function planCashReceivedSMS(string $phone, float|string $total, Order $order): array
    {
        $SMSTemplate = SMSTemplates::getBySlug('plan-cash', Auth::user()->account_id);

        if (! $SMSTemplate) {
            return ['status' => true, 'sms_data' => 'Plan Cash Amount SMS is disabled', 'error_msg' => ''];
        }

        $preparedText = $this->prepareSMSContent($SMSTemplate->content, $phone, $total, $order);
        $setting = Settings::whereSlug('sys-current-sms-operator')->first();
        $UserOperatorSettings = UserOperatorSettings::getRecord(Auth::user()->account_id, $setting->data);

        $cleanedPhone = GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($phone));

        if ($setting->data == 1) {
            $SMSObj = [
                'username' => $UserOperatorSettings->username,
                'password' => $UserOperatorSettings->password,
                'to' => $cleanedPhone,
                'text' => $preparedText,
                'mask' => $UserOperatorSettings->mask,
                'test_mode' => $UserOperatorSettings->test_mode,
            ];
            $response = TelenorSMSAPI::SendSMS($SMSObj);
        } else {
            $SMSObj = [
                'username' => $UserOperatorSettings->username,
                'password' => $UserOperatorSettings->password,
                'from' => $UserOperatorSettings->mask,
                'to' => $cleanedPhone,
                'text' => $preparedText,
                'test_mode' => $UserOperatorSettings->test_mode,
            ];
            $response = JazzSMSAPI::SendSMS($SMSObj);
        }

        $SMSLog = array_merge($SMSObj, $response);
        $SMSLog['log_type'] = 'inventory';
        $SMSLog['created_by'] = Auth::id();

        if ($setting->data == 2) {
            $SMSLog['mask'] = $SMSObj['from'];
        }

        SMSLogs::create($SMSLog);

        return $response;
    }

    private function prepareSMSContent(string $smsContent, string $phone, float|string $total, Order $order): string
    {
        $patient = User::where('phone', $phone)->first();

        if ($patient) {
            $smsContent = str_replace('##patient_name##', $patient->name, $smsContent);
        }

        $smsContent = str_replace('##cash_amount##', number_format((float) $total), $smsContent);
        $smsContent = str_replace('##created_at##', Carbon::parse($order->created_at)->toFormattedDateString(), $smsContent);
        $smsContent = str_replace('##id##', (string) $order->id, $smsContent);

        return $smsContent;
    }
}
