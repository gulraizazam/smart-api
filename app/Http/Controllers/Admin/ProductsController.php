<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ACL;
use App\Models\Brand;
use App\Models\Stock;
use App\Models\Product;
use App\Models\Locations;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use App\Models\ProductDetail;
use App\HelperModule\ApiHelper;
use App\Models\TransferProduct;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Validator;

class ProductsController extends Controller
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
        if (!Gate::allows('product_manage')) {
            return abort(401);
        }

        return view('admin.products.index');
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
            $filename = 'product';
            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, $filename);

            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $apply_filter['delete']);
                $products = Product::getBulkData($ids);
                if ($products) {
                    foreach ($products as $product) {
                        $detail_records = ProductDetail::where('product_id', $product->id)->get();
                        if (!$detail_records->isEmpty()) {
                            foreach ($detail_records as $detail_record) {
                                $detail_record->delete();
                            }
                        }
                        $product->delete();
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }
            // Get Total Records
            $iTotalRecords = Product::getTotalRecords($request, Auth::User()->account_id, $apply_filter);
            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $products = Product::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);
            $brands = Brand::getAllRecordsDictionary(Auth::User()->account_id);
            $centres = Locations::getAllRecordsDictionary(Auth::user()->account_id, 'custom', 'id', 'desc', ACL::getUserCentres());
            $warehouse = Warehouse::getAllRecordsDictionary(Auth::user()->account_id);

            if ($products) {
                $products = collect($products)->map(function ($product) use ($brands, $centres, $warehouse) {
                    $product->quantity = Stock::sumProductQuantity($product->id);
                    $product->brand_id = (array_key_exists($product->brand_id, $brands)) ? $brands[$product->brand_id]->name : 'N/A';
                    $product->sale_price = $product->sale_price ?? 'N/A';
                    $product->product_type = ucwords(str_replace("_", " ", $product->product_type));
                    $product->stock_have = ($product->location_id != null) ? ((array_key_exists($product->location_id, $centres)) ? $centres[$product->location_id]->name : 'N/A') : ((array_key_exists($product->warehouse_id, $warehouse)) ? $warehouse[$product->warehouse_id]->name : 'N/A');
                    return $product;
                });
            }
            $records['data'] = $products;
            $records['permissions'] = [
                'active' => Gate::allows('product_active'),
                'edit' => Gate::allows('product_edit'),
                'manage' => Gate::allows('product_manage'),
                'delete' => Gate::allows('product_destroy'),
                'create' => Gate::allows('product_create'),
                'sale_price' => Gate::allows('product_sale_price'),
                'add_stock' => Gate::allows('product_add_stock'),
                'stock_detail' => Gate::allows('product_stock_detail'),
                'transfer_product' => Gate::allows('product_transfer'),
                'log' => Gate::allows('product_log'),
            ];
            $records['active_filters'] = $filters;
            $records['filter_values'] = [
                'brands' => collect($brands)->pluck('name', 'id'),
                'centres' => collect($centres)->pluck('name', 'id'),
                'warehouse' => collect($warehouse)->pluck('name', 'id'),
                'status' => config('constants.status'),
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

    public function create()
    {
        try {
            if (!Gate::allows('product_create')) {
                return abort(401);
            }

            $centres = Locations::whereIn('id', ACL::getUserCentres())->pluck('name', 'id');
            $warehouse = Warehouse::whereActive(1)->pluck('name', 'id');
            $brands = Brand::whereStatus(1)->pluck('name', 'id');

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'centres' => $centres,
                'warehouse' => $warehouse,
                'brands' => $brands,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
    /**
     * Store a newly created Product in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            if (!Gate::allows('product_create')) {
                return abort(401);
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            $product = Product::createRecord($request, Auth::User()->account_id);
            if ($product) {
                if (ProductDetail::createRecord($request, Auth::User()->account_id, $product->id)) {
                    return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
                }
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Validate form fields
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function verifyFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
            'brand_id' => 'required',
            'purchase_price' => 'required',
        ]);
    }

    /**
     * Show the form for editing products.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        try {
            if (!Gate::allows('product_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $product = Product::getData($id);
            $product_detail = ProductDetail::getProductDetailData($product->id);
            $data['product'] = $product;
            $data['product_detail'] = $product_detail;
            if (!$product) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }

            return ApiHelper::apiResponse($this->success, 'Success', true, $data);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update products in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id, $detail)
    {
        try {
            if (!Gate::allows('product_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            $product = Product::updateRecord($id, $request, Auth::User()->account_id);
            if ($product) {
                if (ProductDetail::updateRecord($detail, $request, Auth::User()->account_id, $id)) {
                    return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
                }
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update products in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSalePrice(Request $request, $id)
    {
        try {
            if (!Gate::allows('product_sale_price')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $product = Product::updateRecord($id, $request, Auth::User()->account_id);
            if ($product) {
                return ApiHelper::apiResponse($this->success, 'Record has been update successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
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
            if (!Gate::allows('product_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $response = Product::DeleteRecord($id);

            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Show the form for editing products.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function editSalePrice($id)
    {
        try {
            $product = Product::getData($id);
            if (!$product) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }

            return ApiHelper::apiResponse($this->success, 'Success', true, $product);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update products in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function addStock(Request $request, $id)
    {
        try {
            if (!Gate::allows('product_add_stock')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            if (ProductDetail::createRecord($request, Auth::User()->account_id, $id)) {
                return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function productStock($id)
    {
        if (!Gate::allows('product_stock_detail')) {
            return abort(401);
        }
        return view('admin.products.stock_detail', compact('id'));
    }

    public function productStockDetail(Request $request, $id)
    {
        if (!Gate::allows('product_stock_detail')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $iTotalRecords = Stock::getTotalRecords($request, Auth::User()->account_id, $id);
        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);
        $stock_data = Stock::with('product')->where('product_id', $id)->get();

        $records['data'] = $stock_data;
        $records['meta'] = [
            'field' => $orderBy,
            'page' => $page,
            'pages' => $pages,
            'perpage' => $iDisplayLength,
            'total' => $iTotalRecords,
            'sort' => $order,
        ];

        return ApiHelper::apiDataTable($records);
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function status(Request $request)
    {
        if (!Gate::allows('product_active')) {
            return abort(401);
        }

        $response = Product::activeRecord($request->id, $request->status);
        if ($response) {
            return ApiHelper::apiResponse($this->success, 'Status has been changed successfully.');
        }
        return ApiHelper::apiResponse($this->success, 'Product not found.', false);
    }

    public function transferProductGetData($id)
    {
        try {
            if (!Gate::allows('product_transfer')) {
                return abort(401);
            }
            $product = Product::findOrFail($id);
            if ($product) {
                $product->quantity = Stock::sumProductQuantity($id);
            }
            $centres = Locations::whereIn('id', ACL::getUserCentres())->pluck('name', 'id');
            $warehouse = Warehouse::whereActive(1)->pluck('name', 'id');

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'product' => $product,
                'centres' => $centres,
                'warehouse' => $warehouse
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function transferProduct(Request $request)
    {
        try {
            if (!Gate::allows('product_transfer')) {
                return abort(401);
            }
            $transfer_product = TransferProduct::createRecord($request, Auth::User()->account_id);
            if ($transfer_product['record']) {
                $product_detail = ProductDetail::createRecordTransferProduct($transfer_product['data'], Auth::User()->account_id, $transfer_product['data']['id']);
                if ($product_detail) {
                    TransferProduct::where(['id' => $transfer_product['record']->id])->update(['product_detail_id' => $product_detail->id]);
                    return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
                }
            }
            $message = ($transfer_product['message'] != null) ? $transfer_product['message'] : 'Something went wrong, please try again later.';
            return ApiHelper::apiResponse($this->success, $message, false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function logs($id)
    {
        try {
            if (!Gate::allows('product_log')) {
                return abort(401);
            }
            $products_logs = Activity::where(['log_name' => 'product', 'subject_id' => $id])->orderBy('id', 'DESC')->get();
           
            $users = User::where(['account_id' => Auth::User()->account_id])->get()->getDictionary();
            dd($products_logs);
            $brands = Brand::getAllRecordsDictionary(Auth::User()->account_id);
            $centres = Locations::getAllRecordsDictionary(Auth::user()->account_id, 'custom', 'id', 'desc', ACL::getUserCentres());
            $warehouse = Warehouse::getAllRecordsDictionary(Auth::user()->account_id);
           
            $products_logs = collect($products_logs)->map(function ($log) use ($users, $brands, $centres, $warehouse) {
                $properties = json_decode($log->properties)->attributes;
               
                $log->product_name = $properties->name;
                $log->brand_id = (array_key_exists($properties->brand_id, $brands)) ? $brands[$properties->brand_id]->name : 'N/A';
                $log->location = (array_key_exists($properties->location_id, $centres)) ? $centres[$properties->location_id]->name : 'N/A';
                $log->warehouse = (array_key_exists($properties->warehouse_id, $warehouse)) ? $warehouse[$properties->warehouse_id]->name : 'N/A';
                $log->created_by = (array_key_exists($properties->created_by, $users)) ? $users[$properties->created_by]->name : 'N/A';
                $log->updated_by = (array_key_exists($properties->updated_by, $users)) ? $users[$properties->updated_by]->name : 'N/A';
               
                return $log;
            });
            return view('admin.products.logs', compact('products_logs'));
        } catch (\Exception $e) {
            return view('admin.products.logs', compact('products_logs'));
        }
    }
}
