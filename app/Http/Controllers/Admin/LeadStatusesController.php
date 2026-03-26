<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\LeadStatuses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class LeadStatusesController extends Controller
{
    protected string $error;
    protected string $success;
    protected string $unauthorized;

    public function __construct()
    {
        $this->error = config('constants.api_status.error');
        $this->success = config('constants.api_status.success');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    public function index()
    {
        if (!Gate::allows('lead_statuses_manage')) {
            return abort(401);
        }

        return view('admin.lead_statuses.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('lead_statuses_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, 'lead_statuses');

            $records = ['data' => []];
            [$orderBy, $order] = getSortBy($request);

            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $statuses = LeadStatuses::getBulkData($ids);
                $statuses?->each(function ($status) {
                    if (!LeadStatuses::isChildExists($status->id, Auth::user()->account_id)) {
                        $status->delete();
                    }
                });
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            $accountId = Auth::user()->account_id;
            $iTotalRecords = LeadStatuses::getTotalRecords($request, $accountId, $apply_filter);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $allLeadStatuses = LeadStatuses::getAllRecordsDictionary($accountId);
            $LeadStatuses = LeadStatuses::getRecords($request, $iDisplayStart, $iDisplayLength, $accountId, $apply_filter);
            $parentLeadStatuses = LeadStatuses::getParentRecords(false, $accountId, [], true);

            // Transform boolean flags to display values
            foreach ($LeadStatuses as $status) {
                $status->parent_id = ($status->parent_id && array_key_exists($status->parent_id, $allLeadStatuses))
                    ? $allLeadStatuses[$status->parent_id]->name
                    : '-';
                $status->is_comment = $status->is_comment ? 'Yes' : 'No';
                $status->is_default = $status->is_default ? 'Yes' : 'No';
                $status->is_arrived = $status->is_arrived ? 'Yes' : 'No';
                $status->is_converted = $status->is_converted ? 'Yes' : 'No';
                $status->is_junk = $status->is_junk ? 'Yes' : 'No';
            }

            $records['data'] = $LeadStatuses;
            $records['permissions'] = [
                'edit' => Gate::allows('lead_sources_edit'),
                'delete' => Gate::allows('lead_sources_destroy'),
                'active' => Gate::allows('lead_sources_active'),
                'inactive' => Gate::allows('lead_sources_inactive'),
            ];
            $records['active_filters'] = Filters::all(Auth::id(), 'lead_statuses');
            $records['filter_values'] = [
                'parents' => $parentLeadStatuses,
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
        if (!Gate::allows('lead_statuses_create')) {
            return abort(401);
        }

        $lead_statuse = new \stdClass();
        $lead_statuse->is_default = 0;
        $lead_statuse->is_arrived = 0;
        $lead_statuse->is_converted = 0;
        $lead_statuse->is_junk = 0;

        $parentLeadStatuses = LeadStatuses::getParentRecords('Parent Group', Auth::user()->account_id, [], true);

        return view('admin.lead_statuses.create', compact('parentLeadStatuses', 'lead_statuse'));
    }

    public function sortOrder()
    {
        if (!Gate::allows('lead_statuses_sort')) {
            return abort(401);
        }

        return view('admin.lead_statuses.sort');
    }

    public function sortOrderSave(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('lead_statuses_sort')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $itemIDs = $request->item_ids;
            if (!count($itemIDs)) {
                return ApiHelper::apiResponse($this->success, 'Something went Wrong! Records are not sorted', false);
            }

            foreach ($itemIDs as $key => $itemID) {
                LeadStatuses::where('id', $itemID)->update(['sort_no' => $key]);
            }

            return ApiHelper::apiResponse($this->success, 'Records are sorted Successfully!');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function sortOrderGet(): JsonResponse
    {
        try {
            if (!Gate::allows('lead_statuses_sort')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $lead_statuses = LeadStatuses::where('account_id', Auth::user()->account_id)
                ->orderBy('sort_no')
                ->get();

            return ApiHelper::apiResponse($this->success, 'Success', true, $lead_statuses);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('lead_statuses_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $validator = Validator::make($request->all(), ['name' => 'required']);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }

            if (LeadStatuses::createRecord($request, Auth::user()->account_id)) {
                return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('lead_statuses_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $lead_statuse = LeadStatuses::getData($id);

            if (!$lead_statuse) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }

            $parentLeadStatuses = LeadStatuses::getParentRecords(false, Auth::user()->account_id, $lead_statuse->id, true);

            return ApiHelper::apiResponse($this->success, 'Success', true, compact('lead_statuse', 'parentLeadStatuses'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('lead_statuses_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $validator = Validator::make($request->all(), ['name' => 'required']);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }

            if (LeadStatuses::updateRecord($id, $request, Auth::user()->account_id)) {
                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('lead_statuses_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $response = LeadStatuses::DeleteRecord($id);

            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function status(Request $request): JsonResponse
    {
        try {
            $response = match (true) {
                $request->status == 0 => Gate::allows('lead_statuses_inactive')
                    ? LeadStatuses::InactiveRecord($request->id)
                    : null,
                default => Gate::allows('lead_statuses_active')
                    ? LeadStatuses::activeRecord($request->id)
                    : null,
            };

            if ($response === null) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
