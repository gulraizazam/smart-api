<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\LeadSources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class LeadSourcesController extends Controller
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
        if (!Gate::allows('lead_sources_manage')) {
            return abort(401);
        }

        return view('admin.lead_sources.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('lead_sources_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, 'lead_sources');

            $records = ['data' => []];
            [$orderBy, $order] = getSortBy($request);

            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $sources = LeadSources::getBulkData($ids);
                $sources?->each(function ($source) {
                    if (!LeadSources::isChildExists($source->id, Auth::user()->account_id)) {
                        $source->delete();
                    }
                });
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            $accountId = Auth::user()->account_id;
            $iTotalRecords = LeadSources::getTotalRecords($request, $accountId, $apply_filter);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $records['data'] = LeadSources::getRecords($request, $iDisplayStart, $iDisplayLength, $accountId, $apply_filter);
            $records['permissions'] = [
                'edit' => Gate::allows('lead_sources_edit'),
                'delete' => Gate::allows('lead_sources_destroy'),
                'active' => Gate::allows('lead_sources_active'),
                'inactive' => Gate::allows('lead_sources_inactive'),
            ];
            $records['active_filters'] = Filters::all(Auth::id(), 'lead_sources');
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

    public function create()
    {
        if (!Gate::allows('lead_sources_create')) {
            return abort(401);
        }

        return view('admin.lead_sources.create');
    }

    public function sortOrder()
    {
        if (!Gate::allows('lead_sources_sort')) {
            return abort(401);
        }

        return view('admin.lead_sources.sort');
    }

    public function sortOrderSave(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('lead_sources_sort')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $itemIDs = $request->item_ids;
            if (!count($itemIDs)) {
                return ApiHelper::apiResponse($this->success, 'Something went Wrong! Records are not sorted', false);
            }

            foreach ($itemIDs as $key => $itemID) {
                LeadSources::where('id', $itemID)->update(['sort_no' => $key]);
            }

            return ApiHelper::apiResponse($this->success, 'Records are sorted Successfully!');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function sortOrderGet(): JsonResponse
    {
        try {
            if (!Gate::allows('lead_sources_sort')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $lead_sources = LeadSources::where('account_id', Auth::user()->account_id)
                ->orderBy('sort_no')
                ->get();

            return ApiHelper::apiResponse($this->success, 'Success', true, $lead_sources);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('lead_sources_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $validator = Validator::make($request->all(), ['name' => 'required']);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }

            if (LeadSources::createRecord($request, Auth::user()->account_id)) {
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
            if (!Gate::allows('lead_sources_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $lead_source = LeadSources::getData($id);

            if (!$lead_source) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }

            return ApiHelper::apiResponse($this->success, 'Success', true, $lead_source);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('lead_sources_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $validator = Validator::make($request->all(), ['name' => 'required']);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }

            if (LeadSources::updateRecord($id, $request, Auth::user()->account_id)) {
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
            if (!Gate::allows('lead_sources_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $response = LeadSources::DeleteRecord($id);

            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function status(Request $request): JsonResponse
    {
        try {
            $response = match (true) {
                $request->status == 0 => Gate::allows('lead_sources_inactive')
                    ? LeadSources::InactiveRecord($request->id)
                    : null,
                default => Gate::allows('lead_sources_active')
                    ? LeadSources::activeRecord($request->id)
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
