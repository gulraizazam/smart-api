<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports\Finance;

use App\Enums\AppointmentType;
use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TaxCalculationReportRequest;
use App\Models\Appointments;
use App\Models\AppointmentsDailyStats;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\RoleHasUsers;
use App\Models\Services;
use App\Models\Cities;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FinanceArrivalReportController extends Controller
{
    /**
     * Normalise a centre/location filter input (scalar, array, or empty) into
     * a clean int[] or empty array meaning "no filter".
     *
     * @return int[]
     */
    private function normaliseLocationIds(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === [] || $raw === '0') {
            return [];
        }

        return array_values(array_filter(
            array_map('intval', (array) $raw),
            fn (int $id): bool => $id > 0,
        ));
    }

    /**
     * @deprecated Moved to App\Http\Controllers\Admin\Reports\ArrivedNotConvertedController
     */

    public function DailyArrival(): \Illuminate\View\View
    {
        $services = Services::where(['parent_id' => 0])->whereNotIn('slug', ['all'])->get();
        $cities = Cities::getActiveOnly(false, Auth::user()->account_id)->pluck('full_name', 'id');
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::user()->account_id);
        $Users = User::getAllRecords(Auth::user()->account_id)->getDictionary();

        return view('admin.reports.dailyarrival', compact('services', 'cities', 'locations', 'Users'));
    }

    public function LoadDailyArrival(Request $request): \Illuminate\View\View
    {
        $where = [];
        $locationIds = $this->normaliseLocationIds($request->input('location_id'));
        // Bound apply of the centre filter is handled below via when()+whereIn().
        if ($request->service_id && $request->service_id != '') {
            $where[] = [['appointments.service_id' => $request->service_id]];
        }
        if ($request->created_by && $request->created_by != '') {
            $where[] = [['appointments.created_by' => $request->created_by]];
        }
        if ($request->date_from) {
            $where[] = ['appointments.scheduled_date', '>=', $request->date_from];
        }
        if ($request->date_to) {
            $where[] = ['appointments.scheduled_date', '<=', $request->date_to];
        }
        $records = [];
        $records['data'] = [];
        if (Gate::allows('consultations_manage')) {
            $resultQuery = Appointments::join('users', function ($query) {
                $query->on('users.id', 'appointments.patient_id')
                    ->where(['users.user_type_id' => config('constants.patient_id')]);
            })->where(['appointments.appointment_type_id' => AppointmentType::Consultancy->value])
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('consultations_manage') && Gate::allows('treatments_services')) {
            $resultQuery = Appointments::join('users', function ($query) {
                $query->on('users.id', 'appointments.patient_id')
                    ->where(['users.user_type_id' => config('constants.patient_id')]);
            })->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }

        if (count($where)) {
            $resultQuery->where($where);
        }
        if (!empty($locationIds)) {
            $resultQuery->whereIn('appointments.location_id', $locationIds);
        }
        $Appointments = $resultQuery->select('*', 'appointments.name as patient_name', 'appointments.id as app_id', 'appointments.created_by as app_created_by', 'appointments.updated_by as app_updated_by', 'appointments.created_at as app_created_at')
            ->orderBy('appointments.created_at', 'DESC')
            ->get();

        // Get arrived and converted appointment status IDs
        $arrivedStatus = \App\Models\AppointmentStatuses::where(['account_id' => Auth::user()->account_id, 'is_arrived' => 1])->first();
        $convertedStatus = \App\Models\AppointmentStatuses::where(['account_id' => Auth::user()->account_id, 'is_converted' => 1])->first();
        $arrivedStatusId = $arrivedStatus ? $arrivedStatus->id : 2;
        $convertedStatusId = $convertedStatus?->id;
        $statusIds = $convertedStatusId ? [$arrivedStatusId, $convertedStatusId] : [$arrivedStatusId];

        $arrivedCentres = !empty($locationIds) ? $locationIds : ACL::getUserCentres();
        $arrived = Appointments::whereIn('appointments.location_id', $arrivedCentres)
            ->whereIn('base_appointment_status_id', $statusIds)
            ->when($request->date_from, fn($q) => $q->where('scheduled_date', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->where('scheduled_date', '<=', $request->date_to))
            ->count();

        return view('admin.reports.daily_arrived', compact('arrived'));
    }

    public function staffWiseArrival(): \Illuminate\View\View
    {
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::user()->account_id);
        $Users = User::getAllRecords(Auth::user()->account_id)->whereNotIn('user_type_id', 5)->where('active', 1)->getDictionary();

        return view('admin.reports.staffwisearrival', compact('locations', 'Users'));
    }

    public function doctorWiseConversion(): \Illuminate\View\View
    {

        $Users = User::getAllRecords(Auth::user()->account_id)->where('user_type_id', 5)->where('active', 1)->getDictionary();
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::user()->account_id);
        return view('admin.reports.doctorwiseconversion', compact('Users', 'locations'));
    }

    public function staffWiseArrivalReport(Request $request): \Illuminate\View\View
    {
        $where = [];
        if (isset($request->date_range) && $request->date_range) {
            $date_range = explode(' - ', $request->date_range);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        $locationIds = $this->normaliseLocationIds($request->input('location_id'));
        $locations = empty($locationIds) ? ACL::getUserCentres() : $locationIds;

        if ($request->created_by && $request->created_by != null) {
            $where[] = [['user_id' => $request->created_by]];
        }
        $records = [];
        $records['data'] = [];

        $fdm_users = RoleHasUsers::where(['role_id' => 4])->pluck('user_id')->toArray();

        // Get arrived and converted appointment status IDs
        $arrivedStatus = \App\Models\AppointmentStatuses::where(['account_id' => Auth::user()->account_id, 'is_arrived' => 1])->first();
        $convertedStatus = \App\Models\AppointmentStatuses::where(['account_id' => Auth::user()->account_id, 'is_converted' => 1])->first();
        $arrivedStatusId = $arrivedStatus ? $arrivedStatus->id : 2;
        $convertedStatusId = $convertedStatus ? $convertedStatus->id : 16;
        $arrivedStatusIds = $convertedStatusId ? [$arrivedStatusId, $convertedStatusId] : [$arrivedStatusId];

        // Fetch all records for set-based counting logic
        $allRecordsQuery = AppointmentsDailyStats::select('id', 'appointment_id', 'appointment_status_id', 'user_id')
            ->whereIn('centre_id', $locations)
            ->whereBetween('scheduled_date', [$start_date, $end_date])
            ->orderBy('appointment_id')
            ->orderBy('id');

        if (count($where)) {
            $allRecordsQuery->where($where);
        }

        $allRecords = $allRecordsQuery->get();

        // Group records by appointment_id
        $groupedByAppointment = [];
        foreach ($allRecords as $record) {
            $appointmentId = $record->appointment_id;
            if (!isset($groupedByAppointment[$appointmentId])) {
                $groupedByAppointment[$appointmentId] = [];
            }
            $groupedByAppointment[$appointmentId][] = $record;
        }

        // Calculate set-based counts
        // Logic: Make sets of 2 records per appointment_id
        // - Each set counts as 1 in total
        // - If a set has at least one arrived/converted status, count 1 as arrived
        $totalSets = 0;
        $arrivedSets = 0;
        $walkinSets = 0;

        foreach ($groupedByAppointment as $appointmentId => $records) {
            $recordCount = count($records);
            $setCount = ceil($recordCount / 2);

            for ($i = 0; $i < $setCount; $i++) {
                $setStart = $i * 2;
                $setRecords = array_slice($records, $setStart, 2);

                // Check if this set has at least one arrived/converted status
                $hasArrived = false;
                $isWalkin = false;

                foreach ($setRecords as $record) {
                    if (in_array($record->appointment_status_id, $arrivedStatusIds, true)) {
                        $hasArrived = true;
                        // Check if this is a walk-in (created by FDM user)
                        if (!empty($fdm_users) && in_array($record->user_id, $fdm_users, true)) {
                            $isWalkin = true;
                        }
                        break;
                    }
                }

                // Count every set in total
                $totalSets++;

                if ($hasArrived) {
                    // Arrived set: count 1 arrived
                    $arrivedSets++;
                    if ($isWalkin) {
                        $walkinSets++;
                    }
                }
            }
        }

        $arrived = $arrivedSets;
        $walkin_customers = $request->created_by == null ? $walkinSets : 0;
        $totalScheduled = $totalSets;

        if (Gate::allows('consultations_manage') && Gate::allows('treatments_services') || Gate::allows('consultations_manage')) {
            $resultQuery = AppointmentsDailyStats::whereIn('centre_id', $locations);
        }
        if (count($where)) {
            $resultQuery->where($where);
        }

        $Appointments = $resultQuery->with(['user', 'appointment' => function ($q) {
            $q->select('*', 'appointments.name as patient_name', 'appointments.id as app_id', 'appointments.created_by as app_created_by', 'appointments.updated_by as app_updated_by', 'appointments.created_at as app_created_at')
                ->orderBy('appointments.created_at', 'DESC');
        }])
            ->whereBetween('scheduled_date', [$start_date, $end_date])
            ->get();

        $user = User::where(['id' => $request->created_by])->first()->name ?? '';
        $centre = empty($locationIds)
            ? 'All centres'
            : Locations::whereIn('id', $locationIds)->pluck('name')->implode(', ');

        return view('admin.reports.staff_wise_arrived', compact('Appointments', 'user', 'centre', 'start_date', 'end_date'));
    }

    public function doctorWiseConversionReport(Request $request): \Illuminate\View\View
    {
        // This is a placeholder; the original file has no doctorWiseConversionReport method body beyond doctorWiseConversion view
        $Users = User::getAllRecords(Auth::user()->account_id)->where('user_type_id', 5)->where('active', 1)->getDictionary();
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::user()->account_id);
        return view('admin.reports.doctorwiseconversion', compact('Users', 'locations'));
    }

    public function loadIncentiveReport(Request $request): \Illuminate\View\View
    {
        // Parse the date range input
        $dates = explode(' - ', $request->input('date_range'));
        $startDate = date('Y-m-d 00:00:00', strtotime($dates[0]));
        $endDate = date('Y-m-d 23:59:59', strtotime($dates[1]));

        $centerIds = $this->normaliseLocationIds($request->input('centre_id'));
        $doctorId = $request->input('doctor_id');

        if (empty($centerIds)) {
            abort(422, 'At least one centre must be selected for the incentive report.');
        }

        // Step 1: Calculate total revenue in the given date range from package_advances
        $totalRevenueQuery = PackageAdvances::whereIn('package_advances.location_id', $centerIds)
                        ->whereBetween('package_advances.created_at', [$startDate, $endDate])
                        ->where('cash_flow', 'in')

                        ->where('cash_amount', '>', 0)
                        ->join('appointments', 'package_advances.appointment_id', '=', 'appointments.id');
                       // ->sum('cash_amount');
            if($doctorId) {
                $totalRevenueQuery->where('appointments.doctor_id', $doctorId);
            }
            $totalRevenuewithRefund = $totalRevenueQuery->sum('package_advances.cash_amount');
            $totalRefund = PackageAdvances::whereIn('package_advances.location_id', $centerIds)
                        ->whereBetween('package_advances.created_at', [$startDate, $endDate])
                        ->where('cash_flow', 'out')
                        ->where('cash_amount', '>', 0)
                        ->where('is_refund', 1)
                        ->sum('cash_amount');
                        $totalRevenue = $totalRevenuewithRefund - $totalRefund;
            $monthWiseRevenueQuery = PackageAdvances::whereIn('package_advances.location_id', $centerIds)
                ->where('cash_flow', 'in')
                ->where('cash_amount', '>', 0)
                ->where('is_refund', 0)
                ->whereBetween('package_advances.created_at', [$startDate, $endDate])
                ->join('appointments', 'package_advances.appointment_id', '=', 'appointments.id') // Join with appointments
                ->select(
                    \DB::raw('DATE_FORMAT(appointments.scheduled_date, "%Y-%m") as revenue_month'),
                    \DB::raw('SUM(package_advances.cash_amount) as monthly_total')
                )
                ->groupBy('revenue_month')
                ->orderBy('revenue_month');

        if ($doctorId) {
            // Apply doctor filter if doctor_id is provided
            $appointmentsInRange = PackageAdvances::select('appointment_id', DB::raw('MIN(created_at) as first_payment_date'))
            ->where('cash_flow', '=', 'in')
            ->where('cash_amount', '>', 0)
            ->where('is_refund', 0)
            ->whereIn('location_id', $centerIds)
            ->groupBy('appointment_id')
            ->havingRaw('first_payment_date BETWEEN ? AND ?', [$startDate, $endDate])
            ->pluck('appointment_id');

            // Step 2: Sum `cash_amount` for appointments where all payments fall within the specified date range.
            $totalCashAmount = PackageAdvances::where('cash_flow', '=', 'in')
                ->where('cash_amount', '>', 0)

                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('location_id', $centerIds)
                ->whereIn('appointment_id', function ($query) use ($appointmentsInRange, $doctorId) {
                    $query->select('id')
                        ->from('appointments')
                        ->where('appointment_type_id', '=', AppointmentType::Consultancy->value)
                        ->where('doctor_id', '=', $doctorId)
                        ->whereIn('id', $appointmentsInRange);
                })
                ->sum('cash_amount');
                $totalDoctorRevenue = PackageAdvances::where('cash_flow', '=', 'in')
                    ->where('cash_amount', '>', 0)
                    ->where('is_refund', 0)
                    ->whereIn('location_id', $centerIds)
                    ->whereBetween('package_advances.created_at', [$startDate, $endDate])
                    ->whereIn('appointment_id', function ($query) use ($doctorId) {
                        $query->select('id')
                            ->from('appointments')
                            ->where('doctor_id', '=', $doctorId);
                    })
                    ->sum('cash_amount');
          $diff = $totalDoctorRevenue - $totalCashAmount;
          $patients = PackageAdvances::select(
            'appointments.patient_id',
            'appointments.scheduled_date',
            'users.name as patient_name',
            'package_advances.created_at as payment_date',
            'package_advances.cash_amount'
        )
        ->join('appointments', 'appointments.id', '=', 'package_advances.appointment_id')
        ->join('users', 'users.id', '=', 'appointments.patient_id')
        ->where('package_advances.cash_flow', '=', 'in')
        ->where('package_advances.cash_amount', '>', 0)
        ->whereIn('package_advances.location_id', $centerIds)
        ->whereBetween('package_advances.created_at', [$startDate, $endDate])
        ->where('appointments.doctor_id', '=', $doctorId)
        ->get();
            // $monthWiseRevenueQuery->where('appointments.doctor_id', $doctorId);
            return view('admin.reports.doctor_incentive_report', compact('totalCashAmount', 'totalDoctorRevenue','diff','patients'));

        } else {
            // No doctor filter, continue as usual
        }

        $monthWiseRevenue = $monthWiseRevenueQuery->pluck('monthly_total', 'revenue_month'); // Retrieve as a key-value pair (month => total)

        return view('admin.reports.incentive_report', compact('totalRevenue', 'monthWiseRevenue'));
    }

    /**
     * Display Tax Calculation Report filter page.
     *
     * @return \Illuminate\Http\Response
     */
    public function taxCalculationReport(): \Illuminate\View\View
    {

        $locations = Locations::getActiveSorted(ACL::getUserCentres());

        return view('admin.reports.taxcalculationreport.index', compact('locations'));
    }

    /**
     * Load Tax Calculation Report data.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function taxCalculationReportLoad(TaxCalculationReportRequest $request): \Illuminate\View\View
    {

        // Parse date range
        $date_range = explode(' - ', $request->get('date_range'));
        $start_date = date('Y-m-d', strtotime($date_range[0]));
        $end_date = date('Y-m-d', strtotime($date_range[1]));

        // Get filter parameters
        $location_id = $request->get('location_id');
        $bank_taxable = $request->get('bank_taxable');
        $cash_taxable = $request->get('cash_taxable');
        $consultation_amount = $request->get('consultation_amount');
        $medium_type = $request->get('medium_type');

        // TODO: Add your report logic here
        // For now, just passing the parameters to a view
        $report_data = [];

        switch ($medium_type) {
            case 'web':
                return view('admin.reports.taxcalculationreport.report', compact(
                    'report_data',
                    'start_date',
                    'end_date',
                    'bank_taxable',
                    'cash_taxable',
                    'consultation_amount'
                ));
            case 'print':
                return view('admin.reports.taxcalculationreport.reportprint', compact(
                    'report_data',
                    'start_date',
                    'end_date',
                    'bank_taxable',
                    'cash_taxable',
                    'consultation_amount'
                ));
            case 'pdf':
                $pdf = PDF::loadView('admin.reports.taxcalculationreport.reportpdf', compact(
                    'report_data',
                    'start_date',
                    'end_date',
                    'bank_taxable',
                    'cash_taxable',
                    'consultation_amount'
                ));
                return $pdf->stream('tax-calculation-report.pdf');
            case 'excel':
                // TODO: Implement Excel export
                break;
        }
    }
}
