<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CentreTarget\CentreTargetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CentreTargetsController extends Controller
{
    public function __construct(
        private readonly CentreTargetService $service,
    ) {}

    /**
     * Display a listing of Centre target.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): \Illuminate\View\View
    {
        if (! Gate::allows('centre_targets_manage')) {
            return abort(401);
        }

        return view('admin.centre_targets.index');
    }

    /**
     * Display a listing of Centre target.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request): \Illuminate\Http\JsonResponse
    {
        try {

            $records = $this->service->getDatatableData($request);

            return response()->json($records);

        } catch (\Exception $e) {
            return $this->handleException($e, 'CentreTargetsController');
        }
    }

    /*
     * Show the form for creating new target.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('centre_targets_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {

            $data = $this->service->getCreateData();

            return $this->successResponse('Record found', $data, 200);

        } catch (\Exception $e) {
            return $this->handleException($e, 'CentreTargetsController');
        }
    }

    /**
     * Load target centre
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function leadtargetcentre(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $this->service->loadTargetCentre($request);

        return $this->successResponse('Record found', $data, 200);
    }

    /*
     * Store the centre target
     */

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('centre_targets_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {

            $result = $this->service->storeRecord($request);

            if ($result['success']) {
                return $this->successResponse($result['message'], null, 200);
            }

            return $this->errorResponse($result['message'], 200);

        } catch (\Exception $e) {
            return $this->handleException($e, 'CentreTargetsController');
        }
    }

    /**
     * Show the form for editing center target.
     *
     * @param  int  $id ,$request
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id): \Illuminate\Http\JsonResponse
    {

        if (! Gate::allows('centre_targets_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $result = $this->service->getEditData($id);

        if (! isset($result['success']) || ! $result['success']) {
            return $this->errorResponse($result['message'], 200);
        }

        return $this->successResponse('Record found.', [
            'center_target' => $result['center_target'],
            'months' => $result['months'],
            'years' => $result['years'],
        ], 200);
    }

    /**
     * Update Centre target in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('centre_targets_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $result = $this->service->updateRecord($request, $id);

        if ($result['success']) {
            return $this->successResponse($result['message'], null, 200);
        }

        return $this->errorResponse($result['message'], 200);
    }

    /**
     * Show details of center target.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function display(int $id): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('centre_targets_manage')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $data = $this->service->getDisplayData($id);

        return $this->successResponse('Record Found.', $data, 200);

    }

    /**
     * Remove StaffTarget from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id, Request $request): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('centre_targets_destroy')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }
        try {

            $this->service->deleteRecord($id);

            return $this->successResponse('Record has been deleted successfully.', null, 200);

        } catch (\Exception $e) {
            return $this->handleException($e, 'CentreTargetsController');
        }
    }

    /**
     * Get all system-wide targets.
     */
    public function getSystemTargets(): \Illuminate\Http\JsonResponse
    {
        try {
            $targets = $this->service->getSystemTargets();

            return $this->successResponse('System targets loaded', $targets, 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    /**
     * Save a single system-wide target (immediate save).
     */
    public function saveSystemTarget(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if (! Gate::allows('centre_targets_edit')) {
                return $this->errorResponse('You are not authorized to save targets.', 403);
            }

            $request->validate([
                'key' => 'required|string|max:50',
                'value' => 'required|numeric|min:0',
            ]);

            $this->service->saveSystemTarget($request->key, (float) $request->value);

            return $this->successResponse('Target saved', null, 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }
}
