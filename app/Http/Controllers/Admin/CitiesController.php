<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\City\CityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CitiesController extends Controller
{
    public function __construct(
        private readonly CityService $cityService,
    ) {}

    /**
     * Display a listing of Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): mixed
    {
        if (! Gate::allows('cities_manage')) {
            return abort(401);
        }
        $data = $this->cityService->getIndexData(Auth::User()->id);

        return view('admin.cities.index', [
            'regions' => $data['regions'],
            'filters' => $data['filters'],
        ]);
    }

    /**
     * Display a listing of cities
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request): mixed
    {
        try {
            if (! Gate::allows('cities_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $records = $this->cityService->getDatatableData($request, Auth::User()->account_id);

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->handleException($e, 'CitiesController');
        }
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(): mixed
    {
        if (! Gate::allows('cities_create')) {
            return abort(401);
        }

        $data = $this->cityService->getCreateData();

        return view('admin.cities.create', ['regions' => $data['regions']]);
    }

    public function sortOrderSave(Request $request): mixed
    {
        try {
            if (! Gate::allows('cities_sort')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            if ($this->cityService->saveSortOrder($request->item_ids)) {
                return $this->successResponse('Records are sorted Successfully!', null, 200);
            }

            return $this->errorResponse('Something went Wrong! Records are not sorted', 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'CitiesController');
        }
    }

    public function sortOrder(): mixed
    {
        if (! Gate::allows('cities_sort')) {
            return abort(401);
        }

        return view('admin.cities.sort');
    }

    /**
     * get records for sorting Cities
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sortOrderGet(): mixed
    {
        try {
            if (! Gate::allows('cities_sort')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }
            $cities = $this->cityService->getSortedCities(Auth::User()->account_id);

            return $this->successResponse('Success', $cities, 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'CitiesController');
        }
    }

    /**
     * Store a newly created City.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): mixed
    {
        try {
            if (! Gate::allows('cities_create')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->cityService->validateAndCreate($request->all(), Auth::User()->account_id);

            if ($result['success']) {
                return $this->successResponse($result['message'], null, 200);
            }

            return $this->errorResponse($result['error'], 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'CitiesController');
        }
    }

    /**
     * Get Data for editing city
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id): mixed
    {
        try {
            if (! Gate::allows('cities_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->cityService->getEditData($id);

            if (! $result['success']) {
                return $this->errorResponse($result['error'], 200);
            }

            return $this->successResponse('Success', $result['city'], 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'CitiesController');
        }
    }

    /**
     * Update City
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id): mixed
    {
        try {
            if (! Gate::allows('cities_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->cityService->validateAndUpdate($request->all(), $id, Auth::User()->account_id);

            if ($result['success']) {
                return $this->successResponse($result['message'], null, 200);
            }

            return $this->errorResponse($result['error'], 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'CitiesController');
        }
    }

    /**
     * Remove City.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): mixed
    {
        try {
            if (! Gate::allows('cities_destroy')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $response = $this->cityService->deleteCity($id);

            if ($response['status']) {
                return $this->successResponse($response['message'], null, 200);
            }

            return $this->errorResponse($response['message'], 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'CitiesController');
        }
    }

    /**
     * Change status of City
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request): mixed
    {
        try {
            if ($request->status == 0) {
                if (! Gate::allows('cities_inactive')) {
                    return $this->errorResponse('You are not authorized to access this resource.', 401);
                }
            } else {
                if (! Gate::allows('cities_active')) {
                    return $this->errorResponse('You are not authorized to access this resource.', 401);
                }
            }

            $response = $this->cityService->changeStatus((int) $request->id, (int) $request->status);

            if ($response['status']) {
                return $this->successResponse($response['message'], null, 200);
            }

            return $this->errorResponse($response['message'], 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'CitiesController');
        }
    }
}
