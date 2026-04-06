<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Region\RegionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class RegionsController extends Controller
{
    public function __construct(
        private readonly RegionService $regionService,
    ) {}

    /**
     * Display a listing of regions
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\never
     */
    public function index()
    {
        if (! Gate::allows('regions_manage')) {
            return abort(401);
        }
        $data = $this->regionService->getIndexData(Auth::User()->id);

        return view('admin.regions.index', ['filters' => $data['filters']]);
    }

    /**
     * Display a listing of Regions.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {
            if (! Gate::allows('regions_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $records = $this->regionService->getDatatableData($request, Auth::User()->account_id, Auth::User()->id);

            return response()->json($records);
        } catch (\Exception $e) {
            dd($e);

            return $this->handleException($e, 'RegionsController');
        }
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! Gate::allows('regions_create')) {
            return abort(401);
        }

        return view('admin.regions.create', compact('city'));
    }

    /**
     * Save sort order of regions
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sortOrderSave(Request $request)
    {
        try {
            if (! Gate::allows('regions_sort')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            if ($this->regionService->saveSortOrder($request->item_ids)) {
                return $this->successResponse('Records are sorted Successfully!');
            }

            return $this->errorResponse('Something went Wrong! Records are not sorted', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'RegionsController');
        }
    }

    public function sortOrder()
    {
        if (! Gate::allows('regions_sort')) {
            return abort(401);
        }

        return view('admin.regions.sort');
    }

    /**
     * get records for sorting Regions
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sortOrderGet()
    {
        try {
            if (! Gate::allows('regions_sort')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }
            $regions = $this->regionService->getSortedRegions(Auth::User()->account_id);

            return $this->successResponse('Success', $regions);
        } catch (\Exception $e) {
            return $this->handleException($e, 'RegionsController');
        }
    }

    /**
     * Store a newly created region
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            if (! Gate::allows('regions_create')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->regionService->validateAndCreate($request->all(), Auth::User()->account_id);

            if ($result['success']) {
                return $this->successResponse($result['message']);
            }

            return $this->successResponse($result['error'], false, $result['errors']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'RegionsController');
        }
    }

    /**
     * Get data for Edit Region
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        try {
            if (! Gate::allows('regions_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->regionService->getEditData($id);

            if (! $result['success']) {
                return $this->errorResponse($result['error'], 404);
            }

            return $this->successResponse('Success', $result['region']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'RegionsController');
        }
    }

    /**
     * Update region
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            if (! Gate::allows('regions_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->regionService->validateAndUpdate($request->all(), $id, Auth::User()->account_id);

            if ($result['success']) {
                return $this->successResponse($result['message']);
            }

            return $this->successResponse($result['error'], false, $result['errors']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'RegionsController');
        }
    }

    /**
     * Remove/delete Region
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            if (! Gate::allows('regions_destroy')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $response = $this->regionService->deleteRegion($id);

            return $this->successResponse($response['message'], $response['status']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'RegionsController');
        }
    }

    /**
     * Change status of Region
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        try {
            if ($request->status == 0) {
                if (! Gate::allows('regions_inactive')) {
                    return $this->errorResponse('You are not authorized to access this resource.', 401);
                }
            } else {
                if (! Gate::allows('regions_active')) {
                    return $this->errorResponse('You are not authorized to access this resource.', 401);
                }
            }

            $response = $this->regionService->changeStatus($request->id, $request->status);

            return $this->successResponse($response['message'], $response['status']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'RegionsController');
        }
    }
}
