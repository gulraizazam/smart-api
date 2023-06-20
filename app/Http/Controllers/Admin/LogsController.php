<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\AuditTrails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

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
        if (! Gate::allows('logs_manage')) {
            return abort(401);
        }

        return view('admin.logs.index');
    }

    /**
     * Display a listing of the logs.
     *
     * @return \Illuminate\Http\JsonResponse|\never
     */
    public function datatable(Request $request)
    {
        try {
            if (! Gate::allows('logs_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $records = [];
            $records['data'] = [];
            // Get Total Records
            $iTotalRecords = AuditTrails::getTotalRecords();
            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $audittrails = AuditTrails::getRecords($iDisplayStart, $iDisplayLength, Auth::User()->account_id);

            $records['data'] = $audittrails;
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
}
