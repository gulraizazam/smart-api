<?php

declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Validator;
use App\Models\Locations;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Services\Location\LocationService;

class LocationsController extends Controller
{
    public $success;

    public $error;

    public $unauthorized;

    public function __construct(
        protected readonly LocationService $locationService,
    ) {
    }

    /**
     * Display a listing of Location.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): mixed
    {
        if (! Gate::allows('locations_manage')) {
            return abort(401);
        }

        return view('admin.locations.index');
    }

    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request): mixed
    {
        $accountId = Auth::User()->account_id;
        $filters = getFilters($request->all());

        $records = $this->locationService->processBulkDelete($filters, $accountId);

        [$orderBy, $order] = getSortBy($request);

        $apply_filter = checkFilters($filters, 'locations');

        // Get Total Records
        $iTotalRecords = Locations::getTotalRecords($request, $accountId, $apply_filter);

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $Locations = Locations::getRecords($request, $iDisplayStart, $iDisplayLength, $accountId, $apply_filter);

        $records = $this->locationService->buildDatatableRows(
            $Locations,
            $accountId,
            $orderBy,
            $order,
            $page,
            $pages,
            $iDisplayLength,
            $iTotalRecords,
            $records
        );

        return response()->json($records);
    }

    /**
     * Show the form for creating new Location.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(): mixed
    {
        if (! Gate::allows('locations_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $data = $this->locationService->getCreateData(Auth::User()->account_id);

        return $this->successResponse('Record found', $data);
    }

    /**
     * Store a newly created Location in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): mixed
    {
        if (! Gate::allows('locations_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $validator = $this->verifyFields($request);
        if ($validator->fails()) {

            return $this->successResponse($validator->messages()->first(), false);
        }
        if ($location = Locations::createRecord($request, Auth::User()->account_id)) {
            $this->locationService->handlePostCreation($location, $request->all(), Auth::User()->account_id);

            return $this->successResponse('Record has been created successfully.');
        } else {
            return $this->errorResponse('Something went wrong, please try again later.', 404);
        }
    }

    /**
     * Validate form fields
     *
     * @return Validator $validator;
     */
    protected function verifyFields(Request $request): mixed
    {
        return $validator = \Validator::make($request->all(), [
            'name' => 'required',
            'fdo_name' => 'required',
            'fdo_phone' => 'required',
            'address' => 'required',
            'google_map' => 'required',
            'city_id' => 'required',
            'ntn' => 'required',
            'stn' => 'required',
            /*'ntn' => ['required', 'regex:/^([0-9]|\.|\+|\*|\-|\_|\#)*$/'],
            'stn' => ['required', 'regex:/^([0-9]|\.|\+|\*|\-|\_|\#)*$/'],*/
        ]);
    }

    /**
     * Show the form for editing Location.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id): mixed
    {
        if (! Gate::allows('locations_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }
        $data = $this->locationService->getEditData($id, Auth::User()->account_id);
        if (! $data['found']) {
            return view('error', compact('lead_statuse'));
        }
        unset($data['found']);

        return $this->successResponse('Record found', $data);
    }

    /**
     * Update Location in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): mixed
    {
        if (! Gate::allows('locations_edit')) {
            return abort(401);
        }
        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return $this->successResponse($validator->messages()->first());
        }
        if ($location = Locations::updateRecord($id, $request, Auth::User()->account_id)) {
            $this->locationService->handlePostUpdate($location, $request->all(), Auth::User()->account_id);

            return $this->successResponse('Record has been updated successfully.');

        } else {

            return $this->errorResponse('Something went wrong, please try again later.', 404);
        }
    }

    /**
     * Remove Location from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): mixed
    {
        if (! Gate::allows('locations_destroy')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }
        $result = $this->locationService->deleteLocation($id);

        if ($result['status']) {
            return $this->successResponse($result['message']);
        }

        return $this->errorResponse($result['message'], 400);
    }

    public function status(Request $request): mixed
    {
        if (! Gate::allows('locations_active')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $response = $this->locationService->changeStatus($request->id, $request->status);

        if ($response) {
            return $this->successResponse('Status has been changed successfully.');
        }

        return $this->errorResponse('Resource not found.', 404);

    }

    /**
     * function for index Sort Order.
     */
    public function getSortOrder(): mixed
    {
        if (! Gate::allows('locations_sort')) {
            return abort(401);
        }

        return view('admin.locations.Sort');
    }

    public function sortorder(): mixed
    {
        if (! Gate::allows('locations_sort')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }
        $locations = $this->locationService->getSortableLocations(Auth::User()->account_id);

        return $this->successResponse('Success', $locations);
    }

    /**
     * function for Sort Order.
     */
    public function sortorder_save(Request $request): mixed
    {
        if (! Gate::allows('locations_sort')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $itemIDs = $request->item_ids;
        if ($this->locationService->saveSortOrder($itemIDs ?? [])) {
            return $this->successResponse('Records are sorted Successfully!');
        } else {
            return $this->errorResponse('Data Not Sort', 404);
        }
    }

    /**
     * Store a newly created Location and checked attribute exists or not.
     *
     * @return \Illuminate\Http\Response
     */
    public function verify(Request $request): mixed
    {
        if (! Gate::allows('locations_create')) {
            return abort(401);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->all(),
            ]);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Record has been verified successfully.',
        ]);
    }

    /**
     * updated Location and verify edit attribute exists or not
     *
     * @return \Illuminate\Http\Response
     */
    public function verify_edit(Request $request): mixed
    {
        if (! Gate::allows('locations_create')) {
            return abort(401);
        }

        $validator = $this->verifyFields($request);
        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->all(),
            ]);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Record has been verified successfully.',
        ]);
    }
    public function getServices(Request $request): mixed
    {
        $data = $this->locationService->getServicesForLocation($request->id, Auth::User()->account_id);

        return $this->successResponse('Success', $data);
    }
}
