<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUpdateResourceRequest;
use App\Services\Resource\ResourceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ResourcesController extends Controller
{
    public function __construct(
        private readonly ResourceService $resourceService,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): mixed
    {
        if (! Gate::allows('resources_manage')) {
            return abort('401');
        }

        return view('admin.resources.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(): mixed
    {
        if (! Gate::allows('resources_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {

            $data = $this->resourceService->getCreateData();

            return $this->successResponse('Record found', $data);

        } catch (\Exception $e) {
            return $this->handleException($e, 'ResourcesController');
        }
    }

    /**
     * get the machine type against location id.
     *
     * @return \Illuminate\Http\Response
     */
    public function get_machinetype(Request $request): mixed
    {
        if (! Gate::allows('resources_create')) {
            return abort('401');
        }

        $result = $this->resourceService->getMachineTypesByLocation((int) $request->id);

        if ($result['status']) {
            $machinetypes = $result['machinetypes'];

            return response()->json([
                'status' => true,
                'd' => view('admin.resources.services', compact('machinetypes'))->render(),
            ]);
        } else {
            return response()->json([
                'status' => false,
            ]);
        }
    }

    /*That Function is not in use that function give the assigned services of center if your select center */
    /**
     * get the service against location id.
     *
     * @return \Illuminate\Http\Response
     */
    public function get_service(Request $request): mixed
    {
        if (! Gate::allows('resources_create')) {
            return abort('401');
        }

        $result = $this->resourceService->getServicesByLocation((int) $request->id);

        if ($result['status']) {
            $Services = $result['Services'];
            $status_for_all = $result['status_for_all'];

            return response()->json([
                'status' => true,
                'd' => view('admin.resources.services', compact('Services', 'status_for_all'))->render(),
            ]);
        } else {
            return response()->json([
                'status' => false,
            ]);
        }
    }

    /*End*/
    /*
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreUpdateResourceRequest $request): mixed
    {
        if (! Gate::allows('resources_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {

            if ($this->resourceService->store($request->all(), \Illuminate\Support\Facades\Auth::User()->account_id)) {
                return $this->successResponse('Record has been created successfully.');
            }

            return $this->errorResponse('Something went wrong, please try again later.', 404);

        } catch (\Exception $e) {
            return $this->handleException($e, 'ResourcesController');
        }
    }

    /**
     * Display the resources in datatable.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request): mixed
    {
        try {
            $records = $this->resourceService->getDatatableData($request->all(), \Illuminate\Support\Facades\Auth::User()->account_id);

            return response()->json($records);

        } catch (\Exception $e) {
            return $this->handleException($e, 'ResourcesController');
        }
    }

    /**
     * Inactive Record from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request): mixed
    {
        if (! Gate::allows('resources_active')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }
        try {

            $response = $this->resourceService->changeStatus((int) $request->id, (int) $request->status);

            if ($response) {
                return $this->successResponse('Status has been changed successfully.');
            }

            return $this->errorResponse('Resource not found.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ResourcesController');
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id): mixed
    {
        if (! Gate::allows('resources_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $data = $this->resourceService->getEditData($id);

        if (! $data['resource']) {
            return $this->errorResponse('Resource not found', 404);
        }

        return $this->successResponse('Record found', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(StoreUpdateResourceRequest $request, int $id): mixed
    {
        if (! Gate::allows('resources_edit')) {
            return abort('401');
        }

        if ($this->resourceService->update($id, $request->all(), \Illuminate\Support\Facades\Auth::User()->account_id)) {
            flash('Record has been updated successfully.')->success()->important();

            return response()->json([
                'status' => 1,
                'message' => 'Record has been updated successfully.',
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => 'Something went wrong, please try again later.',
            ]);
        }
    }

    /*
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id): mixed
    {
        if (! Gate::allows('resources_destroy')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }
        try {

            $response = $this->resourceService->destroy($id);

            if ($response['status']) {
                return $this->successResponse($response['message']);
            }

            return $this->errorResponse($response['message'], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ResourcesController');
        }
    }
}
