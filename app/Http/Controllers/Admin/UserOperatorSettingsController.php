<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\UserOperatorSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UserOperatorSettingsController extends Controller
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
     * Display the list of operators.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\never
     */
    public function index()
    {
        if (! Gate::allows('user_operator_settings_manage')) {
            return abort(401);
        }
        $filters = Filters::all(Auth::User()->id, 'operators');

        return view('admin.user_operator_settings.index', compact('filters'));
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        if (! Gate::allows('user_operator_settings_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        $filename = 'operators';

        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, $filename);

        $records = [];
        $records['data'] = [];

        [$orderBy, $order] = getSortBy($request);

        // Get Total Records
        $iTotalRecords = UserOperatorSettings::getTotalRecords($request, Auth::User()->account_id, $apply_filter);

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $Operators = UserOperatorSettings::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);

        if ($Operators) {
            foreach ($Operators as $operator) {
                $operator->password = '********';
                $operator->test_mode = $operator->test_mode == 1 ? 'Yes' : 'No';
            }
        }
        $records['data'] = $Operators;

        $records['permissions'] = [
            'edit' => Gate::allows('user_operator_settings_edit'),
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
    }

    /**
     * Validate form fields
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function verifyFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'test_mode' => 'required',
        ]);
    }

    /**
     * Get Data for editing Operator Settings.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        try {
            if (! Gate::allows('user_operator_settings_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $user_operator_setting = UserOperatorSettings::getData($id);
            if (! $user_operator_setting) {
                return ApiHelper::apiResponse($this->success, 'No Data Found!', false);
            }

            return ApiHelper::apiResponse($this->success, 'Success', true, $user_operator_setting);
        } catch (\Exception $e) {
            return ApiHelper::apiResponse($this->success, $e->getMessage(), false);
        }

    }

    /**
     * Update Operator Settings.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            if (! Gate::allows('user_operator_settings_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false);
            }
            if (UserOperatorSettings::updateRecord($id, $request, Auth::User()->account_id)) {
                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiResponse($this->success, $e->getMessage(), false);
        }
    }

    /**
     * Load Operator by ID
     *
     * @return \Illuminate\Http\Response
     */
    public function loadOperator(Request $request)
    {
        if (! Gate::allows('user_operator_settings_manage')) {
            return abort(401);
        }

        $GlobalOperatorSetting = UserOperatorSettings::getGlobalOperator($request->get('operator_id'));

        if ($GlobalOperatorSetting) {
            if ($GlobalOperatorSetting->password) {
                $GlobalOperatorSetting->password = '********';
            }

            return response()->json([
                'status' => 1,
                'operator_setting' => $GlobalOperatorSetting->toArray(),
            ]);
        }

        return response()->json([
            'status' => 0,
            'message' => 'Something went wrong, please try again later.',
        ]);
    }
}
