<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSettingRequest;
use App\Http\Requests\Admin\UpdateSettingRequest;
use App\Http\Resources\SettingEditResource;
use App\Http\Resources\SettingResource;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SettingsController extends Controller
{


    public function __construct(
        private readonly SettingsService $settingsService,
    ) {


    }

    public function index(): View
    {
        if (! Gate::allows('settings_manage')) {
            abort(401);
        }

        $filters = $this->settingsService->getActiveFilters();

        return view('admin.settings.index', compact('filters'));
    }

    public function datatable(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('settings_manage')) {
                return $this->errorResponse('You are not authorized to view settings.', 403);
            }

            $filters = getFilters($request->all());
            $applyFilter = checkFilters($filters, 'settings');
            [$orderBy, $order] = getSortBy($request);

            $params = [
                'name' => $filters['name'] ?? null,
                'data' => $filters['data'] ?? null,
                'apply_filter' => $applyFilter,
                'order_by' => $orderBy,
                'order' => $order,
            ];

            $iTotalRecords = $this->settingsService->getDatatableCount($params);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $result = $this->settingsService->getDatatableData([
                ...$params,
                'offset' => $iDisplayStart,
                'limit' => $iDisplayLength,
            ]);

            return response()->json([
                'data' => SettingResource::collection($result['data']),
                'permissions' => $this->settingsService->getPermissions(),
                'meta' => [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => $iDisplayLength,
                    'total' => $iTotalRecords,
                    'sort' => $order,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'SettingsController');
        }
    }

    public function create(): View
    {
        if (! Gate::allows('settings_manage')) {
            abort(401);
        }

        $setting = new \stdClass();
        $setting->id = null;

        return view('admin.settings.create', compact('setting'));
    }

    public function store(StoreSettingRequest $request): JsonResponse
    {
        if (! Gate::allows('settings_manage')) {
            abort(401);
        }

        $record = $this->settingsService->create($request->validated());

        if ($record) {
            flash('Record has been created successfully.')->success()->important();

            return response()->json([
                'status' => true,
                'message' => 'Record has been created successfully.',
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Something went wrong, please try again later.',
        ]);
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (! Gate::allows('settings_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $setting = $this->settingsService->find($id);

            if (! $setting) {
                return $this->errorResponse('No Data Found', 404);
            }

            return $this->successResponse('Success', (new SettingEditResource($setting))->resolve(),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'SettingsController');
        }
    }

    public function update(UpdateSettingRequest $request, int $id): JsonResponse
    {
        try {
            if (! Gate::allows('settings_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            if (! $this->settingsService->validateDiscountRange($id, (float) $request->input('min', 0), (float) $request->input('max', 0))) {
                return $this->errorResponse('Min value is greater than Max value.', 404);
            }

            $record = $this->settingsService->update($id, $request->validated());

            return $record
                ? $this->successResponse('Record has been updated successfully.')
                : $this->errorResponse('Something went wrong, please try again later.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'SettingsController');
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        if (! Gate::allows('settings_manage')) {
            abort(401);
        }

        $result = $this->settingsService->delete($id);

        flash($result['message'])->{$result['success'] ? 'success' : 'error'}()->important();

        return redirect()->route('admin.settings.index');
    }

    public function inactive(int $id): RedirectResponse
    {
        if (! Gate::allows('settings_manage')) {
            abort(401);
        }

        $setting = $this->settingsService->toggleActive($id, false);

        if (! $setting) {
            flash('Resource not found.')->error()->important();

            return redirect()->route('admin.settings.index');
        }

        flash('Record has been inactivated successfully.')->success()->important();

        return redirect()->route('admin.settings.index');
    }

    public function active(int $id): RedirectResponse
    {
        if (! Gate::allows('settings_manage')) {
            abort(401);
        }

        $setting = $this->settingsService->toggleActive($id, true);

        if (! $setting) {
            flash('Resource not found.')->error()->important();

            return redirect()->route('admin.settings.index');
        }

        flash('Record has been activated successfully.')->success()->important();

        return redirect()->route('admin.settings.index');
    }
}
