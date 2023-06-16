<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Discounts;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Http\Request;
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
        if (! Gate::allows('order_manage')) {
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

            if (isset($request->input('query')['search'])) {
                $apply_filter = $request->input('query')['search'];
                if (isset($apply_filter['delete'])) {
                    $ids = explode(',', $apply_filter['delete']);
                    $orders = Order::getBulkData($ids);
                    if ($orders) {
                        foreach ($orders as $order) {
                            $detail_records = OrderDetail::where('order_id', $order->id)->get();
                            if (! $detail_records->isEmpty()) {
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
            } else {
                $apply_filter = false;
            }

            // Get Total Records
            $iTotalRecords = Order::getTotalRecords($request, Auth::User()->account_id, $apply_filter);
            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $orders = Order::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);

            //$products = Product::getAllRecordsDictionary(Auth::User()->account_id);

            $records['data'] = $orders;
            $records['permissions'] = [
                'manage' => Gate::allows('product_manage'),
                'refund' => Gate::allows('refund_manage'),
            ];
            $records['active_filters'] = $apply_filter;
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
        if (! Gate::allows('refund_manage')) {
            return abort(401);
        }

        return view('admin.inventory_refunds.index');
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

            if (isset($request->input('query')['search'])) {
                $apply_filter = $request->input('query')['search'];
                if (isset($apply_filter['delete'])) {
                    $ids = explode(',', $apply_filter['delete']);
                    $orders = Order::getBulkData($ids);
                    if ($orders) {
                        foreach ($orders as $order) {
                            $detail_records = OrderDetail::where('order_id', $order->id)->get();
                            if (! $detail_records->isEmpty()) {
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
            } else {
                $apply_filter = false;
            }

            // Get Total Records
            $iTotalRecords = Order::getTotalRecords($request, Auth::User()->account_id, $apply_filter);
            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $orders = Order::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter, 'refund');

            //$products = Product::getAllRecordsDictionary(Auth::User()->account_id);

            $records['data'] = $orders;
            $records['permissions'] = [];
            $records['active_filters'] = $apply_filter;
            // $all_products = array();
            // foreach ($products as $product) {
            //     $all_products[$product->id] = $product->name;
            // }
            // $records['filter_values'] = [
            //     'products' => $all_products,
            //     'status' => config('constants.status')
            // ];
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
     * Display a listing of orders.
     *
     * @return \Illuminate\Http\Response
     */
    public function orderRefund($id)
    {
        if (! Gate::allows('refund_manage')) {
            return abort(401);
        }
        $order_refund = Order::refund($id);
        if ($order_refund) {
            $order_detail_refund = OrderDetail::refund($id, $order_refund->id);
        }

        return ApiHelper::apiResponse($this->success, 'Order has been refunded.');
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
            if (! Gate::allows('order_create')) {
                return abort(401);
            }
            $order = Order::createRecord($request, Auth::User()->account_id);
            if ($order) {
                if (OrderDetail::createRecord($request, Auth::User()->account_id, $order->id)) {
                    $total_price = OrderDetail::where('order_id', $order->id)->sum('sale_price_after_discount');
                    $order->total_price = $total_price;
                    $order->save();

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

    public function orderRefundDetail($id)
    {
        try {
            if (! Gate::allows('refund_manage')) {
                return abort(401);
            }
            $records = [];
            $orders = Order::with('patients', 'orders.product', 'orders.discount')->find($id);
            $records['data'] = $orders;

            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
