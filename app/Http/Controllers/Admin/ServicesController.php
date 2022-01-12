<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Filters;
use App\Helpers\GroupsTree;
use App\Helpers\NodesTree;
use App\Models\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUpdateServicesRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Validator;
use App\Models\TaxTreatmentType;

class ServicesController extends Controller
{
    /**
     * Display a listing of Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!Gate::allows('services_manage')) {
            return abort(401);
        }

        return view('admin.services.index');
    }

    public function datatable(Request $request)
    {
        $filename = 'locations';

        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, $filename);

        $records = array();
        $records["data"] = array();

        if(count($filters) > 0 && hasFilter($filters, 'delete')  != '') {
            $ids = explode(',', $filters['delete']);
            $Locations = Services::getBulkData($ids);
            if ($Locations) {

                foreach ($Locations as $Location) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (!Services::isChildExists($Location->id, Auth::User()->account_id)) {
                        $Location->delete();
                    }
                }
            }
            $records["status"] = true;
            $records["message"] = "Records has been deleted successfully!";
        }

        list($orderBy, $order) = getSortBy($request);

        // Get Total Records
        $iTotalRecords = Services::getTotalRecords($request, Auth::User()->account_id, $apply_filter);

        list( $iDisplayLength, $iDisplayStart, $pages, $page) = getPaginationElement($request, $iTotalRecords);

        $records = $this->getExtraData($records);

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        //dd($Services);

        if (! empty($Services)) {
            unset($Services[0]);
            $records["data"] = $Services;

            $records["permissions"] = [
                'edit' => Gate::allows('services_edit'),
                'delete' => Gate::allows('services_destroy'),
                'active' => Gate::allows('services_active'),
                'inactive' => Gate::allows('services_inactive'),
                'create' => Gate::allows('services_create'),
                'sort' => Gate::allows('services_sort'),
            ];

            $records["meta"] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];

        } //end


        return response()->json($records);
    }

    private function getExtraData($records = []) {


        $filters = Filters::all(Auth::User()->id, 'services');



        /* Create Nodes with Parents */
        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $records['filter_values'] = [
            'services' => $Services,
            'status' => config('constants.status')
        ];

        $records['active_filters'] = $filters;

        return $records;
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!Gate::allows('services_create')) {
            return abort(401);
        }

        $BaseServices = Services::getGroupsActiveOnly();

        if ($BaseServices) {
            $Services = GroupsTree::buildOptions(GroupsTree::buildTree($BaseServices->toArray()), 0);
        } else {
            $Services = array();
        }

        $service = new \stdClass();
        $service->duration = null;
        $service->parent_id = null;

        $tax_treatment_types = TaxTreatmentType::get();

        $select_tax_treatment_type = 1;

        return view('admin.services.create', compact('Services', 'service', 'tax_treatment_types', 'select_tax_treatment_type'));
    }

    /**
     * Store a newly created Permission in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        if (!Gate::allows('services_create')) {
            return abort(401);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->messages()->all(),
            ));
        }

        if (Services::createRecord($request, Auth::User()->account_id)) {
            flash('Record has been created successfully.')->success()->important();

            return response()->json(array(
                'status' => 1,
                'message' => 'Record has been created successfully.',
            ));
        } else {
            return response()->json(array(
                'status' => 0,
                'message' => 'Something went wrong, please try again later.',
            ));
        }
    }

    /**
     * Validate form fields
     *
     * @param \Illuminate\Http\Request $request
     * @return Validator $validator;
     */
    protected function verifyFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
            'parent_id' => 'required',
        ]);
    }


    /**
     * Show the form for editing Permission.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!Gate::allows('services_edit')) {
            return abort(401);
        }
        $service = Services::findOrFail($id);

        $BaseServices = Services::getGroupsActiveOnly();

        if ($BaseServices) {
            $Services = GroupsTree::buildOptions(GroupsTree::buildTree($BaseServices->toArray(), 0, $service->id), $service->parent_id);
        } else {
            $Services = array();
        }

        $tax_treatment_types = TaxTreatmentType::get();

        if ($service->tax_treatment_type_id == 0) {
            $select_tax_treatment_type = 1;
        } else {
            $select_tax_treatment_type = $service->tax_treatment_type_id;
        }

        return view('admin.services.edit', compact('service', 'Services', 'tax_treatment_types', 'select_tax_treatment_type'));
    }

    /**
     * Update Permission in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (!Gate::allows('services_edit')) {
            return abort(401);
        }
        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->messages()->all(),
            ));
        }

        $service = Services::findOrFail($id);

        if (
            Services::isChildExists($id, Auth::User()->account_id) &&
            ($service->parent_id != $request->get('parent_id') || $service->end_node != $request->get('end_node'))
        ) {
            return response()->json(array(
                'status' => 0,
                'message' => array('Parent Service can not be changed due to one or more services are associated with it.'),
            ));
        }

        if (Services::updateRecord($id, $request, Auth::User()->account_id)) {
            flash('Record has been updated successfully.')->success()->important();

            return response()->json(array(
                'status' => 1,
                'message' => 'Record has been updated successfully.',
            ));
        } else {
            return response()->json(array(
                'status' => 0,
                'message' => 'Something went wrong, please try again later.',
            ));
        }
    }


    /**
     * Remove Permission from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!Gate::allows('services_destroy')) {
            return abort(401);
        }
        Services::deleteRecord($id);

        return redirect()->route('admin.services.index');
    }

    /**
     * Inactive Record from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function inactive($id)
    {
        if (!Gate::allows('services_inactive')) {
            return abort(401);
        }

        Services::inactiveRecord($id);

        return redirect()->route('admin.services.index');

    }

    /**
     * Inactive Record from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function active($id)
    {
        if (!Gate::allows('services_active')) {
            return abort(401);
        }
        Services::activeRecord($id);

        return redirect()->route('admin.services.index');
    }

}
