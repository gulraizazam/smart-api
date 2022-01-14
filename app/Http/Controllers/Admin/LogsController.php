<?php

namespace App\Http\Controllers\admin;

use App\HelperModule\ApiHelper;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use DB;
use Illuminate\Support\Facades\Input;
use Auth;
use Validator;
use App\Models\AuditTrails;
use App\Models\AuditTrailTables;
use App\Models\AuditTrailActions;

class LogsController extends Controller
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
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Gate::allows('logs_manage')) {
            return abort(401);
        }
        return view('admin.logs.index');
    }


    /**
     * Display a listing of the logs.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\never
     */
    public function datatable(Request $request)
    {
        try {
            if (!Gate::allows('logs_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $records = array();
            $records["data"] = array();
            // Get Total Records
            $iTotalRecords = AuditTrails::getTotalRecords();
            list($orderBy, $order) = getSortBy($request);
            list($iDisplayLength, $iDisplayStart, $pages, $page) = getPaginationElement($request, $iTotalRecords);

            $audittrails = AuditTrails::getRecords($iDisplayStart, $iDisplayLength, Auth::User()->account_id);

            $records["data"] = $audittrails;
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
}
