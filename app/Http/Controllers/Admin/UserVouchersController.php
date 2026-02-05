<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Filters;
use App\Helpers\ActivityLogger;
use App\Models\UserVouchers;
use App\Models\User;
use App\Models\Discounts;
use App\Models\PackageVouchers;
use Illuminate\Http\Request;
use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UserVouchersController extends Controller
{
    public $success;
    public $error;
    public $unauthorized;

    public function __construct()
    {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Display a listing of the user vouchers.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Gate::allows('vouchers_manage')) {
            return abort(401);
        }

        return view('admin.vouchers.index');
    }

    /**
     * Display the user vouchers in datatable form.
     * Supports optional patient_id parameter for patient-specific filtering
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int|null  $patientId
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request, $patientId = null)
    {
        try {
            $records = [];
            $records['data'] = [];

            $filename = 'vouchers';
            $filters = getFilters($request->all());
            
            // If patient_id is provided (patient card context), add it to filters
            if ($patientId) {
                $filters['patient_id'] = $patientId;
            }
            
            $apply_filter = checkFilters($filters, $filename);

            $where = $this->applyFilters($filters, $apply_filter, $filename);

            $total_query = UserVouchers::select('id');
            if (count($where)) {
                $total_query->where($where);
            }
            $iTotalRecords = $total_query->count();

            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $query = UserVouchers::with(['user', 'voucher']);

            if (count($where)) {
                $query->where($where);
            }

            $userVouchers = $query->limit($iDisplayLength)
                ->offset($iDisplayStart)
                ->orderby('created_at', 'desc')
                ->get();

            $records = $this->getFiltersData($records, $filename);

            if ($userVouchers) {
                // Fetch all package vouchers once to avoid N+1 queries
                $userIds = $userVouchers->pluck('user_id')->unique()->toArray();
                $voucherIds = $userVouchers->pluck('voucher_id')->unique()->toArray();

                $usedVouchers = PackageVouchers::whereIn('user_id', $userIds)
                    ->whereIn('voucher_id', $voucherIds)
                    ->select('user_id', 'voucher_id')
                    ->get()
                    ->groupBy(function($item) {
                        return $item->user_id . '_' . $item->voucher_id;
                    });

                $records['data'] = $userVouchers->map(function ($item) use ($usedVouchers) {
                    // Check if voucher is used in package_vouchers
                    $key = $item->user_id . '_' . $item->voucher_id;
                    $isUsedInPackages = isset($usedVouchers[$key]);

                    return [
                        'id' => $item->id,
                        'patient_id' => $item->user_id,
                        'name' => $item->user ? $item->user->name  : 'N/A',
                        'voucher_type' => $item->voucher ? $item->voucher->name : 'N/A',
                        'total_amount' => $item->total_amount ?? 0,
                        'amount' => $item->amount,
                        'created_at' => $item->created_at ? $item->created_at->format('F d,Y h:i A') : '',
                        'can_edit' => !$isUsedInPackages,
                        'can_delete' => !$isUsedInPackages,
                    ];
                });

                $records['meta'] = [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => $iDisplayLength,
                    'total' => $iTotalRecords,
                    'sort' => $order,
                ];
            }

            $records['permissions'] = [
                'view' => Gate::allows('vouchers_view'),
                'create' => Gate::allows('vouchers_create'),
                'edit' => Gate::allows('vouchers_edit'),
                'delete' => Gate::allows('vouchers_destroy'),
            ];

            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    private function applyFilters($filters, $apply_filter, $filename = 'vouchers')
    {
        $where = [];

        if (hasFilter($filters, 'patient_id')) {
            $where[] = [
                'user_id',
                '=',
                $filters['patient_id'],
            ];
            Filters::put(Auth::User()->id, $filename, 'patient_id', $filters['patient_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'patient_id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'patient_id')) {
                    $where[] = [
                        'user_id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'patient_id'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'voucher_type')) {
            $where[] = [
                'voucher_id',
                '=',
                $filters['voucher_type'],
            ];
            Filters::put(Auth::User()->id, $filename, 'voucher_type', $filters['voucher_type']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'voucher_type');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'voucher_type')) {
                    $where[] = [
                        'voucher_id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'voucher_type'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'created_from')) {
            $where[] = [
                'created_at',
                '>=',
                $filters['created_from'] . ' 00:00:00',
            ];
            Filters::put(Auth::User()->id, $filename, 'created_from', $filters['created_from']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_from');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_from')) {
                    $where[] = [
                        'created_at',
                        '>=',
                        Filters::get(Auth::User()->id, $filename, 'created_from') . ' 00:00:00',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'created_to')) {
            $where[] = [
                'created_at',
                '<=',
                $filters['created_to'] . ' 23:59:59',
            ];
            Filters::put(Auth::User()->id, $filename, 'created_to', $filters['created_to']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_to');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_to')) {
                    $where[] = [
                        'created_at',
                        '<=',
                        Filters::get(Auth::User()->id, $filename, 'created_to') . ' 23:59:59',
                    ];
                }
            }
        }

        return $where;
    }

    private function getFiltersData($records, $filename)
    {
        $active_filters = Filters::all(Auth::User()->id, $filename);

        // Get patient name if patient_id exists in filters
        if (isset($active_filters['patient_id']) && $active_filters['patient_id']) {
            $patient = User::find($active_filters['patient_id']);
            if ($patient) {
                $active_filters['patient_name'] = $patient->name;
            }
        }

        $records['active_filters'] = $active_filters;

        $records['filter_values'] = [
            'vouchers' => Discounts::where('discount_type', 'voucher')->select('id', 'name')->get(),
        ];

        return $records;
    }

    /**
     * Store a newly assigned voucher.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            if (!Gate::allows('vouchers_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $request->validate([
                'patient_id' => 'required|exists:users,id',
                'voucher_id' => 'required|exists:discounts,id',
                'amount' => 'required|numeric|min:0',
            ]);

            // Check if voucher is already assigned to this patient
            $checkVoucher = UserVouchers::where('user_id', $request->patient_id)
                ->where('voucher_id', $request->voucher_id)
                ->first();

            // if ($checkVoucher) {
            //     return ApiHelper::apiResponse($this->error, 'Voucher is already assigned to this patient.', false);
            // }

            // Create the user voucher
            $userVoucher = UserVouchers::create([
                'user_id' => $request->patient_id,
                'voucher_id' => $request->voucher_id,
                'amount' => $request->amount,
                'total_amount' => $request->amount
            ]);

            // Log voucher assignment activity
            $patient = User::find($request->patient_id);
            $voucher = Discounts::find($request->voucher_id);
            ActivityLogger::logVoucherAssigned($userVoucher, $patient, $voucher);

            return ApiHelper::apiResponse($this->success, 'Voucher assigned successfully.', true);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Edit user voucher.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        try {
            if (!Gate::allows('vouchers_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $userVoucher = UserVouchers::with(['user', 'voucher'])->findOrFail($id);

            // Check if voucher is used in package_vouchers
            $isUsedInPackages = PackageVouchers::where('voucher_id', $userVoucher->voucher_id)
                ->where('user_id', $userVoucher->user_id)
                ->exists();

            if ($isUsedInPackages) {
                return ApiHelper::apiResponse($this->error, 'This voucher cannot be edited as it is already applied to services.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'voucher' => $userVoucher,
                'patient_name' => $userVoucher->user ? $userVoucher->user->name : 'N/A',
                'voucher_type_name' => $userVoucher->voucher ? $userVoucher->voucher->name : 'N/A',
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update user voucher.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            if (!Gate::allows('vouchers_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $request->validate([
                'total_amount' => 'required|numeric|min:0',
            ]);

            $userVoucher = UserVouchers::findOrFail($id);
            $oldAmount = $userVoucher->total_amount;

            // Check if voucher is used in package_vouchers
            $isUsedInPackages = PackageVouchers::where('voucher_id', $userVoucher->voucher_id)
                ->where('user_id', $userVoucher->user_id)
                ->exists();

            if ($isUsedInPackages) {
                return ApiHelper::apiResponse($this->error, 'This voucher cannot be updated as it is already applied to services.', false);
            }

            // Update both total_amount and amount (remaining amount)
            $userVoucher->update([
                'total_amount' => $request->total_amount,
                'amount' => $request->total_amount,
            ]);

            // Log voucher update activity
            $patient = User::find($userVoucher->user_id);
            $voucher = Discounts::find($userVoucher->voucher_id);
            ActivityLogger::logVoucherUpdated($userVoucher, $patient, $voucher, $oldAmount, $request->total_amount);

            return ApiHelper::apiResponse($this->success, 'Voucher updated successfully.', true, $userVoucher);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Delete user voucher.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            if (!Gate::allows('vouchers_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $userVoucher = UserVouchers::findOrFail($id);

            // Check if voucher is used in package_vouchers
            $isUsedInPackages = PackageVouchers::where('voucher_id', $userVoucher->voucher_id)
                ->where('user_id', $userVoucher->user_id)
                ->exists();

            if ($isUsedInPackages) {
                return ApiHelper::apiResponse($this->error, 'This voucher cannot be deleted as it is already applied to services.', false);
            }

            $userVoucher->delete();

            return ApiHelper::apiResponse($this->success, 'Voucher deleted successfully.', true);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Show voucher usage details for a specific patient.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\View\View|\Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        if (!Gate::allows('vouchers_manage')) {
            if ($request->ajax()) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }
            return abort(401);
        }

        // Get the user voucher record
        $userVoucher = UserVouchers::with(['user', 'voucher'])->findOrFail($id);

        $voucher = $userVoucher->voucher;
        $user = $userVoucher->user;

        // Get package_random_ids and main_service_ids from package_vouchers for this specific user and voucher
        $packageVouchers = PackageVouchers::where('user_id', $userVoucher->user_id)
            ->where('voucher_id', $userVoucher->voucher_id)
            ->select('package_random_id', 'main_service_id')
            ->distinct()
            ->get();

        $voucherUsageData = [];

        foreach ($packageVouchers as $packageVoucher) {
            // Find matching package_bundles
            $packageBundles = \App\Models\PackageBundles::where('random_id', $packageVoucher->package_random_id)
                ->where('bundle_id', $packageVoucher->main_service_id)
                ->where('discount_name', $voucher->name) // strict check with voucher name
                ->get();

            foreach ($packageBundles as $bundle) {
                // Get the package to retrieve package_id
                $package = \App\Models\Packages::where('random_id', $packageVoucher->package_random_id)->first();

                // Get services for this bundle
                $packageServices = \App\Models\PackageService::where('package_bundle_id', $bundle->id)
                    ->with('service')
                    ->get();

                foreach ($packageServices as $service) {
                    $voucherUsageData[] = [
                        'package_id' => $package ? $package->id : null,
                        'package_random_id' => $packageVoucher->package_random_id,
                        'bundle_name' => $bundle->bundle ? $bundle->bundle->name : 'N/A',
                        'service_name' => $service->service ? $service->service->name : 'N/A',
                        'discount_type' => $bundle->discount_type,
                        'discount_price' => $bundle->discount_price,
                        'user_id' => $userVoucher->user_id,
                    ];
                }
            }
        }

        // If AJAX request, return HTML content for modal
        if ($request->ajax()) {
            $html = view('admin.vouchers.view_content', compact('voucher', 'user', 'voucherUsageData', 'userVoucher'))->render();
            return ApiHelper::apiResponse($this->success, 'Voucher usage details retrieved successfully.', true, ['html' => $html]);
        }

        // Otherwise return full page view
        return view('admin.vouchers.show', compact('voucher', 'user', 'voucherUsageData', 'userVoucher'));
    }
}
