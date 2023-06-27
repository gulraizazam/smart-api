<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
        if (! Gate::allows('product_manage')) {
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

            if (isset($request->input('query')['search'])) {
                $apply_filter = $request->input('query')['search'];
                if (isset($apply_filter['delete'])) {
                    $ids = explode(',', $apply_filter['delete']);
                    $products = Product::getBulkData($ids);
                    if ($products) {
                        foreach ($products as $product) {
                            $detail_records = ProductDetail::where('product_id', $product->id)->get();
                            if (! $detail_records->isEmpty()) {
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
            } else {
                $apply_filter = false;
            }

            // Get Total Records
            $iTotalRecords = Product::getTotalRecords($request, Auth::User()->account_id, $apply_filter);
            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $products = Product::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);

            $brands = Brand::getAllRecordsDictionary(Auth::User()->account_id);
            if ($products) {
                foreach ($products as $product) {
                    $product->quantity = Stock::sumProductQuantity($product->id);
                    $product->brand_id = (array_key_exists($product->brand_id, $brands)) ? $brands[$product->brand_id]->name : 'N/A';
                }
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
            ];
            $records['active_filters'] = $apply_filter;
            $all_brands = [];
            foreach ($brands as $brand) {
                $all_brands[$brand->id] = $brand->name;
            }
            $records['filter_values'] = [
                'brands' => $all_brands,
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

    /**
     * Store a newly created Product in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            if (! Gate::allows('product_create')) {
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
            'sale_price' => 'required',
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
            if (! Gate::allows('product_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $product = Product::getData($id);
            $product_detail = ProductDetail::getProductDetailData($product->id);
            $data['product'] = $product;
            $data['product_detail'] = $product_detail;
            if (! $product) {
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
            if (! Gate::allows('product_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            $product = Product::updateRecord($id, $request, Auth::User()->account_id);
            if ($product) {
                if (ProductDetail::updateRecord($detail, $request, Auth::User()->account_id)) {
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
            // if (!Gate::allows('product_sale_price')) {
            //     return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            // }
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
            if (! Gate::allows('product_destroy')) {
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
            if (! $product) {
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

            // if (!Gate::allows('product_add_stock')) {
            //     return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            // }
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
        // if (!Gate::allows('product_stock_detail')) {
        //     return abort(401);
        // }
        return view('admin.products.stock_detail', compact('id'));
    }

    public function productStockDetail(Request $request, $id)
    {
        // if (!Gate::allows('product_stock_detail')) {
        //     return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        // }
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
        if (! Gate::allows('product_active')) {
            return abort(401);
        }

        $response = Product::activeRecord($request->id, $request->status);

        if ($response) {
            return ApiHelper::apiResponse($this->success, 'Status has been changed successfully.');
        }

        return ApiHelper::apiResponse($this->success, 'Product not found.', false);

    }
}
