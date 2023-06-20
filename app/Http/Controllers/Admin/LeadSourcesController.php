<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\LeadSources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class LeadSourcesController extends Controller
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
     * Display a listing of Lead_source.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! Gate::allows('lead_sources_manage')) {
            return abort(401);
        }

        return view('admin.lead_sources.index');
    }

    /**
     * Display a listing of Lead Sources.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {
            if (! Gate::allows('lead_sources_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $filename = 'lead_sources';
            $filters = getFilters($request->all());

            $apply_filter = checkFilters($filters, $filename);

            $records = [];
            $records['data'] = [];

            [$orderBy, $order] = getSortBy($request);
            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $LeadSources = LeadSources::getBulkData($ids);
                if ($LeadSources) {
                    foreach ($LeadSources as $LeadSource) {
                        // Check if child records exists or not, If exist then disallow to delete it.
                        if (! LeadSources::isChildExists($LeadSource->id, Auth::User()->account_id)) {
                            $LeadSource->delete();
                        }
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            // Get Total Records
            $iTotalRecords = LeadSources::getTotalRecords($request, Auth::User()->account_id, $apply_filter);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $LeadSources = LeadSources::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);

            $records['data'] = $LeadSources;
            $records['permissions'] = [
                'edit' => Gate::allows('lead_sources_edit'),
                'delete' => Gate::allows('lead_sources_destroy'),
                'active' => Gate::allows('lead_sources_active'),
                'inactive' => Gate::allows('lead_sources_inactive'),
            ];
            $filters = Filters::all(Auth::User()->id, 'lead_sources');
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
     * Show the form for creating new Lead_source.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! Gate::allows('lead_sources_create')) {
            return abort(401);
        }

        return view('admin.lead_sources.create');
    }

    /**
     * Show Sort Order page for Lead Source
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\never
     */
    public function sortOrder()
    {
        if (! Gate::allows('lead_sources_sort')) {
            return abort(401);
        }

        return view('admin.lead_sources.sort');
    }

    /**
     * Sorting save after change order
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sortOrderSave(Request $request)
    {
        try {
            if (! Gate::allows('lead_sources_sort')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $itemIDs = $request->item_ids;
            if (count($itemIDs)) {
                foreach ($itemIDs as $key => $itemID) {
                    LeadSources::where('id', '=', $itemID)->update(['sort_no' => $key]);
                }

                return ApiHelper::apiResponse($this->success, 'Records are sorted Successfully!');
            }

            return ApiHelper::apiResponse($this->success, 'Something went Wrong! Records are not sorted', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * get records for sorting Lead Sources
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sortOrderGet()
    {
        try {
            if (! Gate::allows('lead_sources_sort')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $lead_sources = LeadSources::where(['account_id' => Auth::User()->account_id])->orderby('sort_no', 'ASC')->get();

            return ApiHelper::apiResponse($this->success, 'Success', true, $lead_sources);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Store a newly created Lead_source in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            if (! Gate::allows('lead_sources_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            if (LeadSources::createRecord($request, Auth::User()->account_id)) {
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
     * Show the form for editing Lead_source.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        try {
            if (! Gate::allows('lead_sources_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $lead_source = LeadSources::getData($id);
            if (! $lead_source) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }

            return ApiHelper::apiResponse($this->success, 'Success', true, $lead_source);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update Lead_source in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            if (! Gate::allows('lead_sources_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            if (LeadSources::updateRecord($id, $request, Auth::User()->account_id)) {
                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Remove Lead_source from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            if (! Gate::allows('lead_sources_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $response = LeadSources::DeleteRecord($id);

            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Change status of Lead Source
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
                $response = LeadSources::inactiveRecord($request->id);
            } else {
                if (! Gate::allows('cities_active')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
                }
                $response = LeadSources::activeRecord($request->id);
            }

            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
