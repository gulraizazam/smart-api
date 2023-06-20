<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\PaymentModes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class PaymentModesController extends Controller
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

    public function index()
    {
        if (! Gate::allows('payment_modes_manage')) {
            return abort(401);
        }
        $filters = Filters::all(Auth::User()->id, 'payment_modes');

        return view('admin.payment_modes.index', compact('filters'));
    }

    /**
     * Display a listing of Payment Modes.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {
            if (! Gate::allows('payment_modes_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $filename = 'payment_modes';

            $filters = getFilters($request->all());

            $apply_filter = checkFilters($filters, $filename);

            $records = [];
            $records['data'] = [];
            [$orderBy, $order] = getSortBy($request);
            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $PaymentModes = PaymentModes::getBulkData($ids);
                if ($PaymentModes) {
                    foreach ($PaymentModes as $city) {
                        // Check if child records exists or not, If exist then disallow to delete it.
                        if (! PaymentModes::isChildExists($city->id, Auth::User()->account_id)) {
                            $city->delete();
                        }
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }
            // Get Total Records
            $iTotalRecords = PaymentModes::getTotalRecords($request, Auth::User()->account_id, $apply_filter);

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $PaymentModes = PaymentModes::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);
            foreach ($PaymentModes as $paymentMode) {
                $paymentMode->type = ucwords($paymentMode->type);
                $paymentMode->payment_type = config('constants.payment_type.'.$paymentMode->payment_type);
            }
            $records['data'] = $PaymentModes;
            $records['permissions'] = [
                'edit' => Gate::allows('payment_modes_edit'),
                'delete' => Gate::allows('payment_modes_destroy'),
                'active' => Gate::allows('payment_modes_active'),
                'inactive' => Gate::allows('payment_modes_inactive'),
            ];
            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];

            return response()->json($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! Gate::allows('payment_modes_create')) {
            return abort(401);
        }

        return view('admin.payment_modes.create', compact('city'));
    }

    public function sortorder_save(Request $request)
    {
        try {
            if (! Gate::allows('payment_modes_sort')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $itemIDs = $request->item_ids;
            if (count($itemIDs)) {
                foreach ($itemIDs as $key => $itemID) {
                    PaymentModes::where('id', '=', $itemID)->update(['sort_number' => $key]);
                }

                return ApiHelper::apiResponse($this->success, 'Records are sorted Successfully!');
            }

            return ApiHelper::apiResponse($this->success, 'Something went Wrong! Records are not sorted', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function sortorder()
    {
        if (! Gate::allows('payment_modes_sort')) {
            return abort(401);
        }

        return view('admin.payment_modes.sort');
    }

    /**
     * get records for sorting Payment Modes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sortOrderGet()
    {
        try {
            if (! Gate::allows('payment_modes_sort')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $payment_modes = PaymentModes::where(['account_id' => Auth::User()->account_id])->orderby('sort_number', 'ASC')->get();

            return ApiHelper::apiResponse($this->success, 'Success', true, $payment_modes);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update record of Payment Modes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            if (! Gate::allows('payment_modes_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            if (PaymentModes::createRecord($request, Auth::User()->account_id)) {
                return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function verifyFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
            'type' => 'required',
        ]);
    }

    /**
     * Get data for edit Payment Mode
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        try {
            if (! Gate::allows('payment_modes_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $payment_mode = PaymentModes::getData($id);
            if (! $payment_mode) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }

            return ApiHelper::apiResponse($this->success, 'Success', true, $payment_mode);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update record of Payment Modes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            if (! Gate::allows('payment_modes_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            if (PaymentModes::updateRecord($id, $request, Auth::User()->account_id)) {
                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Remove Payment Mode
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            if (! Gate::allows('payment_modes_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $response = PaymentModes::deleteRecord($id);

            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Change status of Payment Modes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        try {
            if ($request->status == 0) {
                if (! Gate::allows('payment_modes_inactive')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
                }
                $response = PaymentModes::inactiveRecord($request->id);
            } else {
                if (! Gate::allows('payment_modes_active')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
                }
                $response = PaymentModes::activeRecord($request->id);
            }

            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
