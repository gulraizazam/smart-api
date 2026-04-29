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
use App\Models\Locations;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderRefund;
use App\Models\OrderRefundDetail;
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
     */
    public function transformForDatatable(\Illuminate\Database\Eloquent\Collection $orders, array $centres): Collection
    {
        return collect($orders)->map(function ($order) use ($centres) {
            $order->order_have = ($order->location_id !== null && array_key_exists($order->location_id, $centres))
                ? $centres[$order->location_id]->name
                : 'N/A';
            $order->status = $order->status == 1 ? 'completed' : 'pending';

            // Surface patient name + phone for the SPA's avatar-style row.
            // The relation is already eager-loaded above; we flatten it here
            // so the frontend doesn't have to walk a nested object.
            $patient = $order->patients;
            $order->patient_name = $patient?->name;
            $order->patient_phone = $patient?->phone;

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

        foreach ($orders as $order) {
            OrderDetail::where('order_id', $order->id)->each(fn ($d) => $d->delete());
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

    // ---------------------------------------------------------------
    // Order CRUD
    // ---------------------------------------------------------------

    /**
     * Validate order stock availability.
     *
     * @return string|null Error message or null if valid
     */
    public function validateOrderStock(array $data): ?string
    {
        if (empty($data['product_id'])) {
            return 'Please select any product';
        }

        foreach ($data['product_id'] as $index => $productId) {
            $quantity = $data['quantity'][$index];

            if ($quantity <= 0) {
                return 'Quantity must be greater than 0';
            }

            $totalAdditions = DB::table('stocks')
                ->where('product_id', $productId)
                ->where('location_id', $data['location_id'])
                ->where('stock_type', 'in')
                ->sum('quantity');

            $totalSales = DB::table('order_details')
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->where('order_details.product_id', $productId)
                ->where('orders.location_id', $data['location_id'])
                ->sum('order_details.quantity');

            $availableQuantity = $totalAdditions - $totalSales;

            $product = Product::find($productId);
            $productName = $product?->name ?? 'Product';

            if ($availableQuantity < $quantity) {
                return $productName.' quantity is out of stock (Available: '.$availableQuantity.')';
            }
        }

        return null;
    }

    /**
     * Create an order with details and send SMS.
     */
    public function createOrder(array $data): ?Order
    {
        $request = new Request($data);
        $accountId = Auth::user()->account_id;
        $products = array_combine($data['product_id'], $data['quantity']);

        $order = Order::createRecord($request, $accountId, $products);

        if (! $order) {
            return null;
        }

        // Send SMS
        $this->sendOrderSMS($data, $order);

        // Create order details
        $detailResult = OrderDetail::createRecord($request, $accountId, $order->id);

        if (! $detailResult) {
            return null;
        }

        return $order;
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
        $order = Order::whereId($orderId)->first();

        if ($order && $order->is_refunded == 1) {
            return null;
        }

        return Order::with('patients', 'orderDetail')->find($orderId);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function processRefund(int $orderId, array $data): array
    {
        $order = Order::where('id', $orderId)->first();

        if ($order->is_refunded == 1) {
            return ['success' => false, 'message' => 'This Order is already refunded!'];
        }

        if (array_sum($data['quantity']) == 0) {
            return ['success' => false, 'message' => 'You do not have refunded any product.'];
        }

        $request = new Request($data);
        $orderRefund = OrderRefund::refund($orderId, $request);

        if ($orderRefund) {
            OrderRefundDetail::refund($order->location_id, $orderId, $orderRefund->id, $request, Auth::user()->account_id);

            return ['success' => true, 'message' => 'Order has been refunded.'];
        }

        return ['success' => false, 'message' => 'Something went wrong.'];
    }

    // ---------------------------------------------------------------
    // Invoice
    // ---------------------------------------------------------------

    public function getInvoiceData(int $orderId): array
    {
        $invoiceInfo = Order::with('orderDetail')->where(['id' => $orderId])->first();
        $productId = $invoiceInfo->orderDetail->pluck('product_id');

        $locationInfo = $invoiceInfo->location_id !== null
            ? Locations::find($invoiceInfo->location_id)
            : Warehouse::find($invoiceInfo->warehouse_id);

        return [
            'invoice_info' => $invoiceInfo,
            'products' => Product::whereIn('id', $productId)->get(),
            'patient' => User::find($invoiceInfo->patient_id),
            'account' => Accounts::find($invoiceInfo->account_id),
            'company_phone_number' => Settings::where('slug', '=', 'sys-headoffice')->first(),
            'location_info' => $locationInfo,
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
        ];

        if (! $patientId) {
            return $default;
        }

        $membership = DB::table('memberships')
            ->where('patient_id', $patientId)
            ->where('active', 1)
            ->where('end_date', '>=', now())
            ->orderBy('end_date', 'desc')
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
        $doctors = DoctorHasLocations::where('is_allocated', 1)
            ->where('location_id', $locationId)
            ->pluck('user_id')->toArray();

        $users = User::whereIn('id', $doctors)->where('active', 1)->pluck('name', 'id')->toArray();

        $locationIds = [$locationId];
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
