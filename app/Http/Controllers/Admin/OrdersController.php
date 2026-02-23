<?php

namespace App\Http\Controllers\Admin;

use stdClass;
use App\Helpers\ACL;
use App\Models\User;
use App\Models\Order;
use App\Models\Stock;
use App\Models\Product;
use App\Models\Accounts;
use App\Models\Settings;
use App\Models\Discounts;
use App\Models\Locations;
use App\Models\Warehouse;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use App\HelperModule\ApiHelper;
use App\Models\TransferProduct;
use App\Helpers\GeneralFunctions;
use App\Helpers\JazzSMSAPI;
use App\Helpers\TelenorSMSAPI;
use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use App\Models\DoctorHasLocations;
use App\Models\Inventory;
use App\Models\OrderRefund;
use App\Models\OrderRefundDetail;
use App\Models\RoleHasUsers;
use App\Models\SMSLogs;
use App\Models\SMSTemplates;
use App\Models\UserHasLocations;
use App\Models\UserOperatorSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OrdersController extends Controller
{
    protected $error;

    protected $success;

    protected $unauthorized;

    public function __construct()
    {
        $this->error = config('constants.api_status.error');
        $this->success = config('constants.api_status.success');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Display a listing of products.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Gate::allows('order_manage')) {
            return abort(401);
        }

        return view('admin.orders.index');
    }

    /**
     * Display a listing of products
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {
            $records = [];
            $records['data'] = [];
            $filename = 'transfer_product';
            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, $filename);

            if (isset($apply_filter['delete'])) {
                $ids = explode(',', $apply_filter['delete']);
                $orders = Order::getBulkData($ids);
                if ($orders) {
                    foreach ($orders as $order) {
                        $detail_records = OrderDetail::where('order_id', $order->id)->get();
                        if (!$detail_records->isEmpty()) {
                            foreach ($detail_records as $detail_record) {
                                $detail_record->delete();
                            }
                        }
                        $order->delete();
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            // Get Total Records
            $iTotalRecords = Order::getTotalRecords($request, Auth::User()->account_id, $apply_filter);

            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $orders = Order::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);
            $centres = Locations::getAllRecordsDictionary(Auth::user()->account_id, 'custom', 'id', 'desc', ACL::getUserCentres());
            $users = User::getAllRecords(Auth::User()->account_id)->getDictionary();
            $products = Product::getAllRecordsDictionary(Auth::User()->account_id);

            $orders = collect($orders)->map(function ($order) use ($centres) {
                $order->order_have = ($order->location_id != null && array_key_exists($order->location_id, $centres))? $centres[$order->location_id]->name: 'N/A';

                $order->status = $order->status == 1 ? 'completed' : 'pending';
                return $order;
            });

            $records['data'] = $orders;
            $records['permissions'] = [
                'manage' => Gate::allows('order_manage'),
                'edit' => Gate::allows('order_edit'),
                'refund' => Gate::allows('order_refund_manage'),
                'delete' => Gate::allows('order_destroy'),
            ];
            $records['active_filters'] = $filters;
            $records['filter_values'] = [
                'centres' => collect($centres)->pluck('name', 'id'),
                'users' => $users,
                'products' => $products,
            ];
            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];

            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Display a listing of refund orders.
     *
     * @return \Illuminate\Http\Response
     */
    public function refund()
    {
        if (!Gate::allows('inventory_refund_manage')) {
            return abort(401);
        }

        return view('admin.order_refunds.index');
    }

    /**
     * Display a listing of refund orders
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refunddatatable(Request $request)
    {
        try {
            $records = [];
            $records['data'] = [];
            $filename = 'transfer_product';
            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, $filename);

            if (isset($apply_filter['delete'])) {
                $ids = explode(',', $apply_filter['delete']);
                $orders = OrderRefund::getBulkData($ids);
                if ($orders) {
                    foreach ($orders as $order) {
                        $detail_records = OrderRefundDetail::where('order_refund_id', $order->id)->get();
                        if (!$detail_records->isEmpty()) {
                            foreach ($detail_records as $detail_record) {
                                $detail_record->delete();
                            }
                        }
                        $order->delete();
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            // Get Total Records
            $iTotalRecords = OrderRefund::getTotalRecords($request, Auth::User()->account_id, $apply_filter, 'refund');
            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $orders = OrderRefund::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter, 'refund');

            $centres = Locations::getAllRecordsDictionary(Auth::user()->account_id, 'custom', 'id', 'desc', ACL::getUserCentres());
            $warehouse = Warehouse::getAllRecordsDictionary(Auth::user()->account_id, ACL::getUserWarehouse());
            $users = User::getAllRecords(Auth::User()->account_id)->getDictionary();
            $products = Product::getAllRecordsDictionary(Auth::User()->account_id);

            $orders = collect($orders)->map(function ($order) use ($warehouse, $centres) {
                $order->order_have = ($order->location_id != null) ? ((array_key_exists($order->location_id, $centres)) ? $centres[$order->location_id]->name : 'N/A') : ((array_key_exists($order->warehouse_id, $warehouse)) ? $warehouse[$order->warehouse_id]->name : 'N/A');
                return $order;
            });

            $records['data'] = $orders;
            $records['active_filters'] = $filters;
            $records['permissions'] = [
                'manage' => Gate::allows('order_manage'),
                'create' => Gate::allows('order_create'),
                'refund' => Gate::allows('inventory_refund'),
            ];
            $records['filter_values'] = [
                'centres' => collect($centres)->pluck('name', 'id'),
                'warehouse' => collect($warehouse)->pluck('name', 'id'),
                'users' => $users,
                'products' => $products,
            ];
            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];

            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /*
     * Function get the variable to search in database to get the products
     *
     * */
    public function getProducts(Request $request)
    {
        $products = Product::getProductsAjax($request->q, Auth::User()->account_id);
        foreach ($products as $product) {
            $product->quantity = Stock::sumProductQuantity($product->id);
        }

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'products' => $products,
        ]);
    }

    /*
     * Function get the variable to search in database to get the discounts
     *
     * */
    public function getDiscounts(Request $request)
    {
        $discounts = Discounts::where('active', 1)->where('discount_type', 'Treatment')->get(['id', 'name', 'amount']);

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'discounts' => $discounts,
        ]);
    }

    /**
     * Store a newly created orders in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {


        try {
            if (!Gate::allows('order_create')) {
                return abort(401);
            }
            if ($request->payment_mode == null) {
                return ApiHelper::apiResponse($this->error, 'Payment method is required.', false);
            }
            if (!isset($request->product_id)) {
                return ApiHelper::apiResponse($this->error, 'Please select any product', false);
            } else {
                // Use inventory_id if provided (FIFO), otherwise fall back to product_id
                $inventory_ids = $request['inventory_id'] ?? [];
                $products = array_combine($request['product_id'], $request['quantity']);

                foreach ($request['product_id'] as $index => $product_id) {
                    $quantity = $request['quantity'][$index];
                    $inventory_id = $inventory_ids[$index] ?? null;
                    
                    if ($quantity <= 0) {
                        return ApiHelper::apiResponse($this->error, 'Quantity must be greater than 0', false);
                    }
                    
                    // Calculate available using same logic as stock report:
                    // Available = Total stock additions (IN) - Total sales
                    $totalAdditions = \DB::table('stocks')
                        ->where('product_id', $product_id)
                        ->where('location_id', $request->location_id)
                        ->where('stock_type', 'in')
                        ->sum('quantity');

                    $totalSales = \DB::table('order_details')
                        ->join('orders', 'orders.id', '=', 'order_details.order_id')
                        ->where('order_details.product_id', $product_id)
                        ->where('orders.location_id', $request->location_id)
                        ->sum('order_details.quantity');

                    $availableQuantity = $totalAdditions - $totalSales;

                    $product = Product::find($product_id);
                    $product_name = $product ? $product->name : 'Product';

                    if ($availableQuantity < $quantity) {
                        return ApiHelper::apiResponse($this->error, $product_name . ' quantity is out of stock (Available: ' . $availableQuantity . ')', false);
                    }
                }
            }
            if($request->sold_to=="patient" && $request->doctor_id ==null){
                return ApiHelper::apiResponse($this->error, 'prescribed by field is required', false);
            }
            $order = Order::createRecord($request, Auth::User()->account_id,$products);
            if ($order) {
                if($request->patient_id){
                    $userPhone = User::whereId($request->patient_id)->first();
                    $this->PlanCashReceived_SMS($userPhone->phone,$request->grand_total,$order);
                }elseif($request->employee_id){
                    $userPhone = User::whereId($request->employee_id)->first();
                    $this->PlanCashReceived_SMS($userPhone->phone,$request->grand_total,$order);
                }else{
                    $userPhone =$request->phone;
                    $this->PlanCashReceived_SMS($userPhone ,$request->grand_total,$order);
                }


                if (OrderDetail::createRecord($request, Auth::User()->account_id, $order->id)) {

                    return ApiHelper::apiResponse($this->success, 'Record has been created successfully.', true, $order->id);
                }
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }public static function PlanCashReceived_SMS($phone,$total,$order)
    {
        // SEND SMS for Appointment Booked
        $SMSTemplate = SMSTemplates::getBySlug('plan-cash', Auth::User()->account_id);

        if (! $SMSTemplate) {
            // SMS Promotion is disabled
            return [
                'status' => true,
                'sms_data' => 'Plan Cash Amount SMS is disabled',
                'error_msg' => '',
            ];
        }



        $preparedText = self::prepareSMSContent($SMSTemplate->content,$phone,$total,$order);

        $setting = Settings::whereSlug('sys-current-sms-operator')->first();

        $UserOperatorSettings = UserOperatorSettings::getRecord(Auth::User()->account_id, $setting->data);

        if ($setting->data == 1) {

            $SMSObj = [
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($phone)),
                'text' => $preparedText,
                'mask' => $UserOperatorSettings->mask, // Setting ID 3 for Mask
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            ];
            $response = TelenorSMSAPI::SendSMS($SMSObj);
        } else {
            $SMSObj = [
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'from' => $UserOperatorSettings->mask,
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($phone)),
                'text' => $preparedText,
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            ];
            $response = JazzSMSAPI::SendSMS($SMSObj);
        }
        $SMSLog = array_merge($SMSObj, $response);
       $SMSLog['log_type'] = 'inventory';
        $SMSLog['created_by'] = Auth::user()->id;
        if ($setting->data == 2) {
            $SMSLog['mask'] = $SMSObj['from'];
        }
        SMSLogs::create($SMSLog);


        // SEND SMS for Appointment Booked End
        return $response;
    }
    public static function prepareSMSContent($smsContent,$phone,$total,$order)
    {
        if (! $phone) {
            return $smsContent;
        } else {
            if ($phone) {
                $patient = User::where('phone',$phone)->first();
                $smsContent = str_replace('##patient_name##', $patient->name, $smsContent);
                $smsContent = str_replace('##cash_amount##', number_format($total), $smsContent);

                $smsContent = str_replace('##created_at##', Carbon::parse($order->created_at)->toFormattedDateString(), $smsContent);
                $smsContent = str_replace('##id##', $order->id, $smsContent);
            }

            return $smsContent;
        }
    }
    /**
     * Show the form for editing products.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel($id)
    {
        try {
            $response = Order::CancelRecord($id);

            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Remove products from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $response = Order::DeleteRecord($id);

            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function orderRefundDetail($id)
    {
        try {
            if (!Gate::allows('order_refund_manage')) {
                return abort(401);
            }
            $check_refund = Order::whereId($id)->first();
            if($check_refund->is_refunded==1)
            {
                return ApiHelper::apiResponse($this->success, 'This Order is already refunded!', false);
            }
            $records = [];
            $orders = Order::with('patients', 'orderDetail')->find($id);

            $records['data'] = $orders;

            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function orderRefund($id, Request $request)
    {

        $check_refund = Order::where('id',$id)->first();
        if($check_refund->is_refunded==1){
            return ApiHelper::apiResponse($this->success, 'This Order is already refunded!', false);
        }
        try {
            if (!Gate::allows('order_refund_manage')) {
                return abort(401);
            }
            if(array_sum($request->quantity) == 0){
                return ApiHelper::apiResponse($this->error, 'You do not have refunded any product.', false);
            }
            $order_refund = OrderRefund::refund($id, $request);

            if ($order_refund) {
                OrderRefundDetail::refund($check_refund->location_id,$id, $order_refund->id, $request, Auth::User()->account_id);

                return ApiHelper::apiResponse($this->success, 'Order has been refunded.');
            }

            return ApiHelper::apiResponse($this->error, 'Something went wrong.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function displayInvoiceAppointment($id)
    {
        if (!Gate::allows('order_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $invoice_info = Order::with('orderDetail')->where(['id' => $id])->first();
        $location_key = $invoice_info->location_id != null ? 'location_id' : 'warehouse_id';
        $location_value = $invoice_info->location_id != null ? $invoice_info->location_id : $invoice_info->warehouse_id;
        if ($invoice_info->location_id != null) {
            $location_info = Locations::find($invoice_info->location_id);
        } else {
            $location_info = Warehouse::find($invoice_info->warehouse_id);
        }
        $product_id = $invoice_info->orderDetail->pluck('product_id');

        $products = Product::whereIn('id', $product_id)->get();
        $patient = User::find($invoice_info->patient_id);
        $account = Accounts::find($invoice_info->account_id);
        $company_phone_number = Settings::where('slug', '=', 'sys-headoffice')->first();

        return view('admin.orders.displayInvoice', compact('invoice_info', 'patient', 'products', 'company_phone_number', 'location_info', 'account'));
    }
    public function checkMembership(Request $request)
    {
        $patient_id = $request->input('patient_id');
        if($patient_id){
            // Query for latest active membership (end_date >= now, ordered by end_date desc)
            $membership = DB::table('memberships')
                ->where('patient_id', $patient_id)
                ->where('active', 1)
                ->where('end_date', '>=', now())
                ->orderBy('end_date', 'desc')
                ->first();

            // Check if membership exists and is active
            if ($membership) {
                return response()->json([
                    'has_active_membership' => true,
                    'membership_code' => $membership->code,
                    'membership_status' => 'Active',
                    'membership_start_date' => $membership->start_date,
                    'membership_end_date' => $membership->end_date,
                    'membership_type_id' => $membership->membership_type_id
                ]);
            }else{
                return response()->json([
                    'has_active_membership' => false,
                    'membership_code' => null,
                    'membership_status' => 'Inactive',
                    'membership_start_date' => null,
                    'membership_end_date' => null,
                    'membership_type_id' => null
                ]);
            }
        }else{
            return response()->json([
                'has_active_membership' => false,
                'membership_code' => null,
                'membership_status' => 'Inactive',
                'membership_start_date' => null,
                'membership_end_date' => null,
                'membership_type_id' => null
            ]);
        }
    }
    public function invoicePdf($id, $download = null)
    {
        if (!Gate::allows('order_manage')) {
            return abort(401);
        }
        $invoice_info = Order::with('orderDetail')->where(['id' => $id])->first();

        $location_key = $invoice_info->location_id != null ? 'location_id' : 'warehouse_id';
        $location_value = $invoice_info->location_id != null ? $invoice_info->location_id : $invoice_info->warehouse_id;
        if ($invoice_info->location_id != null) {
            $location_info = Locations::find($invoice_info->location_id);
        } else {
            $location_info = Warehouse::find($invoice_info->warehouse_id);
        }
        $product_id = $invoice_info->orderDetail->pluck('product_id');

        $products = Product::whereIn('id', $product_id)->get();
        $patient = User::find($invoice_info->patient_id);
        $account = Accounts::find($invoice_info->account_id);
        $company_phone_number = Settings::where('slug', '=', 'sys-headoffice')->first();

        $content = view('admin.orders.invoice_pdf', compact('invoice_info', 'patient', 'account', 'products', 'company_phone_number', 'location_info', 'download'))->render();
        $pdf = App::make('dompdf.wrapper');
        $pdf->loadHTML($content);
        if ($download) {
            return $pdf->download('order-invoice-C-' . $invoice_info->patient_id . '.pdf');
        }

        return view('admin.orders.invoice_pdf', compact('invoice_info', 'patient', 'account', 'products', 'company_phone_number', 'location_info', 'download'));
    }
    public function getEmployees(Request $request)
    {
        $checkUsers = UserHasLocations::where('location_id', $request->location_id)
            ->pluck('user_id')
            ->toArray();

        $doctors = DoctorHasLocations::where('is_allocated',1)->where('location_id', $request->location_id)
            ->pluck('user_id')
            ->toArray();

        // Fetch users based on user types and active status
        $users = User::whereIn('user_type_id', [2, 5])
            ->where('active', 1)
            ->whereIn('id', $checkUsers)
            ->pluck('name', 'id')
            ->toArray();

        // Fetch doctor names and IDs
        $doctorUsers = User::whereIn('id', $doctors)
            ->where('active',1)
            ->pluck('name', 'id')
            ->toArray();

        // Merge both arrays while preserving user IDs as keys
        $finalUsers = $users + $doctorUsers;

        return response()->json(['users' => $finalUsers]);
    }
    public function getDoctors(Request $request)
    {
        $doctors = DoctorHasLocations::where('is_allocated',1)->where('location_id', $request->location_id)->pluck('user_id')->toArray();

        // Fetch active doctors as an associative array
        $users = User::whereIn('id', $doctors)
            ->where('active', 1)
            ->pluck('name', 'id') // Preserve user IDs
            ->toArray();

        // Ensure 'from_id' is an array
        $locationIds = is_array($request->location_id) ? $request->location_id : [$request->location_id];

        // Fetch FDM users by getting the user_ids associated with the center (location_id)
        $findFDM = UserHasLocations::whereIn('location_id', $locationIds)->pluck('user_id')->toArray();

        // Fetch the 'FDM' role and get its user ids
        $findRole = DB::table('roles')->where('name', 'FDM')->first();
        $roleId = $findRole->id;

        // Get users who have the FDM role
        $roleHasUser = RoleHasUsers::where('role_id', $roleId)->pluck('user_id')->toArray();

        // Get the intersection of users who are both FDM and belong to the center
        $fdmUsers = array_intersect($findFDM, $roleHasUser);

        // Fetch FDM user details (id and name) from the users table
        $FDMUsers = User::whereIn('id', $fdmUsers)
            ->pluck('name', 'id') // Preserve user IDs
            ->toArray();

        // Merge the arrays while preserving keys
        $combinedUsers = $users + $FDMUsers;

        return response()->json(['users'=>$combinedUsers]);
    }
    public function getDoctorsForSales(Request $request)
    {
        $doctors = DoctorHasLocations::where('location_id', $request->location_id)->pluck('user_id')->toArray();

        // Fetch active doctors as an associative array
        $users = User::whereIn('id', $doctors)
            ->where('active', 1)
            ->pluck('name', 'id') // Preserve user IDs
            ->toArray();

        // Ensure 'from_id' is an array
        $locationIds = is_array($request->location_id) ? $request->location_id : [$request->location_id];

        // Fetch FDM users by getting the user_ids associated with the center (location_id)
        $findFDM = UserHasLocations::whereIn('location_id', $locationIds)->pluck('user_id')->toArray();

        // Fetch the 'FDM' role and get its user ids
        $findRole = DB::table('roles')->where('name', 'FDM')->first();
        $roleId = $findRole->id;

        // Get users who have the FDM role
        $roleHasUser = RoleHasUsers::where('role_id', $roleId)->pluck('user_id')->toArray();

        // Get the intersection of users who are both FDM and belong to the center
        $fdmUsers = array_intersect($findFDM, $roleHasUser);

        // Fetch FDM user details (id and name) from the users table
        $FDMUsers = User::whereIn('id', $fdmUsers)
            ->pluck('name', 'id') // Preserve user IDs
            ->toArray();

        // Merge the arrays while preserving keys
        $combinedUsers = $users + $FDMUsers;

        return response()->json(['users'=>$combinedUsers]);
    }
    public function getCentreDoctors(Request $request)
    {
        $doctors = DoctorHasLocations::where('is_allocated',1)->where('location_id', $request->location_id)->pluck('user_id')->toArray();

        // Fetch active doctors as an associative array
        $users = User::whereIn('id', $doctors)
            ->where('active', 1)
            ->pluck('name', 'id') // Preserve user IDs
            ->toArray();

        // Ensure 'from_id' is an array
        $locationIds = is_array($request->location_id) ? $request->location_id : [$request->location_id];


        // Merge the arrays while preserving keys
        $combinedUsers = $users;

        return response()->json(['users'=>$combinedUsers]);
    }
}
