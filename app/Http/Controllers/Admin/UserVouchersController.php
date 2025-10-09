<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Filters;
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
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {
            $records = [];
            $records['data'] = [];

            $filename = 'vouchers';
            $filters = getFilters($request->all());
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
                $records['data'] = $userVouchers->map(function ($item) {
                    // Check if voucher is used in package_vouchers
                    $isUsedInPackages = PackageVouchers::where('voucher_id', $item->voucher_id)
                        ->where('user_id', $item->user_id)
                        ->exists();

                    return [
                        'id' => $item->id,
                        'patient_id' => $item->user_id,
                        'name' => $item->user ? $item->user->name  : 'N/A',
                        'voucher_type' => $item->voucher ? $item->voucher->name : 'N/A',
                        'amount' => $item->amount,
                        'created_at' => $item->created_at,
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
                'view' => Gate::allows('vouchers_manage'),
            ];

            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    private function applyFilters($filters, $apply_filter, $filename = 'user_vouchers')
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

        if (hasFilter($filters, 'voucher_id')) {
            $where[] = [
                'voucher_id',
                '=',
                $filters['voucher_id'],
            ];
            Filters::put(Auth::User()->id, $filename, 'voucher_id', $filters['voucher_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'voucher_id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'voucher_id')) {
                    $where[] = [
                        'voucher_id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'voucher_id'),
                    ];
                }
            }
        }

        return $where;
    }

    private function getFiltersData($records, $filename)
    {
        $records['active_filters'] = Filters::all(Auth::User()->id, $filename);

        $records['filter_values'] = [
            'patients' => User::select('id', 'name')->get(),
            'vouchers' => Discounts::where('discount_type', 'voucher')->select('id', 'name')->get(),
        ];

        return $records;
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
            if (!Gate::allows('vouchers_manage')) {
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
            if (!Gate::allows('vouchers_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $userVoucher = UserVouchers::findOrFail($id);

            // Check if voucher is used in package_vouchers
            $isUsedInPackages = PackageVouchers::where('voucher_id', $userVoucher->voucher_id)
                ->where('user_id', $userVoucher->user_id)
                ->exists();

            if ($isUsedInPackages) {
                return ApiHelper::apiResponse($this->error, 'This voucher cannot be updated as it is already applied to services.', false);
            }

            $userVoucher->update($request->only(['user_id', 'voucher_id', 'amount']));

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
            if (!Gate::allows('vouchers_manage')) {
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
}
