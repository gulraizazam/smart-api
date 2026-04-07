<?php

declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditTrails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LogsController extends Controller
{


    

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): mixed
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
    public function datatable(Request $request): mixed
    {
        try {
            if (! Gate::allows('logs_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
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

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LogsController');
        }
    }
}
