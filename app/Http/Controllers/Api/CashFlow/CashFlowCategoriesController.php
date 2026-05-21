<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CashFlow;

use App\Exceptions\CashflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashFlow\StoreCategorySuggestionRequest;
use App\Services\CashFlow\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CashFlowCategoriesController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    /**
     * Get all categories.
     */
    public function categoriesIndex(): JsonResponse
    {

        if (! Gate::any(['cashflow.category.manage', 'cashflow.expense.view', 'cashflow.expense.create', 'cashflow.manage'])) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

        try {

            $accountId = Auth::user()->account_id;

            return response()->json([

                'success' => true,

                'data' => $this->categoryService->getAll($accountId),

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Create a category.
     */
    public function categoriesStore(Request $request): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow.category.manage')) {

                throw CashflowException::unauthorized('manage categories');

            }



            $request->validate([

                'name' => 'required|string|max:255',

                'description' => 'nullable|string|max:500',

                'vendor_emphasis' => 'nullable|boolean',

            ]);



            $accountId = Auth::user()->account_id;

            $category = $this->categoryService->create($request->all(), $accountId);



            return response()->json(['success' => true, 'data' => $category, 'message' => 'Category created successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            throw $e;

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 422);

        }

    }

    /**
     * Update a category.
     */
    public function categoriesUpdate(Request $request, int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow.category.manage')) {

                throw CashflowException::unauthorized('manage categories');

            }



            $request->validate([

                'name' => 'nullable|string|max:255',

                'description' => 'nullable|string|max:500',

                'vendor_emphasis' => 'nullable|boolean',

            ]);



            $accountId = Auth::user()->account_id;

            $category = $this->categoryService->update($id, $request->all(), $accountId);



            return response()->json(['success' => true, 'data' => $category, 'message' => 'Category updated successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            throw $e;

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 422);

        }

    }

    /**
     * Toggle category active/inactive.
     */
    public function categoriesToggle(int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow.category.manage')) {

                throw CashflowException::unauthorized('manage categories');

            }



            $accountId = Auth::user()->account_id;

            $category = $this->categoryService->toggle($id, $accountId);



            return response()->json([

                'success' => true,

                'data' => $category,

                'message' => $category->is_active ? 'Category activated.' : 'Category deactivated.',

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 422);

        }

    }

    /**
     * Get category requests list.
     */
    public function categoryRequestsData(Request $request): JsonResponse
    {

        if (! Gate::any(['cashflow.category.manage', 'cashflow.expense.create', 'cashflow.manage'])) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

        try {

            $accountId = Auth::user()->account_id;

            $status = $request->input('status');

            $requests = $this->categoryService->getCategoryRequests($accountId, $status, (int) $request->input('per_page', 25));



            return response()->json([

                'success' => true,

                'data' => $requests->items(),

                'meta' => [

                    'current_page' => $requests->currentPage(),

                    'last_page' => $requests->lastPage(),

                    'per_page' => $requests->perPage(),

                    'total' => $requests->total(),

                ],

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Create a category suggestion/request.
     */
    public function categoryRequestsStore(StoreCategorySuggestionRequest $request): JsonResponse
    {

        if (! Gate::any(['cashflow.expense.create', 'cashflow.category.manage', 'cashflow.manage'])) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

        try {

            $accountId = Auth::user()->account_id;

            $catRequest = $this->categoryService->createCategoryRequest($request->validated(), $accountId);



            return response()->json(['success' => true, 'data' => $catRequest, 'message' => 'Category suggestion submitted.']);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 422);

        }

    }

    /**
     * Approve a category request.
     */
    public function categoryRequestsApprove(int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow.category.manage')) {

                throw CashflowException::unauthorized('approve category requests');

            }



            $accountId = Auth::user()->account_id;

            $catRequest = $this->categoryService->approveCategoryRequest($id, $accountId);



            return response()->json(['success' => true, 'data' => $catRequest, 'message' => 'Category request approved and category created.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            throw $e;

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Dismiss a category request.
     */
    public function categoryRequestsDismiss(Request $request, int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow.category.manage')) {

                throw CashflowException::unauthorized('dismiss category requests');

            }



            $accountId = Auth::user()->account_id;

            $catRequest = $this->categoryService->dismissCategoryRequest($id, $request->input('admin_notes'), $accountId);



            return response()->json(['success' => true, 'data' => $catRequest, 'message' => 'Category request dismissed.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            throw $e;

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }
}
