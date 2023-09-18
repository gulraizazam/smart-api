<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ACL;
use App\Models\User;
use App\Models\Brand;
use App\Models\Stock;
use App\Models\Product;
use App\Models\Locations;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use App\Models\ProductDetail;
use App\HelperModule\ApiHelper;
use App\Models\TransferProduct;
use App\Helpers\GeneralFunctions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Facades\LogBatch;
use Illuminate\Support\Facades\Validator;
use PhpParser\Node\Stmt\Break_;

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

            if (isset($filters['delete'])) {
                $ids = explode(',', $filters['delete']);
                $products = Product::getBulkData($ids);
                $is_child = false;
                if ($products) {
                    foreach ($products as $product) {
                        if (!Product::isChildExists($product->id, Auth::User()->account_id)) {
                            ProductDetail::where(['product_id' => $product->id])->delete();
                            Stock::where(['product_id' => $product->id])->delete();
                            $product->delete();
                            $is_child = true;
                        }
                    }
                }
                if (!$is_child) {
                    $records['status'] = false;
                    $records['message'] = 'Child records exist, unable to delete resource!';
                } else {
                    $records['status'] = true;
                    $records['message'] = 'Records has been deleted successfully!';
                }
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
            LogBatch::startBatch();
            $request['type'] = 'product_create';
            $request['message'] = 'Product create';

            $product = Product::createRecord($request, Auth::User()->account_id);
            if ($product) {
                if (ProductDetail::createRecord($request, Auth::User()->account_id, $product->id)) {
                    LogBatch::endBatch();
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
            $data['quantity'] = GeneralFunctions::stockCheck($id);
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
            LogBatch::startBatch();
            $request['type'] = 'product_update';
            $request['message'] = 'Product update';

            $product = Product::updateRecord($id, $request, Auth::User()->account_id);
            if ($product) {
                if (ProductDetail::updateRecord($detail, $request, Auth::User()->account_id, $id)) {
                    LogBatch::endBatch();
                    return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
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
            $product = Product::getData($id);;
            if ($product->product_type == 'in_house_use') {
                return ApiHelper::apiResponse($this->success, 'This product for in house use. so do not add sale price!', false);
            }
            LogBatch::startBatch();
            $request['type'] = 'product_sale_price_update';
            $request['message'] = 'Product sale price update';

            $product = Product::updateRecord($id, $request, Auth::User()->account_id);
            LogBatch::endBatch();
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
            LogBatch::startBatch();
            $data['type'] = 'product_delete';
            $data['message'] = 'Product delete';
            $response = Product::DeleteRecord($id, $data);

            LogBatch::endBatch();

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
            LogBatch::startBatch();
            $request['type'] = 'stock_add';
            $request['message'] = 'Stock add';

            if (ProductDetail::createRecord($request, Auth::User()->account_id, $id)) {
                LogBatch::endBatch();
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
            LogBatch::startBatch();
            $request['type'] = 'product_transfer_create';
            $request['message'] = 'Product transfer';

            $transfer_product = TransferProduct::createRecord($request, Auth::User()->account_id);
            if ($transfer_product['record']) {
                $product_detail = ProductDetail::createRecordTransferProduct($transfer_product['data'], Auth::User()->account_id, $transfer_product['data']['id']);
                if ($product_detail) {
                    TransferProduct::where(['id' => $transfer_product['record']->id])->update(['product_detail_id' => $product_detail->id]);
                    LogBatch::endBatch();
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
            $products_logs = Activity::where(['subject_id' => $id])
                ->orWhere(['properties->attributes->product_id' => $id])
                ->orWhere(['properties->attributes->child_product_id' => $id])
                ->get()->toArray();

            $ids = [];
            $data = [];
            $data2 = [];
            $batch_uuid = [];

            $productsCount = sizeof($products_logs);

            for ($i = 0; $i < $productsCount; $i++) {
                $foundInInnerLoop = false; // Flag to check if a match is found in the inner loop
                for ($j = $i + 1; $j < $productsCount; $j++) {
                    if (isset($products_logs[$i]['batch_uuid']) && isset($products_logs[$j]['batch_uuid'])) {
                        if ($products_logs[$i]['batch_uuid'] == $products_logs[$j]['batch_uuid']) {
                            if (!in_array($products_logs[$i]['id'], $ids) && !in_array($products_logs[$j]['id'], $ids)) {
                                $data2[] = [$products_logs[$i], $products_logs[$j]];
                                $ids[] = $products_logs[$i]['id'];
                                $ids[] = $products_logs[$j]['id'];
                                $batch_uuid[] = $products_logs[$i]['batch_uuid'];
                            }
                            $foundInInnerLoop = true; // Mark that a match is found in the inner loop
                        }
                    }
                }

                // If no match is found in the inner loop, include only the outer loop element
                if (!$foundInInnerLoop && !in_array($products_logs[$i]['id'], $ids) && !in_array($products_logs[$i]['batch_uuid'], $batch_uuid)) {
                    $data[] = $products_logs[$i];
                    $ids[] = $products_logs[$i]['id'];
                }
            }

            $users = User::getAllRecords(Auth::User()->account_id)->getDictionary();
            $brands = Brand::getAllRecordsDictionary(Auth::User()->account_id);
            $centres = Locations::getAllRecordsDictionary(Auth::user()->account_id, 'custom', 'id', 'desc', ACL::getUserCentres());
            $warehouse = Warehouse::getAllRecordsDictionary(Auth::user()->account_id);
            $products = Product::getAllRecordsDictionary(Auth::user()->account_id);

            $records = [];
            $records2 = [];
            foreach ($data as $key => $log) {
                $data = [];
                $data['log_name'] = $log['log_name'];
                $data['event'] = $log['event'];
                $data['subject_id'] = $log['subject_id'];
                $data['causer_id'] = $log['causer_id'];
                $data['batch_uuid'] = $log['batch_uuid'];
                $data['properties']['attributes'] = $log['properties']['attributes'];
                $data['created_at'] = $log['created_at'];
                $data['updated_at'] = $log['updated_at'];
                $records[] = $data;
            }
            foreach ($data2 as $key => $log) {
                $data = [];
                $data['log_name'] = $log[0]['log_name'];
                $data['event'] = $log[0]['event'];
                $data['subject_id'] = $log[0]['subject_id'];
                $data['causer_id'] = $log[0]['causer_id'];
                $data['batch_uuid'] = $log[0]['batch_uuid'];
                $data['properties']['attributes'] = array_merge($log[0]['properties']['attributes'], $log[1]['properties']['attributes']);
                unset($data['properties']['attributes']['id']);
                $data['created_at'] = $log[0]['created_at'];
                $data['updated_at'] = $log[0]['updated_at'];
                $records2[] = $data;
            }

            $final_records = array_merge($records, $records2);
            $records = collect($final_records)->map(function ($log) use ($id, $users, $brands, $centres, $warehouse, $products) {
                $properties = null;
                if (isset($log['properties']['attributes'])) {
                    $properties = $log['properties']['attributes'];
                }

                $brand_id = isset($properties['brand_id']) ? $properties['brand_id'] : null;
                $location_id = isset($properties['location_id']) ? $properties['location_id'] : null;
                $warehouse_id = isset($properties['warehouse_id']) ? $properties['warehouse_id'] : null;
                $created_by = isset($properties['created_by']) ? $properties['created_by'] : null;
                $updated_by = isset($properties['updated_by']) ? $properties['updated_by'] : null;
                $to_location_id = isset($properties['to_location_id']) ? $properties['to_location_id'] : null;
                $to_warehouse_id = isset($properties['to_warehouse_id']) ? $properties['to_warehouse_id'] : null;
                $from_location_id = isset($properties['from_location_id']) ? $properties['from_location_id'] : null;
                $from_warehouse_id = isset($properties['from_warehouse_id']) ? $properties['from_warehouse_id'] : null;
                $child_product_id = isset($properties['child_product_id']) ? $properties['child_product_id'] : null;
                $product_name = isset($properties['name']) ? $properties['name'] : (isset($properties['product_id']) ? $properties['product_id'] : null);
                $causer_id = (array_key_exists($log['causer_id'], $users)) ? $users[$log['causer_id']]->name : 'N/A';

                $log['product_id'] = $id;
                $log['product_name'] = (array_key_exists($product_name, $products)) ? $products[$properties['product_id']]->name : $product_name;

                $log['brand_id'] = (array_key_exists($brand_id, $brands)) ? $brands[$brand_id]->name : 'N/A';
                $log['location'] = (array_key_exists($location_id, $centres)) ? $centres[$location_id]->name : 'N/A';
                $log['warehouse'] = (array_key_exists($warehouse_id, $warehouse)) ? $warehouse[$warehouse_id]->name : 'N/A';
                $log['to_location'] = (array_key_exists($to_location_id, $centres)) ? $centres[$to_location_id]->name : 'N/A';
                $log['to_warehouse'] = (array_key_exists($to_warehouse_id, $warehouse)) ? $warehouse[$to_warehouse_id]->name : 'N/A';
                $log['from_location'] = (array_key_exists($from_location_id, $centres)) ? $centres[$from_location_id]->name : 'N/A';
                $log['from_warehouse'] = (array_key_exists($from_warehouse_id, $warehouse)) ? $warehouse[$from_warehouse_id]->name : 'N/A';
                $log['child_product'] = (array_key_exists($child_product_id, $products)) ? $products[$child_product_id]->name : 'N/A';
                $log['created_by'] = (array_key_exists($created_by, $users)) ? $users[$created_by]->name : $causer_id;
                $log['updated_by'] = (array_key_exists($updated_by, $users)) ? $users[$updated_by]->name : $causer_id;

                return $log;
            })->sortByDesc('created_at');

            return view('admin.products.logs', compact('records'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
