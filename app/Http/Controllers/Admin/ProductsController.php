<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Brand;
use App\Models\Product;
use App\Helpers\Filters;
use Illuminate\Support\Facades\Auth;
use App\HelperModule\ApiHelper;
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
     * Display a listing of brand.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        return view('admin.products.index');
    }

    /**
     * Display a listing of brands
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {
            dd($request->all());
            $records = array();
            $records["data"] = array();
            
            if(isset($request->input('query')['search'])){
                $apply_filter = $request->input('query')['search'];
                if (isset($apply_filter['delete'])) {
                    $ids = explode(',', $apply_filter['delete']);
                    $brands = Brand::getBulkData($ids);
                    if ($brands) {
                        foreach ($brands as $brand) {
                            $brand->delete();
                        }
                    }
                    $records['status'] = true;
                    $records['message'] = 'Records has been deleted successfully!';
                }
            }else{
                $apply_filter = false;
            }
            
            // Get Total Records
            $iTotalRecords = Brand::getTotalRecords($request, Auth::User()->account_id, $apply_filter);
            list($orderBy, $order) = getSortBy($request);
            list($iDisplayLength, $iDisplayStart, $pages, $page) = getPaginationElement($request, $iTotalRecords);

            $Brands = Brand::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);

            $records["data"] = $Brands;
            $records["permissions"] = [];
            $records['active_filters'] = $apply_filter;
            $records['filter_values'] = [
                'status' => config('constants.status')
            ];
            $records["meta"] = [
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
     * Store a newly created Brand in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            dd($request->all());
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            if (Brand::createRecord($request, Auth::User()->account_id)) {
                return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
            }
            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Validate form fields
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function verifyFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);
    }

        /**
     * Show the form for editing Lead_source.
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        try {
            dd($id);
            // if (!Gate::allows('brands_edit')) {
            //     return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            // }
            $brand = Brand::getData($id);
            if (!$brand) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }
            return ApiHelper::apiResponse($this->success, 'Success', true, $brand);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update Lead_source in storage.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            dd($request->all());
            // if (!Gate::allows('brands_edit')) {
            //     return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            // }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            if (Brand::updateRecord($id, $request, Auth::User()->account_id)) {
                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
            }
            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Remove Lead_source from storage.
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            dd($id);
            // if (!Gate::allows('brands_destroy')) {
            //     return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            // }
            $response = Brand::DeleteRecord($id);
            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
