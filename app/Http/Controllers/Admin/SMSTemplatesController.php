<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Http\Controllers\Controller;
use App\Models\SMSTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SMSTemplatesController extends Controller
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
     * Display a listing of Sms Templates.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\never
     */
    public function index()
    {
        if (! Gate::allows('sms_templates_manage')) {
            return abort(401);
        }

        return view('admin.sms_templates.index');
    }

    /**
     * Display a listing of Sms Templates.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {
            if (! Gate::allows('sms_templates_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $filename = 'sms_templates';

            $filters = getFilters($request->all());

            $apply_filter = checkFilters($filters, $filename);

            $records = [];
            $records['data'] = [];

            [$orderBy, $order] = getSortBy($request);
            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $SMSTemplates = SMSTemplates::getBulkData($ids);
                if ($SMSTemplates) {
                    foreach ($SMSTemplates as $SMSTemplate) {
                        $SMSTemplate->delete();
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            // Get Total Records
            $iTotalRecords = SMSTemplates::getTotalRecords($request, Auth::User()->account_id, $apply_filter);

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $SMSTemplates = SMSTemplates::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);

            $records['data'] = $SMSTemplates;
            $records['permissions'] = [
                'edit' => Gate::allows('sms_templates_edit'),
                'active' => Gate::allows('sms_templates_active'),
                'inactive' => Gate::allows('sms_templates_inactive'),
            ];
            $filters = Filters::all(Auth::User()->id, 'sms_templates');
            $records['active_filters'] = $filters;
            $records['filter_values'] = [
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
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! Gate::allows('sms_templates_manage')) {
            return abort(401);
        }

        return view('admin.sms_templates.create');
    }

    /**
     * Store a newly created Sms Templates in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            if (! Gate::allows('sms_templates_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            if (SMSTemplates::createRecord($request, Auth::User()->account_id)) {
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
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function verifyFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
            'content' => 'required',
        ]);
    }

    /**
     * Show data for editing Sms Templates.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        try {
            if (! Gate::allows('sms_templates_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $sms_template = SMSTemplates::getData($id);
            if (! $sms_template) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }

            $sms_template->variables = GeneralFunctions::smsTemplateVariables($sms_template->slug);

            return ApiHelper::apiResponse($this->success, 'Success', true, $sms_template);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update Sms Templates in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            if (! Gate::allows('sms_templates_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            if (SMSTemplates::updateRecord($id, $request, Auth::User()->account_id)) {
                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Remove Sms Template from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            if (! Gate::allows('sms_templates_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $sms_template = SMSTemplates::getData($id);
            if (! $sms_template) {
                return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
            }
            $sms_template->delete();

            return ApiHelper::apiResponse($this->success, 'Record has been deleted successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Change status of SMS Template
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        try {
            $sms_template = SMSTemplates::getData($request->id);
            if (! $sms_template) {
                return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
            }
            if ($request->status == 0) {
                if (! Gate::allows('sms_templates_inactive')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
                }
                $update = $sms_template->update(['active' => 0]);

                return ApiHelper::apiResponse($this->success, 'Record has been inactivated successfully.');
            } else {
                if (! Gate::allows('sms_templates_active')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
                }
                $update = $sms_template->update(['active' => 1]);

                return ApiHelper::apiResponse($this->success, 'Record has been activated successfully.');
            }
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
