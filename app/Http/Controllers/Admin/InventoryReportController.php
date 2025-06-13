<?php

namespace App\Http\Controllers\Admin;

use DateTime;
use App\Helpers\ACL;
use App\Models\Order;
use App\Models\Product;
use App\Models\Locations;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use App\HelperModule\ApiHelper;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class InventoryReportController extends Controller
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

    public function report()
    {
        return view('admin.reports.inventory.index');
    }

    public function reportResult(Request $request)
    {
        try {
            $centres = Locations::getAllRecordsDictionary(Auth::user()->account_id, 'custom', 'id', 'desc', ACL::getUserCentres());
            $warehouse = Warehouse::getAllRecordsDictionary(Auth::user()->account_id);

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'centres' => $centres,
                'warehouse' => $warehouse,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }


    public function stockReport(Request $request)
    {
        try {
            if ($request->report_type == null) {
                return ApiHelper::apiResponse($this->error, 'Please select report type', false);
            }
            if ($request->has('report_type')) {
                if ($request->report_type == 'stock_report') {
                    return $this->stockReportResult($request);
                }
            }
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function stockReportResult($request)
    {
        $where = [];
        if ($request->has('date_range')) {
            $date_range = explode(' - ', $request->date_range);
            $start_date_time = date('Y-m-d H:i:s', strtotime($date_range[0]));
            $end_date_string = new DateTime($date_range[1]);
            $end_date_string->setTime(23, 59, 0);
            $end_date_time = $end_date_string->format('Y-m-d H:i:s');
        } else {
            $start_date_time = null;
            $end_date_time = null;
        }

        if ($request->has("name")) {
            $where[] = ['name', 'like', '%' . $request->name . '%'];
        }
        if ($request->has("location_type") && $request->has("location")) {
            if ($request->location_type == 'branch') {
                $where[][] = ['location_id' => $request->location];
            } elseif ($request->location_type == 'warehouse') {
                $where[][] = ['warehouse_id' => $request->location];
            }
        }
        if ($request->has('date_range')) {
            $where[] = ['created_at', '>=', $start_date_time];
            $where[] = ['created_at', '<=', $end_date_time];
        }

        $products = Product::with('order')->where(function ($query) {
            $query->whereIn('location_id', ACL::getUserCentres())
                ->orWhereIn('warehouse_id', ACL::getUserWarehouse());
        })
            ->withSum('productDetail', 'quantity')
            ->withSum('productDetail', 'total_purchase_price')
            ->withSum('transferProduct', 'quantity')
            ->where($where)
            ->get();
        $centres = Locations::getAllRecordsDictionary(Auth::user()->account_id, 'custom', 'id', 'desc', ACL::getUserCentres());
        $warehouse = Warehouse::getAllRecordsDictionary(Auth::user()->account_id, ACL::getUserWarehouse());

        $products = collect($products)->map(function ($product) use($centres, $warehouse) {
            $product->transfer_product_sum_quantity = $product->transfer_product_sum_quantity == null ? 0 : $product->transfer_product_sum_quantity;
            $product->available_stock = $product->getAvailableStockAttribute();
            $product->order_quantity = $product['order']->filter(function ($order) {
                return $order['order_type'] === 'sale' && $order['refund_order_id'] == null;
            })->sum(function ($order) {
                return $order['orderDetail']['quantity'];
            });
            $product->order_sale_price = $product['order']->filter(function ($order) {
                return $order['order_type'] === 'sale' && $order['refund_order_id'] == null;
            })->sum(function ($order) {
                return $order['orderDetail']['sale_price'];
            });
            $product->location = ($product->location_id != null) ? ((array_key_exists($product->location_id, $centres)) ? $centres[$product->location_id]->name : 'N/A') : ((array_key_exists($product->warehouse_id, $warehouse)) ? $warehouse[$product->warehouse_id]->name : 'N/A');
            return $product;
        });

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'products' => $products,
        ]);
    }
}
