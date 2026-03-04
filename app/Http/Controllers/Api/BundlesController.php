<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Bundle\BundleService;
use App\Exceptions\BundleException;
use App\HelperModule\ApiHelper;
use App\Http\Requests\Bundle\StoreBundleRequest;
use App\Http\Requests\Bundle\UpdateBundleRequest;
use App\Http\Requests\Bundle\UpdateBundleStatusRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BundlesController extends Controller
{
    protected BundleService $bundleService;
    protected int $success;
    protected int $error;
    protected int $unauthorized;

    public function __construct(BundleService $bundleService)
    {
        $this->bundleService = $bundleService;
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Get bundles datatable data
     */
    public function datatable(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('packages_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $records = $this->bundleService->getDatatableRecords($request);

            return response()->json($records);

        } catch (BundleException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false, $e->getErrors());
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Store a new simple bundle
     */
    public function store(StoreBundleRequest $request): JsonResponse
    {
        try {
            $this->bundleService->createBundle($request->validated());

            return ApiHelper::apiResponse($this->success, 'Bundle has been created successfully.');

        } catch (BundleException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false, $e->getErrors());
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get bundle data for editing
     */
    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('packages_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $data = $this->bundleService->getBundleForEdit($id);

            return ApiHelper::apiResponse($this->success, 'Success', true, $data);

        } catch (BundleException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update a simple bundle
     */
    public function update(UpdateBundleRequest $request, int $id): JsonResponse
    {
        try {
            $this->bundleService->updateBundle($id, $request->validated());

            return ApiHelper::apiResponse($this->success, 'Bundle has been updated successfully.');

        } catch (BundleException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false, $e->getErrors());
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Delete a bundle
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('packages_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $this->bundleService->deleteBundle($id);

            return ApiHelper::apiResponse($this->success, 'Bundle has been deleted successfully.');

        } catch (BundleException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update bundle status (active/inactive)
     */
    public function status(UpdateBundleStatusRequest $request): JsonResponse
    {
        try {
            $this->bundleService->updateStatus($request->id, $request->status);

            $message = $request->status == 1 
                ? 'Bundle has been activated successfully.' 
                : 'Bundle has been inactivated successfully.';

            return ApiHelper::apiResponse($this->success, $message);

        } catch (BundleException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get bundle details
     */
    public function detail(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('packages_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $data = $this->bundleService->getBundleDetails($id);

            return ApiHelper::apiResponse($this->success, 'Success', true, $data);

        } catch (BundleException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
