<?php

namespace App\Http\Controllers\Api;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Services\Plan\PlanService;
use App\Exceptions\PlanException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PlansController extends Controller
{
    protected PlanService $planService;

    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
    }

    /**
     * Get datatable data for plans (OPTIMIZED)
     * 
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function datatable(Request $request, int $patientId): JsonResponse
    {
        try {
            // Check permission - use same permission as main plans module
            if (!Gate::allows('plans_manage')) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not authorized to access this resource.',
                ], 403);
            }

            // Handle bulk delete action
            if ($request->get('customActionType') === 'group_action' && $request->has('id')) {
                $result = $this->planService->handleBulkDelete($request->get('id'));
                
                return response()->json([
                    'customActionStatus' => 'OK',
                    'customActionMessage' => $result['message'],
                    'data' => [],
                    'draw' => intval($request->get('draw')),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                ]);
            }

            // Handle filter cancellation
            if ($request->get('action') && is_array($request->get('action'))) {
                $action = $request->get('action');
                if (isset($action[0]) && $action[0] === 'filter_cancel') {
                    \App\Helpers\Filters::flush(\Illuminate\Support\Facades\Auth::id(), 'patient_packages');
                }
            }

            // Get filters from request
            $filters = $this->getFilters($request);

            // Get datatable data using service
            $datatableData = $this->planService->getDatatableData($filters, $patientId);

            // Apply pagination - support both KTDatatable format and DataTables format
            $pagination = $request->input('pagination', []);
            $iDisplayLength = intval($pagination['perpage'] ?? $request->get('length', 30));
            $iDisplayLength = $iDisplayLength < 0 ? $datatableData['total'] : $iDisplayLength;
            $page = intval($pagination['page'] ?? 1);
            $iDisplayStart = intval($request->get('start', ($page - 1) * $iDisplayLength));

            // Get paginated results
            $packages = $datatableData['query']
                ->orderBy($datatableData['orderBy'], $datatableData['order'])
                ->offset($iDisplayStart)
                ->limit($iDisplayLength)
                ->get();

            // Format records for datatable
            $formattedRecords = $this->planService->formatDatatableRecords($packages);

            // Calculate pagination
            $totalPages = $iDisplayLength > 0 ? ceil($datatableData['total'] / $iDisplayLength) : 1;

            return response()->json([
                'meta' => [
                    'page' => $page,
                    'pages' => $totalPages,
                    'perpage' => $iDisplayLength,
                    'total' => $datatableData['total'],
                    'sort' => strtolower($datatableData['order']),
                    'field' => $datatableData['orderBy'],
                ],
                'data' => $formattedRecords,
                'permissions' => [
                    'edit' => Gate::allows('plans_edit'),
                    'delete' => Gate::allows('plans_destroy'),
                    'active' => Gate::allows('plans_active'),
                    'inactive' => Gate::allows('plans_inactive'),
                    'create' => Gate::allows('plans_create'),
                    'log' => Gate::allows('plans_log'),
                    'sms_log' => Gate::allows('plans_sms_log'),
                    'patients_plan_cash_edit' => Gate::allows('plans_cash_edit'),
                    'patients_plan_cash_delete' => Gate::allows('plans_cash_delete'),
                ],
                'filter_values' => $datatableData['filter_values'] ?? [],
                'active_filters' => $filters,
            ]);

        } catch (PlanException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Plans Datatable Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'patient_id' => $patientId,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching plans data.',
            ], 500);
        }
    }

    /**
     * Get lookup data for filters
     * 
     * @param int $patientId
     * @return JsonResponse
     */
    public function getLookupData(int $patientId): JsonResponse
    {
        try {
            if (!Gate::allows('plans_manage')) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not authorized to access this resource.',
                ], 403);
            }

            $lookupData = $this->planService->getLookupData($patientId);

            return response()->json([
                'status' => true,
                'data' => $lookupData,
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Plans Lookup Data Error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching lookup data.',
            ], 500);
        }
    }

    /**
     * Extract filters from request
     * 
     * @param Request $request
     * @return array
     */
    protected function getFilters(Request $request): array
    {
        $filters = [];

        // Get query parameters - KTDatatable sends filters in query[search]
        $query = $request->get('query', []);
        
        // Check if filters are in query[search] (KTDatatable format)
        if (isset($query['search']) && is_array($query['search'])) {
            $filters = $query['search'];
        } elseif (is_array($query)) {
            $filters = $query;
        }

        // Get sort parameters
        if ($request->has('sort')) {
            $filters['sort'] = $request->get('sort');
        }

        // Get action parameter (might be at root level)
        if ($request->has('action')) {
            $filters['action'] = $request->get('action');
        }

        // Extract specific filter fields from root level if not in query
        $filterFields = ['package_id', 'location_id', 'status', 'created_at', 'patient_id'];
        
        foreach ($filterFields as $field) {
            if (!isset($filters[$field]) && $request->has($field)) {
                $filters[$field] = $request->get($field);
            }
        }

        // Check if generalSearch exists
        if (isset($query['generalSearch']) && !empty($query['generalSearch'])) {
            $filters['generalSearch'] = $query['generalSearch'];
        }

        return $filters;
    }

    /**
     * Get global datatable data for plans (admin packages page - OPTIMIZED)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function globalDatatable(Request $request): JsonResponse
    {
        try {
            // Check permission
            if (!Gate::allows('plans_manage')) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not authorized to access this resource.',
                ], 403);
            }

            // Handle bulk delete action
            if ($request->has('delete') && $request->get('delete')) {
                $ids = explode(',', $request->get('delete'));
                $result = $this->planService->handleBulkDelete($ids);
                
                return response()->json([
                    'status' => $result['deleted'] > 0,
                    'message' => $result['message'],
                ]);
            }

            // Handle filter cancellation
            if ($request->get('action') && is_array($request->get('action'))) {
                $action = $request->get('action');
                if (isset($action[0]) && $action[0] === 'filter_cancel') {
                    \App\Helpers\Filters::flush(\Illuminate\Support\Facades\Auth::id(), 'packages');
                }
            }

            // Get filters from request
            $filters = $this->getFilters($request);

            // Get datatable data using service
            $datatableData = $this->planService->getGlobalDatatableData($filters);

            // Apply pagination
            $pagination = $request->input('pagination', []);
            $iDisplayLength = intval($pagination['perpage'] ?? 30);
            $page = intval($pagination['page'] ?? 1);
            $iDisplayStart = ($page - 1) * $iDisplayLength;

            // Get paginated results
            $packages = $datatableData['query']
                ->orderBy($datatableData['orderBy'], $datatableData['order'])
                ->offset($iDisplayStart)
                ->limit($iDisplayLength)
                ->get();

            // Format records for datatable
            $formattedRecords = $this->planService->formatDatatableRecords($packages);

            // Calculate pagination
            $totalPages = $iDisplayLength > 0 ? ceil($datatableData['total'] / $iDisplayLength) : 1;

            return response()->json([
                'meta' => [
                    'page' => $page,
                    'pages' => $totalPages,
                    'perpage' => $iDisplayLength,
                    'total' => $datatableData['total'],
                    'sort' => strtolower($datatableData['order']),
                    'field' => $datatableData['orderBy'],
                ],
                'data' => $formattedRecords,
                'permissions' => [
                    'edit' => Gate::allows('plans_edit'),
                    'delete' => Gate::allows('plans_destroy'),
                    'active' => Gate::allows('plans_active'),
                    'inactive' => Gate::allows('plans_inactive'),
                    'create' => Gate::allows('plans_create'),
                    'log' => Gate::allows('plans_log'),
                    'sms_log' => Gate::allows('plans_sms_log'),
                    'plans_cash_edit' => Gate::allows('plans_cash_edit'),
                    'plans_cash_delete' => Gate::allows('plans_cash_delete'),
                    'plans_cash_edit_payment_mode' => Gate::allows('plans_cash_edit_payment_mode'),
                    'plans_cash_edit_amount' => Gate::allows('plans_cash_edit_amount'),
                    'plans_cash_edit_date' => Gate::allows('plans_cash_edit_date'),
                    'plans_edit_sold_by' => Gate::allows('plans_edit_sold_by'),
                ],
                'filter_values' => $datatableData['filter_values'] ?? [],
                'active_filters' => $filters,
            ]);

        } catch (PlanException $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 400);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Global Plans Datatable Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Return detailed error in development
            if (config('app.debug')) {
                return response()->json([
                    'status' => false,
                    'message' => 'An error occurred while fetching plans data.',
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching plans data.',
            ], 500);
        }
    }

    /**
     * Get global lookup data for filters (admin packages page)
     * 
     * @return JsonResponse
     */
    public function getGlobalLookupData(): JsonResponse
    {
        try {
            if (!Gate::allows('plans_manage')) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not authorized to access this resource.',
                ], 403);
            }

            $lookupData = $this->planService->getGlobalLookupData();

            return response()->json([
                'status' => true,
                'data' => $lookupData,
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Global Plans Lookup Data Error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching lookup data.',
            ], 500);
        }
    }

    /**
     * Get plan statistics for patient
     * 
     * @param int $patientId
     * @return JsonResponse
     */
    public function getStatistics(int $patientId): JsonResponse
    {
        try {
            if (!Gate::allows('plans_manage')) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not authorized to access this resource.',
                ], 403);
            }

            $stats = \App\Models\Packages::where('patient_id', $patientId)
                ->selectRaw('
                    COUNT(*) as total_plans,
                    SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_plans,
                    SUM(total_price) as total_amount,
                    SUM(CASE WHEN is_refund = 1 THEN 1 ELSE 0 END) as refunded_plans
                ')
                ->first();

            $cashReceived = \App\Models\PackageAdvances::whereHas('package', function ($query) use ($patientId) {
                $query->where('patient_id', $patientId);
            })
                ->where('cash_flow', 'in')
                ->where('is_cancel', 0)
                ->sum('cash_amount');

            return response()->json([
                'status' => true,
                'data' => [
                    'total_plans' => $stats->total_plans ?? 0,
                    'active_plans' => $stats->active_plans ?? 0,
                    'total_amount' => number_format($stats->total_amount ?? 0, 2),
                    'cash_received' => number_format($cashReceived, 2),
                    'refunded_plans' => $stats->refunded_plans ?? 0,
                ],
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Plans Statistics Error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching statistics.',
            ], 500);
        }
    }
}
