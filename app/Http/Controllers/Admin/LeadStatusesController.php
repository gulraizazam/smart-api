<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\LeadStatuses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class LeadStatusesController extends Controller
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
     * Display a listing of Lead_statuse.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! Gate::allows('lead_statuses_manage')) {
            return abort(401);
        }

        return view('admin.lead_statuses.index');
    }

    /**
     * Display a listing of Lead Status
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {
            if (! Gate::allows('lead_statuses_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $filename = 'lead_statuses';

            $filters = getFilters($request->all());

            $apply_filter = checkFilters($filters, $filename);

            $records = [];
            $records['data'] = [];
            [$orderBy, $order] = getSortBy($request);

            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $LeadStatuses = LeadStatuses::getBulkData($ids);
                if ($LeadStatuses) {
                    foreach ($LeadStatuses as $LeadStatus) {
                        // Check if child records exists or not, If exist then disallow to delete it.
                        if (! LeadStatuses::isChildExists($LeadStatus->id, Auth::User()->account_id)) {
                            $LeadStatus->delete();
                        }
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            // Get Total Records
            $iTotalRecords = LeadStatuses::getTotalRecords($request, Auth::User()->account_id, $apply_filter);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $allLeadStatuses = LeadStatuses::getAllRecordsDictionary(Auth::User()->account_id);

            $LeadStatuses = LeadStatuses::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);

            $parentLeadStatuses = LeadStatuses::getParentRecords(false, Auth::User()->account_id, false, true);

            if ($LeadStatuses) {
                foreach ($LeadStatuses as $lead_status) {
                    $lead_status->parent_id = ($lead_status->parent_id && array_key_exists($lead_status->parent_id, $allLeadStatuses)) ? $allLeadStatuses[$lead_status->parent_id]->name : '-';
                    $lead_status->is_comment = ($lead_status->is_comment) ? 'Yes' : 'No';
                    $lead_status->is_default = ($lead_status->is_default) ? 'Yes' : 'No';
                    $lead_status->is_arrived = ($lead_status->is_arrived) ? 'Yes' : 'No';
                    $lead_status->is_converted = ($lead_status->is_converted) ? 'Yes' : 'No';
                    $lead_status->is_junk = ($lead_status->is_junk) ? 'Yes' : 'No';
                }
            }
            $records['data'] = $LeadStatuses;
            $records['permissions'] = [
                'edit' => Gate::allows('lead_sources_edit'),
                'delete' => Gate::allows('lead_sources_destroy'),
                'active' => Gate::allows('lead_sources_active'),
                'inactive' => Gate::allows('lead_sources_inactive'),
            ];

            $filters = Filters::all(Auth::User()->id, 'lead_statuses');
            $records['active_filters'] = $filters;
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

    /**
     * Show the form for creating new Lead_statuse.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! Gate::allows('lead_statuses_create')) {
            return abort(401);
        }

        $lead_statuse = new \stdClass();
        $lead_statuse->is_default = 0;
        $lead_statuse->is_arrived = 0;
        $lead_statuse->is_converted = 0;
        $lead_statuse->is_junk = 0;

        $parentLeadStatuses = LeadStatuses::getParentRecords('Parent Group', Auth::User()->account_id, false, true);

        return view('admin.lead_statuses.create', compact('parentLeadStatuses', 'lead_statuse'));
    }

    /**
     * Show Sort Order page for Lead Statuses
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\never
     */
    public function sortOrder()
    {
        if (! Gate::allows('lead_statuses_sort')) {
            return abort(401);
        }

        return view('admin.lead_statuses.sort');
    }

    /**
     * Sorting save after change order
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sortOrderSave(Request $request)
    {
        try {
            if (! Gate::allows('lead_statuses_sort')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $itemIDs = $request->item_ids;
            if (count($itemIDs)) {
                foreach ($itemIDs as $key => $itemID) {
                    LeadStatuses::where('id', '=', $itemID)->update(['sort_no' => $key]);
                }

                return ApiHelper::apiResponse($this->success, 'Records are sorted Successfully!');
            }

            return ApiHelper::apiResponse($this->success, 'Something went Wrong! Records are not sorted', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * get records for sorting Lead Status
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sortOrderGet()
    {
        try {
            if (! Gate::allows('lead_statuses_sort')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $lead_statuses = LeadStatuses::where(['account_id' => Auth::User()->account_id])->orderby('sort_no', 'ASC')->get();

            return ApiHelper::apiResponse($this->success, 'Success', true, $lead_statuses);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Store a newly created Lead_statuse in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            if (! Gate::allows('lead_statuses_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            if (LeadStatuses::createRecord($request, Auth::User()->account_id)) {
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
        ]);
    }

    /**
     * Get Data for editing Lead_statuse.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        try {
            if (! Gate::allows('lead_statuses_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $lead_statuse = LeadStatuses::getData($id);
            if (! $lead_statuse) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }
            $parentLeadStatuses = LeadStatuses::getParentRecords(false, Auth::User()->account_id, $lead_statuse->id, true);

            return ApiHelper::apiResponse($this->success, 'Success', true, compact('lead_statuse', 'parentLeadStatuses'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update Lead_statuse in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            if (! Gate::allows('lead_statuses_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            if (LeadStatuses::updateRecord($id, $request, Auth::User()->account_id)) {
                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Remove Lead_status from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            if (! Gate::allows('lead_statuses_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $response = LeadStatuses::DeleteRecord($id);

            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Change status of Lead Statuses
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        try {
            if ($request->status == 0) {
                if (! Gate::allows('cities_inactive')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
                }
                $response = LeadStatuses::inactiveRecord($request->id);
            } else {
                if (! Gate::allows('cities_active')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
                }
                $response = LeadStatuses::activeRecord($request->id);
            }

            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
