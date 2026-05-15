<?php

declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Http\Controllers\Controller;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\PaymentModes;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PackageAdvancesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function index(): \Illuminate\View\View
    {
        if (! Gate::allows('finances_manage')) {

            return abort(401);
        }

        $paymentmodes = PaymentModes::get()->pluck('name', 'id');
        $paymentmodes->prepend('All', '');

        $package = Packages::get()->pluck('name', 'id');
        $package->prepend('All', '');

        $total_cash_in = PackageAdvances::where('cash_flow', '=', 'in')->sum('cash_amount');
        $total_cash_out = PackageAdvances::where('cash_flow', '=', 'out')->sum('cash_amount');

        $balance = $total_cash_in - $total_cash_out;

        return view('admin.packagesadvances.index', compact('paymentmodes', 'package', 'total_cash_in', 'total_cash_out', 'balance'));

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('finances_create')) {

            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $paymentmodes = PaymentModes::get()->pluck('name', 'id');
        $paymentmodes->prepend('Select Payment Mode', '');

        return $this->successResponse('Record found', [
            'paymentmodes' => $paymentmodes,
        ]);
    }

    /*
     * Get the packages against patient id
     *
     * */
    public function getpackages(Request $request): \Illuminate\Http\JsonResponse
    {

        $packageinfo = Packages::where('patient_id', '=', $request->id)->get();

        return $this->successResponse('Record found', [
            'packageinfo' => $packageinfo,
        ]);

    }

    /*
     * Get the packages information from packages advances
     *
     * */
    public function getpackagesinfo(Request $request): \Illuminate\Http\JsonResponse
    {
        $package_info = Packages::where('id', '=', $request->id)->first();
        /*We discuss in future what happen next*/
        $cash_amount = number_format(PackageAdvances::where([
            ['package_id', '=', $request->id],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
        ])->sum('cash_amount'));
        $cash_amount_sum = (sanitize_money($cash_amount) + $request->cash_amount);
        $total_price = number_format($package_info->total_price);

        if ($cash_amount_sum <= $package_info->total_price) {
            $cash_amount_sum = number_format($cash_amount_sum);

            return $this->successResponse('Record found', [
                'cash_amount_sum' => $cash_amount_sum,
                'total_price' => $total_price,
            ]);
        }

        return $this->errorResponse('Record not found', 404);

    }

    /*
     * Get the packages information from packages advances
     *
     */
    public function getpackagesinfo_update(Request $request): \Illuminate\Http\JsonResponse
    {
        $cash_receive = PackageAdvances::where([
            ['package_id', '=', $request->id],
            ['cash_flow', '=', 'in'],
        ])->sum('cash_amount');
        $cash_receive_forupdate = $cash_receive - $request->cash_amount_update;

        $cash_amount_sum = $cash_receive_forupdate + $request->cash_amount;

        $total_price = sanitize_money($request->total_price);

        if ($cash_amount_sum <= $total_price) {
            $cash_amount_sum = number_format($cash_amount_sum);
            $total_price = number_format($total_price);

            return response()->json([
                'status' => true,
                'cash_amount_sum' => $cash_amount_sum,
                'total_price' => $total_price,
            ]);
        } else {
            return response()->json([
                'status' => false,
            ]);
        }

    }

    /*
     * save the information in packages advances
     * */
    public function savepackagesadvances(Request $request): \Illuminate\Http\JsonResponse
    {
        // Permission gate — was missing entirely until 2026-05-15. This
        // endpoint writes to the cash ledger and triggers pool
        // adjustment via PackageAdvanceObserver; an authenticated user
        // without `finances_create` must not reach it.
        if (! Gate::allows('finances_create')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        $accountId = (int) Auth::user()->account_id;

        // Tenant-scoped + soft-delete-aware FK validation. Before this,
        // patient_id / package_id / payment_mode_id were copied raw from
        // the request — an attacker could pass another tenant's plan id
        // and record a payment against it. `cash_amount` is enforced
        // strictly positive: overpayment IS allowed (the ledger is
        // signed and treats excess as a credit), but a negative amount
        // would flip the ledger direction and let the caller drain a
        // pool while pretending to make a payment.
        $request->validate([
            // `integer` rejects fractional + scientific notation; `min:1` enforces
// strictly positive (cash flow direction is server-controlled to `in`
// in this method — a negative amount would invert the ledger sign and
// drain the pool while logging an "income" event). Matches the cashflow
// module's contract pinned by CashflowAmountValidationTest.
'cash_amount' => 'required|integer|min:1|max:99999999',
            'patient_id' => ['required', 'integer', Rule::exists('users', 'id')->where('account_id', $accountId)->whereNull('deleted_at')],
            'package_id' => ['required', 'integer', Rule::exists('packages', 'id')->where('account_id', $accountId)->whereNull('deleted_at')],
            'payment_mode_id' => ['required', 'integer', Rule::exists('payment_modes', 'id')->where('account_id', $accountId)->whereNull('deleted_at')],
        ]);

        return DB::transaction(function () use ($request, $accountId) {
            // Overpayment is a legitimate state — operators routinely
            // collect deposits / advances that exceed the current plan
            // total. The cash ledger (`package_advances`) is signed:
            // any excess simply lands as a credit (negative balance) and
            // surfaces in the dashboards' credit column. See
            // ConcurrentPackageAdvanceTest for the pinned contract.

            $data['cash_flow'] = 'in';
            $data['cash_amount'] = $request->cash_amount;
            $data['patient_id'] = $request->patient_id;
            $data['payment_mode_id'] = $request->payment_mode_id;
            $data['account_id'] = $accountId;
            $data['created_by'] = Auth::user()->id;
            $data['updated_by'] = Auth::user()->id;
            $data['package_id'] = $request->package_id;

            $package_advances = PackageAdvances::createRecord_onlyadvances($data);

            if ($package_advances) {
                return $this->successResponse('Record saved successfully.');
            }

            return $this->errorResponse('Failed to save the record.', 404);
        });
    }

    /**
     * Display a User As package advances  in datatables.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request): \Illuminate\Http\JsonResponse
    {
        $jason_var = 'packageAdvances';

        $filters = getFilters($request->all());
        $apply_filter = checkFilters($filters, $jason_var);

        $records = [];
        $records['data'] = [];

        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $packagesadvances = PackageAdvances::getBulkData($ids);
            if ($packagesadvances) {
                foreach ($packagesadvances as $packageadvances) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! PackageAdvances::isChildExists($packageadvances->id, Auth::user()->account_id)) {
                        $packageadvances->delete();
                    }
                }
            }
            $records['status'] = true; // pass custom message(useful for getting status of group actions)
            $records['message'] = 'Records has been deleted successfully!'; // pass custom message(useful for getting status of group actions)
        }

        $patient_id = $this->getPatientId();
        // Get Total Records
        $iTotalRecords = PackageAdvances::getTotalRecords($request, Auth::user()->account_id, $patient_id, $apply_filter, $jason_var);

        [$orderBy, $order] = getSortBy($request, 'created_at', 'DESC');
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $packagesadvances = PackageAdvances::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::user()->account_id, $patient_id, $apply_filter, $jason_var);
        $records = $this->getFilterData($records, $jason_var);

        if ($packagesadvances) {
            $balance = 0;
            foreach ($packagesadvances as $packagesadvances) {

                $balance += match ($packagesadvances->cash_flow) {
                    'in' => $packagesadvances->cash_amount,
                    default => -$packagesadvances->cash_amount,
                };
                if ($packagesadvances->package_id) {
                    $transtype = Config::get('constants.trans_type.advance_in');
                }

                if ($packagesadvances->invoice_id && $packagesadvances->cash_flow == 'in') {
                    $transtype = Config::get('constants.trans_type.advance_in');
                }

                if ($packagesadvances->is_adjustment == '1') {
                    $transtype = Config::get('constants.trans_type.adjustment');
                }

                if ($packagesadvances->is_cancel == '1') {
                    $transtype = Config::get('constants.trans_type.invoice_cancel');
                }
                if ($packagesadvances->invoice_id && $packagesadvances->cash_flow == 'out') {
                    $transtype = Config::get('constants.trans_type.invoice_create');
                }
                if ($packagesadvances->is_refund == '1') {
                    $transtype = Config::get('constants.trans_type.refund_in');
                }
                if ($packagesadvances->is_tax == '1') {
                    $transtype = Config::get('constants.trans_type.tax_out');
                }
                if ($packagesadvances->cash_flow == 'in') {
                    $cash_in = number_format($packagesadvances->cash_amount);
                    $cash_out = '-';
                } else {
                    $cash_out = number_format($packagesadvances->cash_amount);
                    $cash_in = '-';
                }
                $records['data'][] = [
                    'patient_id' => GeneralFunctions::patientSearchStringAdd($packagesadvances->user->id),
                    'patient' => $packagesadvances->user->name,
                    'phone' => Gate::allows('contact') ? GeneralFunctions::prepareNumber4Call($packagesadvances->user->phone) : '***********',
                    'transtype' => $transtype,
                    'cash_in' => $cash_in,
                    'cash_out' => $cash_out,
                    'balance' => number_format($balance),
                    'cash_amount' => '1',
                    'created_at' => Carbon::parse($packagesadvances->created_at)->format('F j,Y h:i A'),
                ];
            }

            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];
        }

        return response()->json($records);
    }

    private function getPatientId(): string|int|false
    {

        $patient_id = false;
        $id = request('id');
        if (isset($id) && $id != '') {
            $patient_id = $id;
        }

        return $patient_id;
    }

    private function getFilterData($records, $filename): array
    {

        $filters = Filters::all(Auth::user()->id, $filename);

        if ($user_id = Filters::get(Auth::user()->id, 'packageAdvances', 'patient_id')) {
            $patient = User::where([
                'id' => $user_id,
            ])->first();
            if ($patient) {
                $patient = $patient->toArray();
            }
        } else {
            $patient = [];
        }

        if (isset($filters['created_from'])) {
            $filters['created_from'] = date('Y-m-d', strtotime($filters['created_from']));
        }
        if (isset($filters['created_to'])) {
            $filters['created_to'] = date('Y-m-d', strtotime($filters['created_to']));
        }

        $records['active_filters'] = $filters;

        $records['filter_values'] = [
            'patient' => $patient,
        ];

        return $records;

    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function inactive(int $id): \Illuminate\Http\RedirectResponse
    {
        if (! Gate::allows('finances_manage')) {
            return abort(401);
        }
        PackageAdvances::inactiveRecord($id);

        return redirect()->route('admin.packagesadvances.index');
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function active(int $id): \Illuminate\Http\RedirectResponse
    {
        if (! Gate::allows('finances_manage')) {
            return abort(401);
        }
        PackageAdvances::activeRecord($id);

        return redirect()->route('admin.packagesadvances.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(int $id): \Illuminate\View\View
    {
        if (! Gate::allows('finances_manage')) {

            return abort(401);
        }
        $packageadvances = PackageAdvances::find($id);
        $package_info = Packages::where('patient_id', '=', $packageadvances->patient_id)->get();
        $total_price_cal = Packages::where('id', '=', $packageadvances->package_id)->first();
        $total_price = number_format($total_price_cal->total_price);
        $cash_sum = PackageAdvances::where([
            ['package_id', '=', $packageadvances->package_id],
            ['cash_flow', '=', 'in'],
        ])->sum('cash_amount');
        $cash_total_amount = number_format($cash_sum - $packageadvances->cash_amount);
        $total_amount = number_format(($cash_sum - $packageadvances->cash_amount) + $packageadvances->cash_amount);

        $leads = User::where('user_type_id', '=', Config::get('constants.patient_id'))->get();
        $paymentmodes = PaymentModes::get();

        return view('admin.packagesadvances.edit', compact('leads', 'paymentmodes', 'packageadvances', 'package_info', 'total_price', 'cash_total_amount', 'total_amount'));
    }

    /*
     * update package advance information
     * */
    public function updatepackagesadvances(Request $request): \Illuminate\Http\JsonResponse
    {
        // Permission gate — was missing entirely. Mirrors savepackagesadvances.
        if (! Gate::allows('finances_edit')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        $accountId = (int) Auth::user()->account_id;

        // Tenant-scoped + soft-delete-aware FK validation +
        // strict-positive amount. Same rationale as savepackagesadvances.
        // package_advance_id is the row being mutated and must belong
        // to the caller's tenant — bare ::find() previously let an
        // attacker target another tenant's advance by id.
        $request->validate([
            'package_advance_id' => ['required', 'integer', Rule::exists('package_advances', 'id')->where('account_id', $accountId)->whereNull('deleted_at')],
            // `integer` rejects fractional + scientific notation; `min:1` enforces
// strictly positive (cash flow direction is server-controlled to `in`
// in this method — a negative amount would invert the ledger sign and
// drain the pool while logging an "income" event). Matches the cashflow
// module's contract pinned by CashflowAmountValidationTest.
'cash_amount' => 'required|integer|min:1|max:99999999',
            'patient_id' => ['required', 'integer', Rule::exists('users', 'id')->where('account_id', $accountId)->whereNull('deleted_at')],
            'package_id' => ['required', 'integer', Rule::exists('packages', 'id')->where('account_id', $accountId)->whereNull('deleted_at')],
            'payment_mode_id' => ['required', 'integer', Rule::exists('payment_modes', 'id')->where('account_id', $accountId)->whereNull('deleted_at')],
        ]);

        return DB::transaction(function () use ($request, $accountId) {
            $data['cash_flow'] = 'in';
            $data['cash_amount'] = $request->cash_amount;
            $data['patient_id'] = $request->patient_id;
            $data['payment_mode_id'] = $request->payment_mode_id;
            $data['account_id'] = $accountId;
            $data['created_by'] = Auth::user()->id;
            $data['updated_by'] = Auth::user()->id;
            $data['package_id'] = $request->package_id;

            $package_advances = PackageAdvances::updateRecord_onlyadvances($data, $request->package_advance_id);

            return response()->json([
                'status' => true,
            ]);
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id): \Illuminate\Http\RedirectResponse
    {
        if (! Gate::allows('finances_manage')) {
            return abort(401);
        }

        PackageAdvances::deleteRecord($id);

        return redirect()->route('admin.packagesadvances.index');

    }

    /**
     *cancel the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function cancel(int $id): \Illuminate\Http\RedirectResponse
    {
        if (! Gate::allows('finances_manage')) {
            return abort(401);
        }

        // Separation of duties — the operator who recorded a payment
        // must not be the one to cancel it. Without this, an operator
        // who pocketed cash could create + cancel the same advance and
        // the audit trail would only show "created by X, cancelled by
        // X" — a normal-looking pair. A second pair of eyes is required.
        $accountId = (int) Auth::user()->account_id;
        $original = PackageAdvances::where(['id' => $id, 'account_id' => $accountId])->first();
        if ($original && (int) $original->created_by === (int) Auth::user()->id) {
            abort(403, 'You cannot cancel a payment you created. Another admin must cancel it.');
        }

        $packageadvances = PackageAdvances::CancelRecord($id, $accountId);

        $package_advnaces = (PackageAdvances::find($id))->toArray();
        if ($package_advnaces['cash_flow'] == 'in') {

            $package_advnaces['cash_flow'] = 'out';
            $package_advnaces['is_cancel'] = '1';
        } else {
            $package_advnaces['cash_flow'] = 'in';
            $package_advnaces['is_cancel'] = '1';
        }
        $advance_cancel = PackageAdvances::createRecord_onlyadvances($package_advnaces);

        return redirect()->route('admin.packagesadvances.index');
    }

}
