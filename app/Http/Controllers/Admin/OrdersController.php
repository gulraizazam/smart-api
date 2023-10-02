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
use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
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

            if (!empty($filters) && isset($filters['filter']) == "filter_cancel") {
                if ($filters['location'] == null) {
                    unset($filters['location']);
                }
                if ($filters['product_id'] == null) {
                    unset($filters['product_id']);
                }
            }

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
            $warehouse = Warehouse::getAllRecordsDictionary(Auth::user()->account_id);
            $users = User::getAllRecords(Auth::User()->account_id)->getDictionary();
            $products = Product::getAllRecordsDictionary(Auth::User()->account_id);

            $orders = collect($orders)->map(function ($order) use ($warehouse, $centres) {
                $order->order_have = ($order->location_id != null) ? ((array_key_exists($order->location_id, $centres)) ? $centres[$order->location_id]->name : 'N/A') : ((array_key_exists($order->warehouse_id, $warehouse)) ? $warehouse[$order->warehouse_id]->name : 'N/A');
                $order->status = $order->status == 1 ? 'completed' : 'pending';
                return $order;
            });

            $records['data'] = $orders;
            $records['permissions'] = [
                'manage' => Gate::allows('order_manage'),
                'edit' => Gate::allows('order_edit'),
                'refund' => Gate::allows('refund_manage'),
                'delete' => Gate::allows('order_destroy'),
            ];
            $records['active_filters'] = $filters;
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
            $iTotalRecords = Order::getTotalRecords($request, Auth::User()->account_id, $apply_filter, 'refund');
            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $orders = Order::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter, 'refund');
            $centres = Locations::getAllRecordsDictionary(Auth::user()->account_id, 'custom', 'id', 'desc', ACL::getUserCentres());
            $warehouse = Warehouse::getAllRecordsDictionary(Auth::user()->account_id);
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
            $stock_check = GeneralFunctions::stockC($request->product_id);
            if ($stock_check < $request->quantity) {
                return ApiHelper::apiResponse($this->error, 'Product quantity out of stock.', false);
            }
            if ($request->quantity <= 0) {
                return ApiHelper::apiResponse($this->error, "Product quantity can't be 0.", false);
            }
            $order = Order::createRecord($request, Auth::User()->account_id);
            if ($order) {
                if (OrderDetail::createRecord($request, Auth::User()->account_id, $order->id)) {
                    OrderDetail::where('order_id', $order->id)->sum('sale_price_after_discount');

                    return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
                }
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
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

    public function edit($id)
    {
        try {
            if (!Gate::allows('order_edit')) {
                return abort(401);
            }
            $response = Order::getRecord($id);
            if ($response->location_id != null) {
                $from_id = $response->location_id;
                $from_key = 'location_id';
            } elseif ($response->warehouse_id != null) {
                $from_id = $response->warehouse_id;
                $from_key = 'warehouse_id';
            } else {
                $from_id = '';
                $from_key = '';
            }

            $data = [];
            $data['request_from'] = 'order';
            $data['from_id'] = $from_id;
            $data['from_key'] = $from_key;

            $products = Product::getProductsAjax($data, Auth::User()->account_id);
            foreach ($products as $product) {
                $product->quantity = Stock::sumProductQuantity($product->id);
            }

            return ApiHelper::apiResponse($this->success, 'Get Record', true, [
                'response' => $response,
                'products' => $products,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $stock_check = GeneralFunctions::stockC($request->product_id);
            if ($stock_check < $request->quantity) {
                return ApiHelper::apiResponse($this->error, 'Product quantity out of stock.', false);
            }
            if ($request->quantity <= 0) {
                return ApiHelper::apiResponse($this->error, "Product quantity can't be 0.", false);
            }
            $order = Order::updateRecord($request, Auth::user()->account_id, $id);
            if ($order) {
                if (OrderDetail::updateRecord($order->id, $request, Auth::User()->account_id)) {
                    $total_price = OrderDetail::priceCalculate($request);
                    $order = Order::where(['id' => $order->id])->update([
                        'total_price' => $total_price
                    ]);
                    return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
                }
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function orderRefundDetail($id)
    {
        try {
            if (!Gate::allows('refund_manage')) {
                return abort(401);
            }
            $response = Order::getRecord($id);
            if ($response->location_id != null) {
                $from_id = $response->location_id;
                $from_key = 'location_id';
            } elseif ($response->warehouse_id != null) {
                $from_id = $response->warehouse_id;
                $from_key = 'warehouse_id';
            } else {
                $from_id = '';
                $from_key = '';
            }

            $data = [];
            $data['request_from'] = 'order';
            $data['from_id'] = $from_id;
            $data['from_key'] = $from_key;

            $records = [];
            $orders = Order::with('patients', 'orders.product')->find($id);

            $records['data'] = $orders;

            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function orderRefund($id)
    {
        try {
            if (!Gate::allows('refund_manage')) {
                return abort(401);
            }
            $order_refund = Order::refund($id);
            if ($order_refund) {
                $order_detail_refund = OrderDetail::refund($id, $order_refund->id);
            }

            return ApiHelper::apiResponse($this->success, 'Order has been refunded.');
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

        $product = Product::find($invoice_info->orderDetail->product_id);
        $patient = User::find($invoice_info->patient_id);
        $account = Accounts::find($invoice_info->account_id);
        $company_phone_number = Settings::where('slug', '=', 'sys-headoffice')->first();

        return view('admin.orders.displayInvoice', compact('invoice_info', 'patient', 'product', 'company_phone_number', 'location_info', 'account'));
    }

    public function invoicePdf($id, $download = null)
    {
        if (!Gate::allows('order_manage')) {
            return abort(401);
        }
        $invoice_info = Order::with('orderDetail')->where(['id' => $id])->first();
        Order::where(['id' => $id])->update(['status' => 1]);

        $location_key = $invoice_info->location_id != null ? 'location_id' : 'warehouse_id';
        $location_value = $invoice_info->location_id != null ? $invoice_info->location_id : $invoice_info->warehouse_id;
        if ($invoice_info->location_id != null) {
            $location_info = Locations::find($invoice_info->location_id);
        } else {
            $location_info = Warehouse::find($invoice_info->warehouse_id);
        }

        $product = Product::find($invoice_info->orderDetail->product_id);
        $patient = User::find($invoice_info->patient_id);
        $account = Accounts::find($invoice_info->account_id);
        $company_phone_number = Settings::where('slug', '=', 'sys-headoffice')->first();

        $content = view('admin.orders.invoice_pdf', compact('invoice_info', 'patient', 'account', 'product', 'company_phone_number', 'location_info', 'download'))->render();
        $pdf = App::make('dompdf.wrapper');
        $pdf->loadHTML($content);
        if ($download) {
            return $pdf->download('order-invoice-C-' . $invoice_info->patient_id . '.pdf');
        }

        return view('admin.orders.invoice_pdf', compact('invoice_info', 'patient', 'account', 'product', 'company_phone_number', 'location_info', 'download'));
    }
}
