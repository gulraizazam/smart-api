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
use App\Helpers\GeneralFunctions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\DoctorHasLocations;
use App\Models\Inventory;
use App\Models\RoleHasUsers;
use App\Models\User;
use App\Models\UserHasLocations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

use Illuminate\Support\Facades\Validator;

class TransferProductsController extends Controller
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
        // if (!Gate::allows('transfer_product_manage')) {
        //     return abort(401);
        // }

        return view('admin.transfer_product.index');
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

           
            // Get Total Records
            $iTotalRecords = TransferProduct::getTotalRecords($request, Auth::User()->account_id, $apply_filter);
            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $transfer_products = TransferProduct::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);
           
            $centres = Locations::getAllRecordsDictionary(Auth::user()->account_id, 'custom', 'id', 'desc', ACL::getUserCentres());
            $warehouse = Warehouse::getAllRecordsDictionary(Auth::user()->account_id, ACL::getUserWarehouse());

            if ($transfer_products) {
                $transfer_products = collect($transfer_products)->map(function ($transfer_product, $index) {
                    $transfer_product->transfer_index = $index + 1;
                    $transfer_product_name = Product::where(['id' => $transfer_product->product_id])->select('name')->first();
                    $product_detail_quantity = ProductDetail::where(['id' => $transfer_product->product_detail_id])->first();

                    $transfer_product->from = TransferProduct::parentLocation($transfer_product->id);
                    $transfer_product->to = TransferProduct::childLocation($transfer_product->id);
                    $transfer_product->name = $transfer_product_name->name;
                    $transfer_product->quantity = $product_detail_quantity->quantity ?? 0;
                    $transfer_product->transfer_date = $transfer_product->transfer_date;
                    return $transfer_product;
                });
            }
            $records['data'] = $transfer_products;
            $records['permissions'] = [
                'edit' => Gate::allows('transfer_product_edit'),
                'manage' => Gate::allows('transfer_product_manage'),
                'delete' => Gate::allows('transfer_product_destroy'),
                'create' => Gate::allows('transfer_product_create'),
            ];
            $records['active_filters'] = $filters;
            $records['filter_values'] = [
                'centres' => collect($centres)->pluck('name', 'id'),
                'warehouse' => collect($warehouse)->pluck('name', 'id')
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
            // if (!Gate::allows('transfer_product_create')) {
            //     return abort(401);
            // }

            $centres = Locations::whereIn('id', ACL::getUserCentres())->pluck('name', 'id');
            $warehouse = Warehouse::whereActive(1)->pluck('name', 'id');

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'centres' => $centres,
                'warehouse' => $warehouse
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
            // if (!Gate::allows('transfer_product_create')) {
            //     return abort(401);
            // }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
           
            $stock_check = GeneralFunctions::inventoryCheck($request);
            
            if ($stock_check < $request->quantity) {
                return collect(['status' => false, 'message' => 'This product stock not available.']);
            }
            if($request->quantity <= 0){
                return collect(['status' => false, 'message' => 'Please add valid product quantity.']);
            }
            if($request->product_type_option_to == 'in_warehouse' && $request->to_warehouse_id == null){
                return collect(['status' => false, 'message' => 'Please select warehouse']);
            }
            if($request->product_type_option_to == 'in_branch' && $request->to_location_id == null){
                return collect(['status' => false, 'message' => 'Please select any centre.']);
            }

            if ($request->product_type_option_to == 'in_warehouse') {
                $from_value = $request->from_warehouse_id;
                $to_value = $request->to_warehouse_id;
                if($from_value == $to_value){
                    return ApiHelper::apiResponse($this->error, "Please add different location.", false);
                }
            } else {
                $from_value = $request->from_location_id;
                $to_value = $request->to_location_id;
                if($from_value == $to_value){
                    return ApiHelper::apiResponse($this->error, "Please add different location.", false);
                }
            }
         
            $request['type'] = 'product_transfer_create';
            $request['message'] = 'Transfer Product create';

            $transfer_product = TransferProduct::createRecord($request, Auth::User()->account_id);
            if ($transfer_product['record']) {
                $product_detail = ProductDetail::createRecordTransferProduct($transfer_product['data'], Auth::User()->account_id);
                if ($product_detail) {
                    TransferProduct::where(['id' => $transfer_product['record']->id])->update(['product_detail_id' => $product_detail->id]);
                     $product_type = Product::find($request->product_id);
                    if($request->from_warehouse_id){
                        $minus_inventory = Inventory::where('product_id',$request->product_id)->where('warehouse_id',$request->from_warehouse_id)->first();
                        if($request->to_warehouse_id){
                            $update_inventory = Inventory::where('product_id',$request->product_id)->where('warehouse_id',$request->to_warehouse_id)->first();
                        }else{
                            $update_inventory = Inventory::where('product_id',$request->product_id)->where('location_id',$request->to_location_id)->first();
                       
                        }
                        
                    }else{
                        if($request->to_warehouse_id){
                            $update_inventory = Inventory::where('product_id',$request->product_id)->where('warehouse_id',$request->to_warehouse_id)->first();
                        }else{
                            $update_inventory = Inventory::where('product_id',$request->product_id)->where('location_id',$request->to_location_id)->first();
                        }
                        $minus_inventory = Inventory::where('product_id',$request->product_id)->where('location_id',$request->from_location_id)->first();
                       
                    }
                    $updated_quantity = $minus_inventory->quantity - $request->quantity;
                   
                    $minus_inventory->update(['quantity'=>$updated_quantity]);
                    if($update_inventory){
                      
                        $latest_updated_quantity = $update_inventory->quantity + $request->quantity;

                        $update_inventory->update(['quantity'=>$latest_updated_quantity]);
                    }else{
                       
                        $inventory = new Inventory();
                        $inventory->product_id = $request->product_id;
                        if($request->to_warehouse_id){
                            $inventory->warehouse_id = $request->to_warehouse_id;
                        }else{
                            $inventory->location_id = $request->to_location_id;
                        }
                        $inventory->quantity = $request->quantity;
                        $inventory->is_saleable = $product_type->product_type == 'for_sale' ? 1 : 0;
                        $inventory->save();

                    }
                 
                    return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
                }
            }
            $message = ($transfer_product['message'] != null) ? $transfer_product['message'] : 'Something went wrong, please try again later.';
            return ApiHelper::apiResponse($this->success, $message, false);
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
            'product_id' => 'required',
            'quantity' => 'required',
            'from_location_id'=>'required',
            'to_location_id'=>'required'
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
            // if (!Gate::allows('transfer_product_edit')) {
            //     return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            // }
            $data['product'] = TransferProduct::findOrFail($id);
            $data['product_details'] = ProductDetail::findOrFail($data['product']->product_detail_id);
            $data['products'] = Product::getAllRecordsDictionary(Auth::user()->account_id);
            if (!$data['product']) {
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
    public function update(Request $request, $id)
    {
        try {
            // if (!Gate::allows('transfer_product_edit')) {
            //     return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            // }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }

            $stock_check = GeneralFunctions::stockC($request->product_id);
            if ($stock_check < $request->quantity) {
                return collect(['status' => false, 'message' => 'This product stock not available.']);
            }
            if($request->quantity <= 0){
                return collect(['status' => false, 'message' => 'Please add valid product quantity.']);
            }
            if($request->product_type_option_to == 'in_warehouse' && $request->to_warehouse_id == null){
                return collect(['status' => false, 'message' => 'Please select warehouse']);
            }
            if($request->product_type_option_to == 'in_branch' && $request->to_location_id == null){
                return collect(['status' => false, 'message' => 'Please select any centre.']);
            }
           
            $request['type'] = 'product_transfer_update';
            $request['message'] = 'Transfer Product update';

            $transfer_product = TransferProduct::updateRecord($id, $request, Auth::User()->account_id);
            $product_detail = ProductDetail::updateRecordTransferProduct($transfer_product['data'], Auth::User()->account_id, $transfer_product['data']['product_detail_id']);
            if ($transfer_product) {
                if ($product_detail) {
                    TransferProduct::where(['id' => $transfer_product['record']->id])->update(['product_detail_id' => $product_detail->id]);
                  
                    return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
                }
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
            // if (!Gate::allows('transfer_product_destroy')) {
            //     return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            // }
            $response = TransferProduct::DeleteRecord($id);

            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getProducts(Request $request)
    {
        $products = Product::getProductsAjax($request, Auth::User()->account_id);
        $doctors = DoctorHasLocations::where('is_allocated',1)->where('is_allocated',1)->where('location_id', $request->from_id)->pluck('user_id')->toArray();
    
        // Fetch active doctors as an associative array
        $users = User::whereIn('id', $doctors)
            ->where('active', 1)
            ->pluck('name', 'id') // Preserve user IDs
            ->toArray();
    
        // Ensure 'from_id' is an array
        $locationIds = is_array($request->from_id) ? $request->from_id : [$request->from_id];
    
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
    
        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'products' => $products,
            'doctors' => $combinedUsers
        ]);
    }
    public function getTransferProducts(Request $request)
    {
       
         
        $products = Product::getTransferProductsAjax($request, Auth::User()->account_id);
      
        if($request->location_id){
            $warehouseId = TransferProduct::where(['product_id'=>$request->product_id,'to_location_id'=>$request->location_id])->pluck('from_warehouse_id')->toArray();
            $warehouses = Warehouse::whereIn('id',$warehouseId)->get();
            
        }else{
            $warehouses = Warehouse::get();
           
        }
        $Users = User::where('user_type_id', 5)->where('active', 1)->pluck('name', 'id');
        

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'products' => $products,
            'warehouses' =>$warehouses,
            'users'=>$Users
        ]);
       
    }
}