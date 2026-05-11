<?php

declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\GroupsTree;
use App\Helpers\ServiceHelper;
use App\Helpers\NodesTree;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUpdateServiceRequest;
use App\Models\Appointments;
use App\Models\Services;
use PDF;
use App\Models\TaxTreatmentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ServicesController extends Controller
{
    /**
     * Display a listing of Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): \Illuminate\View\View
    {
        if (! Gate::allows('services_manage')) {
            return abort(401);
        }

        return view('admin.services.index');
    }

    public function datatable(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $filters = getFilters($request->all());
            $records = [];
            $records['data'] = [];
            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $Locations = Services::getBulkData($ids);
                if ($Locations) {
                    foreach ($Locations as $Location) {
                        // Check if child records exists or not, If exist then disallow to delete it.
                        if (! Services::isChildExists($Location->id, Auth::user()->account_id)) {
                            $Location->delete();
                        }
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }
            [$orderBy, $order] = getSortBy($request);
            // Get Total Records
            $iTotalRecords = Services::getTotalRecords($request, Auth::user()->account_id);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);
            $Services = GeneralFunctions::servicesList($request, $iTotalRecords);
            $records = $this->getExtraData($records);
            if (! empty($Services)) {
                $records['data'] = $Services;
                $records['permissions'] = [
                    'edit' => Gate::allows('services_edit'),
                    
                    'delete' => Gate::allows('services_destroy'),
                    'active' => Gate::allows('services_active'),
                    'inactive' => Gate::allows('services_inactive'),
                    'create' => Gate::allows('services_create'),
                    'sort' => Gate::allows('services_edit'),
                    'duplicate' => Gate::allows('services_duplicate'),
                    'detail'=> Gate::allows('services_detail'),
                ];
                $records['meta'] = [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => 100,
                    'total' => $iTotalRecords,
                    'sort' => $order,
                ];

            } //end

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ServicesController');
        }
    }
    public function getSortOrder(): \Illuminate\View\View
    {
        if (! Gate::allows('services_edit')) {
            return abort(401);
        }

        return view('admin.services.Sort');
    }
    public function sortOrderGet(): \Illuminate\Http\JsonResponse
    {
        try {
            if (! Gate::allows('services_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $parents = Services::where('slug', '!=', 'all')
                ->where(function ($q) {
                    $q->whereNull('parent_id')->orWhere('parent_id', 0);
                })
                ->orderBy('sort_no', 'asc')
                ->get(['id', 'name', 'parent_id', 'sort_no']);

            $parentIds = $parents->pluck('id')->all();
            $childrenByParent = Services::whereIn('parent_id', $parentIds)
                ->orderBy('sort_number', 'ASC')
                ->get(['id', 'name', 'parent_id', 'sort_number'])
                ->groupBy('parent_id');

            $grouped = [];
            foreach ($parents as $parent) {
                $grouped[] = [
                    'id' => $parent->id,
                    'name' => $parent->name,
                    'children' => $childrenByParent->get($parent->id, collect())->values()->toArray(),
                ];
            }

            return $this->successResponse('Success', $grouped);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ServicesController');
        }
    }
    public function sortOrderSave(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if (! Gate::allows('services_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $parentId = (int) $request->parent_id;
            $itemIDs = $request->item_ids;

            if (empty($itemIDs) || !is_array($itemIDs)) {
                return $this->errorResponse('No items to sort.', 404);
            }

            $ids = array_map('intval', $itemIDs);
            $cases = [];
            $bindings = [];
            foreach ($ids as $sortNo => $id) {
                $cases[] = 'WHEN id = ? THEN ?';
                $bindings[] = $id;
                $bindings[] = $sortNo;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $bindings = array_merge($bindings, $ids, [$parentId]);

            \DB::update(
                'UPDATE services SET sort_number = CASE ' . implode(' ', $cases) . ' END WHERE id IN (' . $placeholders . ') AND parent_id = ?',
                $bindings
            );

            return $this->successResponse('Sort order saved!');
        } catch (\Exception $e) {
            return $this->handleException($e, 'ServicesController');
        }
    }
    public function categorySortOrderSave(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if (! Gate::allows('services_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $categoryIds = $request->input('category_ids', []);

            if (empty($categoryIds) || !is_array($categoryIds)) {
                return $this->errorResponse('No categories to sort.', 404);
            }

            $ids = array_map('intval', $categoryIds);
            $cases = [];
            $bindings = [];
            foreach ($ids as $sortNo => $id) {
                $cases[] = 'WHEN id = ? THEN ?';
                $bindings[] = $id;
                $bindings[] = $sortNo;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $bindings = array_merge($bindings, $ids);

            \DB::update(
                'UPDATE services SET sort_no = CASE ' . implode(' ', $cases) . ' END WHERE id IN (' . $placeholders . ') AND (parent_id IS NULL OR parent_id = 0)',
                $bindings
            );

            return $this->successResponse('Category order saved!');
        } catch (\Exception $e) {
            return $this->handleException($e, 'ServicesController');
        }
    }

    private function getExtraData($records = []): array
    {

        $filters = Filters::all(Auth::user()->id, 'services');

        /* Create Nodes with Parents */
        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::user()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $records['filter_values'] = [
            'services' => $Services,
            'status' => config('constants.status'),
        ];

        $records['active_filters'] = $filters;

        return $records;
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('services_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $service = new \stdClass();
        $service->duration = null;
        $service->parent_id = null;

        $tax_treatment_types = TaxTreatmentType::get();

        $select_tax_treatment_type = 1;

        $Services = GeneralFunctions::parentServices();

        $durations = ServiceHelper::getDurations();

        return $this->successResponse('Record found', [
            'parent_services' => $Services,
            'service' => $service,
            'durations' => $durations,
            'tax_treatment_types' => $tax_treatment_types,
            'select_tax_treatment_type' => $select_tax_treatment_type,
        ]);
    }

    /**
     * Store a newly created Permission in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreUpdateServiceRequest $request): \Illuminate\Http\JsonResponse
    {

        if (! Gate::allows('services_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        if (Services::createRecord($request, Auth::user()->account_id)) {

            return $this->successResponse('Record has been created successfully.');
        } else {
            return $this->errorResponse('Something went wrong, please try again later.', 404);
        }
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('services_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $service = Services::findOrFail($id);

        /*$BaseServices = Services::getGroupsActiveOnly();

        if ($BaseServices) {
            $Services = GroupsTree::buildOptions(GroupsTree::buildTree($BaseServices->toArray(), 0, $service->id), $service->parent_id);
        } else {
            $Services = array();
        }*/

        $tax_treatment_types = TaxTreatmentType::get();

        if ($service->tax_treatment_type_id == 0) {
            $select_tax_treatment_type = 1;
        } else {
            $select_tax_treatment_type = $service->tax_treatment_type_id;
        }

        $Services = GeneralFunctions::parentServices();

        $durations = ServiceHelper::getDurations();

        return $this->successResponse('Record found', [
            'parent_services' => $Services,
            'service' => $service,
            'durations' => $durations,
            'tax_treatment_types' => $tax_treatment_types,
            'select_tax_treatment_type' => $select_tax_treatment_type,
        ]);
    }

    /**
     * Display the specified service.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, int $id): \Illuminate\View\View
    {
        if (! Gate::allows('services_manage')) {
            return abort(401);
        }

        $service = Services::findOrFail($id);

        // If AJAX request, return JSON with description only
        if ($request->ajax() || $request->wantsJson()) {
            return $this->successResponse('Record found', [
                'description' => $service->description,
            ]);
        }

        // Get parent service if exists
        $parent = null;
        if ($service->parent_id) {
            $parent = Services::find($service->parent_id);
        }

        // Get child services if this is a parent
        $children = Services::where('parent_id', $id)->get();

        // Get tax treatment type
        $taxTreatmentType = TaxTreatmentType::find($service->tax_treatment_type_id);

        return view('admin.services.show', compact('service', 'parent', 'children', 'taxTreatmentType'));
    }

    /**
     * Get service data for duplication.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function duplicate(int $id): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('services_duplicate')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $service = Services::findOrFail($id);

        $tax_treatment_types = TaxTreatmentType::get();

        if ($service->tax_treatment_type_id == 0) {
            $select_tax_treatment_type = 1;
        } else {
            $select_tax_treatment_type = $service->tax_treatment_type_id;
        }

        $Services = GeneralFunctions::parentServices();

        $durations = ServiceHelper::getDurations();

        return $this->successResponse('Record found', [
            'parent_services' => $Services,
            'service' => $service,
            'durations' => $durations,
            'tax_treatment_types' => $tax_treatment_types,
            'select_tax_treatment_type' => $select_tax_treatment_type,
        ]);
    }

    /**
     * Store duplicated service.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeDuplicate(StoreUpdateServiceRequest $request): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('services_duplicate')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        if (Services::createRecord($request, Auth::user()->account_id)) {
            return $this->successResponse('Service has been duplicated successfully.');
        } else {
            return $this->successResponse('Something went wrong, please try again later.');
        }
    }

    /**
     * Update Permission in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(StoreUpdateServiceRequest $request, int $id): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('services_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }
        $service = Services::findOrFail($id);
        if ($service->parent_id > 0 && $request->parent_id == 0) {
            $check_appointment = Appointments::whereServiceId($id)->count();
            if ($check_appointment > 0) {
                return $this->errorResponse('Service can not be updated due to one or more treatments are associated with it.', 500);
            }
        }
        if (
            Services::isChildExists($id, Auth::user()->account_id) &&
            ($service->parent_id != $request->get('parent_id') || $service->end_node != (int) $request->get('end_node'))
        ) {
            return $this->errorResponse('Parent Service can not be changed due to one or more services are associated with it.', 404);
        }

        if (Services::updateRecord($id, $request, Auth::user()->account_id)) {

            return $this->successResponse('Record has been updated successfully.');
        } else {
            return $this->successResponse('Something went wrong, please try again later.');
        }
    }

    /**
     * Remove Permission from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('services_destroy')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $result = Services::deleteRecord($id);

        if ($result['status']) {
            return $this->successResponse($result['message']);
        }

        return $this->errorResponse($result['message'], 400);
    }

    /**
     * Inactive Record from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request): \Illuminate\Http\JsonResponse
    {
        try {

            if (! Gate::allows('services_active') && ! Gate::allows('services_inactive')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }
            $checkService = Services::find((int) $request->id);

        if (!$checkService) {
            return $this->errorResponse('Service not found.', 500);
        }

        // If the request is to deactivate (status = 0) AND service is a parent
        if ($request->status == 0 && $checkService->parent_id == 0) {
            // Check if any child is active
            $activeChildExists = Services::where('parent_id', $checkService->id)
                ->where('active', 1)
                ->exists();

            if ($activeChildExists) {
                return $this->errorResponse('This parent has active child services. Please deactivate them first.', 500);
            }
        }

            if ($request->status == 1) {
                $response = Services::activeRecord((int) $request->id);
            } else {
                $response = Services::inactiveRecord((int) $request->id);
            }

            if ($response) {
                return $this->successResponse('Status has been changed successfully.');
            }

            return $this->errorResponse('Resource not found.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ServicesController');
        }

    }

    public function GetColor(Request $request): \Illuminate\Http\JsonResponse
    {
        if ($request->service != 0) {
            $service = Services::where('id', $request->service)->first();

            return response()->json(['color' => $service->color]);
        } else {
            return response()->json(['color' => '#000000']);
        }
    }
    public function exportPdf(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        // Server-side authority — the SPA also hides the button when this
        // gate is missing, but a 403 here is the actual security boundary.
        // Permission seeded by PermissionSeeder (services_export, parent_id=46).
        if (! \Illuminate\Support\Facades\Gate::allows('services_export')) {
            abort(403);
        }

        $services = Services::getTreeStructure();

        $pdf = PDF::loadView('admin.services.pdf', compact('services'));

        return $pdf->download('services-tree.pdf');
    }

    // Helper method to flatten tree for display
    private function flattenTree($services, $level = 0): array
    {
        $flattened = [];
        
        foreach ($services as $service) {
            $service->level = $level;
            $flattened[] = $service;
            
            if ($service->children->count() > 0) {
                $children = $this->flattenTree($service->children, $level + 1);
                $flattened = array_merge($flattened, $children);
            }
        }
        
        return $flattened;
    }

    // Alternative method using flattened approach
    public function exportPdfFlattened(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $services = Services::getTreeStructure();
        $flattenedServices = $this->flattenTree($services);
        
        $pdf = PDF::loadView('services.pdf-flattened', compact('flattenedServices'));
        
        return $pdf->download('services-tree.pdf');
    }
}
