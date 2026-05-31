<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FileUploadTownRequest;
use App\Http\Requests\Admin\StoreUpdateTownRequest;
use App\Services\Town\TownService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TownController extends Controller
{
    public function __construct(
        private readonly TownService $townService,
    ) {}

    public function index(): \Illuminate\View\View
    {
        if (! Gate::allows('towns_manage')) {
            return abort(401);
        }

        return view('admin.towns.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $filters     = getFilters($request->all());
        $applyFilter = checkFilters($filters, 'towns');
        $accountId   = (int) Auth::user()->account_id;

        $records = ['data' => []];

        [$orderBy, $order] = getSortBy($request);

        if (hasFilter($filters, 'delete')) {
            $this->townService->processBulkDelete($filters, $accountId);
            $records['status']  = true;
            $records['message'] = 'Records has been deleted successfully!';
        }

        $total = $this->townService->getTotalRecords($request, $accountId, $applyFilter);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $total);

        $towns = $this->townService->getDatatableRows($request, $iDisplayStart, $iDisplayLength, $accountId, $applyFilter);

        if ($towns) {
            $records['data'] = $towns;
            $records['meta'] = [
                'field'   => $orderBy,
                'page'    => $page,
                'pages'   => $pages,
                'perpage' => $iDisplayLength,
                'total'   => $total,
                'sort'    => $order,
            ];
            $records['permissions'] = [
                'edit'     => Gate::allows('towns_edit'),
                'delete'   => Gate::allows('towns_destroy'),
                'active'   => Gate::allows('towns_active'),
                'inactive' => Gate::allows('towns_inactive'),
            ];
        }

        $records['active_filters'] = $this->townService->getActiveFilters();
        $records['filter_values']  = [
            'cities' => $this->townService->getCitiesForFilter($accountId),
            'status' => config('constants.status'),
        ];

        return response()->json($records);
    }

    public function create(): JsonResponse
    {
        if (! Gate::allows('towns_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        return $this->successResponse('Record found', [
            'cities' => $this->townService->getCitiesForCreate((int) Auth::user()->account_id),
        ]);
    }

    public function store(StoreUpdateTownRequest $request): JsonResponse
    {
        if (! Gate::allows('towns_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        if ($this->townService->create($request, (int) Auth::user()->account_id)) {
            return $this->successResponse('Record has been created successfully.');
        }

        return $this->errorResponse('Something went wrong, please try again later.', 404);
    }

    public function show(int $id): void
    {
        //
    }

    public function edit(int $id): JsonResponse
    {
        if (! Gate::allows('towns_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $town = $this->townService->getForEdit($id);

        if (! $town) {
            return $this->errorResponse('Resource not found.', 401);
        }

        return $this->successResponse('Record found', [
            'cities' => $this->townService->getCitiesForEdit((int) Auth::user()->account_id),
            'town'   => $town,
        ]);
    }

    public function update(StoreUpdateTownRequest $request, int $id): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('towns_edit')) {
            return $this->errorResponse('You are not authorized to perform this action.', 403);
        }

        if ($this->townService->update($id, $request, (int) Auth::user()->account_id)) {
            return $this->successResponse('Record has been updated successfully.');
        }

        return $this->errorResponse('Something went wrong, please try again later.', 404);
    }

    public function destroy(int $id): JsonResponse
    {
        if (! Gate::allows('towns_destroy')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $response = $this->townService->delete($id);

        return $response['status']
            ? $this->successResponse($response['message'])
            : $this->errorResponse($response['message'], 400);
    }

    public function status(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('towns_active')) {
            return $this->errorResponse('You are not authorized to perform this action.', 403);
        }

        $response = $this->townService->toggleStatus((int) $request->id, (int) $request->status);

        if ($response) {
            return $this->successResponse('Status has been changed successfully.');
        }

        return $this->errorResponse('Resource not found.', 404);
    }

    public function importTowns(Request $request): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        if (! Gate::allows('towns_import')) {
            flash('You are not authorized to access this resource.')->error()->important();

            return redirect()->route('admin.towns.index');
        }

        return view('admin.towns.import');
    }

    public function uploadLeads(FileUploadTownRequest $request): \Illuminate\Http\RedirectResponse
    {
        if (! Gate::allows('towns_import')) {
            flash('You are not authorized to access this resource.')->error()->important();

            return redirect()->route('admin.Towns.index');
        }

        if (! $request->hasfile('towns_file')) {
            return redirect()->route('admin.towns.import');
        }

        try {
            $this->townService->importFromFile($request, (int) Auth::user()->account_id);

            return redirect()->route('admin.towns.index');
        } catch (\Exception $e) {
            flash($e->getMessage())->error()->important();

            return redirect()->route('admin.towns.import');
        }
    }
}
