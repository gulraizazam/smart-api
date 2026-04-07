<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Warehouse\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class WarehouseController extends Controller
{
    public function __construct(
        private readonly WarehouseService $warehouseService,
    ) {}

    /**
     * Display a listing of brand.
     *
     * @return \never
     */
    public function index(): mixed
    {
        if (!Gate::allows('warehouse_manage')) {
            return abort(401);
        }

        return view('admin.warehouse.index');
    }

    /**
     * Display a listing of warehouse
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request): mixed
    {
        try {
            $records = $this->warehouseService->getDatatableData($request, Auth::User()->account_id);

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->handleException($e, 'WarehouseController');
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(): mixed
    {
        if (!Gate::allows('warehouse_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $data = $this->warehouseService->getCreateData(Auth::User()->account_id);

        return $this->successResponse('Record found', [
            'cities' => $data['cities'],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): mixed
    {
        if (!Gate::allows('warehouse_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $result = $this->warehouseService->validateAndCreate($request->all(), Auth::User()->account_id);

        if ($result['success']) {
            return $this->successResponse($result['message']);
        }

        if (isset($result['error'])) {
            return $this->successResponse($result['error'], false);
        }

        return $this->errorResponse('Something went wrong, please try again later.', 404);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id): mixed
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id): mixed
    {
        if (!Gate::allows('warehouse_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $result = $this->warehouseService->getEditData($id, Auth::User()->account_id);

        if (!$result['success']) {
            return view('error', compact('lead_statuse'));
        }

        return $this->successResponse('Record found', [
            'warehouse' => $result['warehouse'],
            'cities' => $result['cities'],
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id): mixed
    {
        if (!Gate::allows('warehouse_edit')) {
            return abort(401);
        }

        $result = $this->warehouseService->validateAndUpdate($request->all(), $id, Auth::User()->account_id);

        if ($result['success']) {
            return $this->successResponse($result['message']);
        }

        if (isset($result['error'])) {
            return $this->successResponse($result['error']);
        }

        return $this->errorResponse('Something went wrong, please try again later.', 404);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id): mixed
    {
        if (!Gate::allows('warehouse_destroy')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $result = $this->warehouseService->deleteWarehouse($id);

        if ($result['status']) {
            return $this->successResponse($result['message']);
        }

        return $this->errorResponse($result['message'], 400);
    }

    public function status(Request $request): mixed
    {
        if (!Gate::allows('warehouse_active')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $result = $this->warehouseService->changeStatus($request->id, $request->status);

        if ($result['success']) {
            return $this->successResponse($result['message']);
        }

        return $this->errorResponse($result['error'], 404);
    }
}
