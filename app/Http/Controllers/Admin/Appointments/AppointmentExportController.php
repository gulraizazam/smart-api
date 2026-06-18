<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Appointments;

use App\Exports\ExportAppointment;
use App\Exports\ExportConsultancies;
use App\Exports\ExportToday;
use App\Exports\TodayTreatment;
use App\Helpers\ACL;
use App\Helpers\ActivityLogger;
use App\Models\Accounts;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\AuditTrailActions;
use App\Models\AuditTrails;
use App\Models\AuditTrailTables;
use App\Models\Regions;
use App\Models\User;
use App\Services\Phone\PhoneFormattingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppointmentExportController extends AppointmentBaseController
{
    public function todayexport(): BinaryFileResponse
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0'); // for infinite time of execution
        $limit = 1000;
        $offset = 0;

        $this->logExport('today_consultancies');

        return Excel::download(new ExportToday($limit, $offset), 'todayconsultancies.xlsx');
    }

    public function todaytreatments(): BinaryFileResponse
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0'); // for infinite time of execution
        $limit = 1000;
        $offset = 0;

        $this->logExport('today_treatments');

        return Excel::download(new TodayTreatment($limit, $offset), 'todaytreatments.xlsx');
    }

    public function downloadExportdata(Request $request): BinaryFileResponse
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0'); // for infinite time of execution
        $limit = 10000;
        $offset = 0;

        $exportType = $request->appointmenttype == 1 ? 'consultancies' : 'appointments';
        $this->logExport($exportType, $this->sanitizeExportFilters($request));

        if ($request->appointmenttype == 1) {
            return Excel::download(new ExportConsultancies($limit, $offset, $request), 'consultancies.xlsx');
        } else {
            return Excel::download(new ExportConsultancies($limit, $offset, $request), 'appointments.xlsx');
        }
    }

    public function appointmentexcel(Request $request): RedirectResponse
    {
        $today = Carbon::now()->toDateString();
        $this_month = Carbon::now()->firstOfMonth()->toDateString();
        $created_F = '';
        $created_T = '';
        $schedule_F = '';
        $schedule_T = '';
        $where = [];
        if ($request->patient_id && $request->patient_id != '') {
            $where[] = [['users.id' => $request->patient_id]];
        }
        if ($request->phone && $request->phone != '') {
            $where[] = [
                'users.phone',
                'like',
                '%'.PhoneFormattingService::cleanNumber($request->phone).'%',
            ];
        }
        if (Gate::allows('appointments_export_all')) {
            if ($request->date_from && $request->date_from != '') {
                $where[] = [
                    'appointments.scheduled_date',
                    '>=',
                    $request->date_from.' 00:00:00',
                ];
                $schedule_F = $request->date_from;
            }
            if ($request->date_to && $request->date_to != '') {
                $where[] = [
                    'appointments.scheduled_date',
                    '<=',
                    $request->date_to.'23:59:59',
                ];
                $schedule_T = $request->date_to;
            }
        } elseif (Gate::allows('appointments_export_today')) {
            $where[] = [
                'appointments.scheduled_date',
                '>=',
                $today.' 00:00:00',
            ];
            $schedule_F = $today;
            $where[] = [
                'appointments.scheduled_date',
                '<=',
                $today.'23:59:59',
            ];
            $schedule_T = $today;
        } elseif (Gate::allows('appointments_export_this_month')) {
            $where[] = [
                'appointments.scheduled_date',
                '>=',
                $this_month.' 00:00:00',
            ];
            $schedule_F = $this_month;
            $where[] = [
                'appointments.scheduled_date',
                '<=',
                $today.'23:59:59',
            ];
            $schedule_T = $today;
        }
        if ($request->doctor_id && $request->doctor_id != '') {
            $where[] = [
                'doctor_id',
                '=',
                $request->doctor_id,
            ];
        }
        if ($request->region_id && $request->region_id != '') {
            $where[] = [['region_id' => $request->region_id]];
        }
        if ($request->city_id && $request->city_id != '') {
            $where[] = [['city_id' => $request->city_id]];
        }
        if ($request->location_id && $request->location_id != '') {
            $where[] = [['location_id' => $request->location_id]];
        }
        if ($request->service_id && $request->service_id != '') {
            $where[] = [['service_id' => $request->service_id]];
        }
        if ($request->created_by && $request->created_by != '') {
            $where[] = [['appointments.created_by' => $request->created_by]];
        }
        if ($request->converted_by && $request->converted_by != '') {
            $where[] = [['appointments.converted_by' => $request->converted_by]];
        }
        if ($request->updated_by && $request->updated_by != '') {
            $where[] = [['appointments.updated_by' => $request->updated_by]];
        }
        if ($request->appointment_status_id && $request->appointment_status_id != '') {
            $where[] = [['appointments.base_appointment_status_id' => $request->appointment_status_id]];
        }
        if ($request->appointment_type_id && $request->appointment_type_id != '') {
            $where[] = [['appointments.appointment_type_id' => $request->appointment_type_id]];
        }
        if ($request->consultancy_type && $request->consultancy_type != '') {
            $where[] = [['appointments.consultancy_type' => $request->consultancy_type]];
        }
        if (Gate::allows('appointments_export_all')) {
            if ($request->created_from && $request->created_from != '') {
                $where[] = [
                    'appointments.created_at',
                    '>=',
                    $request->created_from.' 00:00:00',
                ];
                $created_F = $request->created_from;
            }
            if ($request->created_to && $request->created_to != '') {
                $where[] = [
                    'appointments.created_at',
                    '<=',
                    $request->created_to.' 23:59:59',
                ];
                $created_T = $request->created_to;
            }
        }
        $consultancyslug = AppointmentTypes::where('slug', '=', 'consultancy')->first();
        $treatmentslug = AppointmentTypes::where('slug', '=', 'treatment')->first();
        $records = [];
        $records['data'] = [];
        if (Gate::allows('consultations.list.view')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $consultancyslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('treatments.list.view')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $treatmentslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('consultations.list.view') && Gate::allows('treatments.list.view')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (! Gate::allows('consultations.list.view') && ! Gate::allows('treatments.list.view')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where([
                ['appointments.appointment_type_id', '!=', $consultancyslug->id],
                ['appointments.appointment_type_id', '!=', $treatmentslug->id],
            ])
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (count($where)) {
            $resultQuery->where($where);
        }
        if ($request->name && $request->name != '') {
            $resultQuery->where(function ($query) {
                global $request;
                $query->where(
                    'users.name',
                    'like',
                    '%'.$request->name.'%'
                );
                $query->orWhere(
                    'appointments.name',
                    'like',
                    '%'.$request->name.'%'
                );
            });
        }
        if ($request->name && $request->name != '') {
            $resultQuery->where(function ($query) use ($request) {
                $query->where(
                    'users.name',
                    'like',
                    '%'.$request->name.'%'
                );
                $query->orWhere(
                    'appointments.name',
                    'like',
                    '%'.$request->name.'%'
                );
            });
        }
        $Appointments_count = $resultQuery->select('*', 'appointments.name as patient_name', 'appointments.id as app_id', 'appointments.created_by as app_created_by', 'appointments.updated_by as app_updated_by', 'appointments.created_at as app_created_at')->count();
        if ($Appointments_count > 10000) {
            flash('The data you are trying to pull is too large in size. Please apply some filters to reduce the data count ( maximum 10,000 ) to be able to export it.')->warning();

            return redirect()->back();
        }
        $Appointments = $resultQuery->select('*', 'appointments.name as patient_name', 'appointments.id as app_id', 'appointments.created_by as app_created_by', 'appointments.updated_by as app_updated_by', 'appointments.created_at as app_created_at')->orderBy('appointments.created_at', 'desc')->get();
        $spreadsheet = new Spreadsheet;  /* ----Spreadsheet object----- */
        $Excel_writer = new Xlsx($spreadsheet);  /* ----- Excel (Xls) Object */
        $Excel_writer->setPreCalculateFormulas(false);
        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();
        $activeSheet->setCellValue('A1', 'ID')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'Patient')->getStyle('B1')->getFont()->setBold(true);
        $activeSheet->setCellValue('C1', 'Phone')->getStyle('C1')->getFont()->setBold(true);
        $activeSheet->setCellValue('D1', 'Scheduled')->getStyle('D1')->getFont()->setBold(true);
        $activeSheet->setCellValue('E1', 'Doctor')->getStyle('E1')->getFont()->setBold(true);
        $activeSheet->setCellValue('F1', 'Region')->getStyle('F1')->getFont()->setBold(true);
        $activeSheet->setCellValue('G1', 'City')->getStyle('G1')->getFont()->setBold(true);
        $activeSheet->setCellValue('H1', 'Centre')->getStyle('H1')->getFont()->setBold(true);
        $activeSheet->setCellValue('I1', 'Service')->getStyle('I1')->getFont()->setBold(true);
        $activeSheet->setCellValue('J1', 'Status')->getStyle('J1')->getFont()->setBold(true);
        $activeSheet->setCellValue('K1', 'Type')->getStyle('K1')->getFont()->setBold(true);
        $activeSheet->setCellValue('L1', 'Consultancy Type')->getStyle('L1')->getFont()->setBold(true);
        $activeSheet->setCellValue('M1', 'Created At')->getStyle('M1')->getFont()->setBold(true);
        $activeSheet->setCellValue('N1', 'Created By')->getStyle('N1')->getFont()->setBold(true);
        $activeSheet->setCellValue('O1', 'Updated By')->getStyle('O1')->getFont()->setBold(true);
        $activeSheet->setCellValue('P1', 'Reschedule By')->getStyle('P1')->getFont()->setBold(true);
        $counter = 2;
        if (count($Appointments)) {
            $Regions = Regions::getAllRecordsDictionary(Auth::user()->account_id);
            $Users = User::getAllRecords(Auth::user()->account_id)->getDictionary();
            $AppointmentStatuses = AppointmentStatuses::getAllRecordsDictionary(Auth::user()->account_id);
            foreach ($Appointments as $appointment) {
                if ($appointment->consultancy_type == 'in_person') {
                    $consultancy_type = 'In Person';
                } elseif ($appointment->consultancy_type == 'virtual') {
                    $consultancy_type = 'Virtual';
                } else {
                    $consultancy_type = '';
                }
                // Round 4 Inj-H4: every user-controlled cell (patient
                // name, doctor name, region/city/location/service names,
                // creator names) is defanged via csv_safe so a name like
                // `=cmd|...` cannot execute when the XLSX is opened.
                $activeSheet->setCellValue('A'.$counter, $appointment->patient_id);
                $activeSheet->setCellValue('B'.$counter, csv_safe(($appointment->patient_name) ? $appointment->patient_name : $appointment->name));
                $activeSheet->setCellValue('C'.$counter, csv_safe(\App\Helpers\PhoneFormattingService::prepareNumber4Call($appointment->patient->phone, 1)));
                $activeSheet->setCellValue('D'.$counter, ($appointment->scheduled_date) ? Carbon::parse($appointment->scheduled_date, null)->format('M j, Y').' at '.Carbon::parse($appointment->scheduled_time, null)->format('h:i A') : '-');
                $activeSheet->setCellValue('E'.$counter, csv_safe($appointment->doctor->name));
                $activeSheet->setCellValue('F'.$counter, csv_safe((array_key_exists($appointment->region_id, $Regions)) ? $Regions[$appointment->region_id]->name : 'N/A'));
                $activeSheet->setCellValue('G'.$counter, csv_safe($appointment->city?->name ?? 'N/A'));
                $activeSheet->setCellValue('H'.$counter, csv_safe($appointment->location?->name ?? 'N/A'));
                $activeSheet->setCellValue('I'.$counter, csv_safe($appointment->service->name));
                $activeSheet->setCellValue('J'.$counter, csv_safe(($appointment->appointment_status_id ? ($appointment->appointment_status->parent_id ? $AppointmentStatuses[$appointment->appointment_status->parent_id]->name : $appointment->appointment_status->name) : '')));
                $activeSheet->setCellValue('K'.$counter, csv_safe($appointment->appointment_type->name));
                $activeSheet->setCellValue('L'.$counter, $consultancy_type);
                $activeSheet->setCellValue('M'.$counter, Carbon::parse($appointment->app_created_at)->format('F j,Y h:i A'));
                $activeSheet->setCellValue('N'.$counter, csv_safe(array_key_exists($appointment->app_created_by, $Users) ? $Users[$appointment->app_created_by]->name : 'N/A'));
                $activeSheet->setCellValue('O'.$counter, csv_safe(array_key_exists($appointment->converted_by, $Users) ? $Users[$appointment->converted_by]->name : 'N/A'));
                $activeSheet->setCellValue('p'.$counter, csv_safe(array_key_exists($appointment->app_updated_by, $Users) ? $Users[$appointment->app_updated_by]->name : 'N/A'));
                $counter++;
            }
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'General Report'.'.xlsx"'); /* -- $filename is  xsl filename --- */
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    public function export(Request $request, int $limit = 1000, int $offset = 0): BinaryFileResponse
    {

        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0'); // for infinite time of execution

        $this->logExport('appointments', $this->sanitizeExportFilters($request));

        return Excel::download(new ExportAppointment($limit, $offset), 'appointments.xlsx');
    }

    /**
     * Fire an [EXPORT] audit row before streaming the file to the browser.
     * Silent-fail: audit failures never block the download itself.
     *
     * Row count is approximated from the `limit` parameter — each exporter
     * already caps its result set at a fixed limit, and counting exactly
     * would require an extra query just for the audit log. The approximation
     * is labeled in the filter summary.
     *
     * @param  array<string, mixed>  $filters
     */
    private function logExport(string $exportType, array $filters = []): void
    {
        try {
            // Best-effort approximate row count from limit; exporter will
            // produce at most this many rows.
            $approxRows = (int) ($filters['_limit'] ?? 0);
            unset($filters['_limit']);

            ActivityLogger::logDataExport(
                exportType: $exportType,
                rowCount: $approxRows,
                filters: $filters,
            );
        } catch (\Throwable $e) {
            Log::warning('activities.data_export.audit_write_failed', [
                'event' => 'activities.data_export.audit_write_failed',
                'export_type' => $exportType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pluck audit-relevant filter keys out of the Request, dropping verbose
     * values that would bloat the audit description.
     *
     * @return array<string, mixed>
     */
    private function sanitizeExportFilters(Request $request): array
    {
        $keep = ['appointmenttype', 'date_from', 'date_to', 'location_id', 'service_id', 'status', 'doctor_id'];
        $out = [];
        foreach ($keep as $k) {
            $v = $request->input($k);
            if ($v !== null && $v !== '') {
                $out[$k] = is_array($v) ? implode(',', $v) : (string) $v;
            }
        }

        return $out;
    }

    public function viewLog(int $id, string $type): JsonResponse
    {
        if (! Gate::allows('appointments_log')) {
            abort(404);
        }
        $appointments = AuditTrailTables::whereName('appointments')->first();
        $audit_trails = AuditTrails::has('auditTrailChanges')->with('auditTrailChanges')->where('audit_trail_table_name', '=', $appointments->id)->where('table_record_id', '=', $id)->get();
        $data = [];
        foreach ($audit_trails as $audit_trail) {
            $audit_trail_action = AuditTrailActions::find($audit_trail->audit_trail_action_name);
            $data[$audit_trail->id] = [
                'action' => $audit_trail_action->name,
                'caused_by' => $audit_trail->userr->name,
                'created_at' => $audit_trail->created_at,
            ];
            foreach ($audit_trail->auditTrailChanges as $auditTrailChange) {
                $company = Accounts::find(1, ['name']);
                $data[$audit_trail->id]['company'] = $company->name;
                switch ($auditTrailChange->field_name) {
                    case 'scheduled_date':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->field_after;
                        break;
                    case 'scheduled_time':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->field_after;
                        break;
                    case 'name':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->field_after;
                        break;
                    case 'patient_id':
                        $data[$audit_trail->id]['phone'] = $auditTrailChange->user->phone;
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->field_after;
                        break;
                    case 'appointment_type_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->AppointmentType->name;
                        break;
                    case 'base_appointment_status_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->appointmentStatus->name;
                        break;
                    case 'appointment_status_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->appointmentStatus->name;
                        break;
                    case 'created_by':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->appointmentCreatedBy->name;
                        break;
                    case 'updated_by':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->appointmentCreatedBy->name;
                        break;
                    case 'converted_by':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->appointmentCreatedBy->name;
                        break;
                    case 'service_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->service->name;
                        break;
                    case 'doctor_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->doctor->name;
                        break;
                    case 'resource_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->resource->name;
                        break;
                    case 'region_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->region->name;
                        break;
                    case 'city_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->city->name;
                        break;
                    case 'location_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->location->name;
                        break;
                    case 'send_message':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->field_after;
                        break;
                }
            }
        }
        if ($type === 'web') {
            $records['data'] = $data;
            $records['meta'] = [
                'field' => 'action',
                'page' => 1,
                'pages' => count($data),
                'perpage' => 20,
                'total' => count($data),
                'sort' => 'DESC',
            ];
            $records['permissions'] = [
                'contact' => Gate::allows('contact'),
            ];

            return response()->json($records);
        }

        return $this->viewLogInExcel($id, $data);
    }

    public function viewLogInExcel(int $id, mixed $data): void
    {
        $appointment = Appointments::withTrashed()->find($id);
        $spreadsheet = new Spreadsheet;  /* ----Spreadsheet object----- */
        $Excel_writer = new Xlsx($spreadsheet);  /* ----- Excel (Xls) Object */
        $Excel_writer->setPreCalculateFormulas(false);
        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();
        $activeSheet->setCellValue('A1', 'APPOINTMENT ID')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', $id);
        if ($appointment->appointment_type_id === config('constants.appointment_type_service')) {
            $activeSheet->setCellValue('A2', '#')->getStyle('A2')->getFont()->setBold(true);
            $activeSheet->setCellValue('B2', 'Action')->getStyle('B2')->getFont()->setBold(true);
            $activeSheet->setCellValue('C2', 'Patient Name')->getStyle('C2')->getFont()->setBold(true);
            $activeSheet->setCellValue('D2', 'Phone')->getStyle('D2')->getFont()->setBold(true);
            $activeSheet->setCellValue('E2', 'Scheduled At')->getStyle('E2')->getFont()->setBold(true);
            $activeSheet->setCellValue('F2', 'Doctor')->getStyle('F2')->getFont()->setBold(true);
            $activeSheet->setCellValue('G2', 'Resource')->getStyle('G2')->getFont()->setBold(true);
            $activeSheet->setCellValue('H2', 'Region')->getStyle('H2')->getFont()->setBold(true);
            $activeSheet->setCellValue('I2', 'City')->getStyle('I2')->getFont()->setBold(true);
            $activeSheet->setCellValue('J2', 'Centre')->getStyle('J2')->getFont()->setBold(true);
            $activeSheet->setCellValue('K2', 'Service')->getStyle('K2')->getFont()->setBold(true);
            $activeSheet->setCellValue('L2', 'Parent Status')->getStyle('L2')->getFont()->setBold(true);
            $activeSheet->setCellValue('M2', 'Child Status')->getStyle('M2')->getFont()->setBold(true);
            $activeSheet->setCellValue('N2', 'Type')->getStyle('N2')->getFont()->setBold(true);
            $activeSheet->setCellValue('O2', 'Created At')->getStyle('O2')->getFont()->setBold(true);
            $activeSheet->setCellValue('P2', 'Created By')->getStyle('P2')->getFont()->setBold(true);
            $activeSheet->setCellValue('Q2', 'Updated By')->getStyle('Q2')->getFont()->setBold(true);
            $activeSheet->setCellValue('R2', 'Rescheduled By')->getStyle('R2')->getFont()->setBold(true);
            $activeSheet->setCellValue('S2', 'Message')->getStyle('S2')->getFont()->setBold(true);
            $counter = 4;
            $count = 1;
            if (count($data)) {
                foreach ($data as $log) {
                    $activeSheet->setCellValue('A'.$counter, $count++);
                    $activeSheet->setCellValue('B'.$counter, $log['action']);
                    $activeSheet->setCellValue('C'.$counter, $log['name'] ?? '-');
                    $activeSheet->setCellValue('D'.$counter, isset($log['phone']) ? \App\Helpers\PhoneFormattingService::prepareNumber4Call($log['phone']) : '-');
                    if (isset($log['scheduled_date']) && isset($log['scheduled_time'])) {
                        $activeSheet->setCellValue('E'.$counter, Carbon::parse($log['scheduled_date'], null)->format('M j, Y').' at '.Carbon::parse($log['scheduled_time'], null)->format('h:i A'));
                    } elseif (isset($log['scheduled_time'])) {
                        $activeSheet->setCellValue('E'.$counter, Carbon::parse($log['scheduled_time'], null)->format('h:i A'));
                    } elseif (isset($log['scheduled_date'])) {
                        $activeSheet->setCellValue('E'.$counter, Carbon::parse($log['scheduled_date'], null)->format('M j, Y'));
                    } else {
                        $activeSheet->setCellValue('E'.$counter, '-');
                    }
                    $activeSheet->setCellValue('F'.$counter, $log['doctor_id'] ?? '-');
                    $activeSheet->setCellValue('G'.$counter, $log['resource_id'] ?? '-');
                    $activeSheet->setCellValue('H'.$counter, $log['region_id'] ?? '-');
                    $activeSheet->setCellValue('I'.$counter, $log['city_id'] ?? '-');
                    $activeSheet->setCellValue('J'.$counter, $log['location_id'] ?? '-');
                    $activeSheet->setCellValue('K'.$counter, $log['service_id'] ?? '-');
                    $activeSheet->setCellValue('L'.$counter, $log['base_appointment_status_id'] ?? '-');
                    $activeSheet->setCellValue('M'.$counter, $log['appointment_status_id'] ?? '-');
                    $activeSheet->setCellValue('N'.$counter, $log['appointment_type_id'] ?? '-');
                    $activeSheet->setCellValue('O'.$counter, isset($log['created_at']) ? Carbon::parse($log['created_at'])->format('F j,Y h:i A') : '-');
                    $activeSheet->setCellValue('P'.$counter, $log['created_by'] ?? '-');
                    $activeSheet->setCellValue('Q'.$counter, $log['converted_by'] ?? '-');
                    $activeSheet->setCellValue('R'.$counter, $log['updated_by'] ?? '-');
                    $activeSheet->setCellValue('S'.$counter, isset($log['send_message']) ? ($log['send_message'] == 1) ? 'Sent' : 'Not Sent' : '-');
                    $counter++;
                }
            }
        } else {
            $activeSheet->setCellValue('A2', '#')->getStyle('A2')->getFont()->setBold(true);
            $activeSheet->setCellValue('B2', 'Action')->getStyle('B2')->getFont()->setBold(true);
            $activeSheet->setCellValue('C2', 'Patient Name')->getStyle('C2')->getFont()->setBold(true);
            $activeSheet->setCellValue('D2', 'Phone')->getStyle('D2')->getFont()->setBold(true);
            $activeSheet->setCellValue('E2', 'Scheduled At')->getStyle('E2')->getFont()->setBold(true);
            $activeSheet->setCellValue('F2', 'Doctor')->getStyle('F2')->getFont()->setBold(true);
            $activeSheet->setCellValue('G2', 'Region')->getStyle('G2')->getFont()->setBold(true);
            $activeSheet->setCellValue('H2', 'City')->getStyle('H2')->getFont()->setBold(true);
            $activeSheet->setCellValue('I2', 'Centre')->getStyle('I2')->getFont()->setBold(true);
            $activeSheet->setCellValue('J2', 'Service')->getStyle('J2')->getFont()->setBold(true);
            $activeSheet->setCellValue('K2', 'Parent Status')->getStyle('K2')->getFont()->setBold(true);
            $activeSheet->setCellValue('L2', 'Child Status')->getStyle('L2')->getFont()->setBold(true);
            $activeSheet->setCellValue('M2', 'Type')->getStyle('M2')->getFont()->setBold(true);
            $activeSheet->setCellValue('N2', 'Created At')->getStyle('N2')->getFont()->setBold(true);
            $activeSheet->setCellValue('O2', 'Created By')->getStyle('O2')->getFont()->setBold(true);
            $activeSheet->setCellValue('P2', 'Updated By')->getStyle('P2')->getFont()->setBold(true);
            $activeSheet->setCellValue('Q2', 'Rescheduled By')->getStyle('Q2')->getFont()->setBold(true);
            $activeSheet->setCellValue('R2', 'Message')->getStyle('R2')->getFont()->setBold(true);
            $counter = 4;
            $count = 1;
            if (count($data)) {
                foreach ($data as $log) {
                    $activeSheet->setCellValue('A'.$counter, $count++);
                    $activeSheet->setCellValue('B'.$counter, $log['action']);
                    $activeSheet->setCellValue('C'.$counter, $log['name'] ?? '-');
                    $activeSheet->setCellValue('D'.$counter, isset($log['phone']) ? \App\Helpers\PhoneFormattingService::prepareNumber4Call($log['phone']) : '-');
                    if (isset($log['scheduled_date']) && isset($log['scheduled_time'])) {
                        $activeSheet->setCellValue('E'.$counter, Carbon::parse($log['scheduled_date'], null)->format('M j, Y').' at '.Carbon::parse($log['scheduled_time'], null)->format('h:i A'));
                    } elseif (isset($log['scheduled_time'])) {
                        $activeSheet->setCellValue('E'.$counter, Carbon::parse($log['scheduled_time'], null)->format('h:i A'));
                    } elseif (isset($log['scheduled_date'])) {
                        $activeSheet->setCellValue('E'.$counter, Carbon::parse($log['scheduled_date'], null)->format('M j, Y'));
                    } else {
                        $activeSheet->setCellValue('E'.$counter, '-');
                    }
                    $activeSheet->setCellValue('F'.$counter, $log['doctor_id'] ?? '-');
                    $activeSheet->setCellValue('G'.$counter, $log['region_id'] ?? '-');
                    $activeSheet->setCellValue('H'.$counter, $log['city_id'] ?? '-');
                    $activeSheet->setCellValue('I'.$counter, $log['location_id'] ?? '-');
                    $activeSheet->setCellValue('J'.$counter, $log['service_id'] ?? '-');
                    $activeSheet->setCellValue('K'.$counter, $log['base_appointment_status_id'] ?? '-');
                    $activeSheet->setCellValue('L'.$counter, $log['appointment_status_id'] ?? '-');
                    $activeSheet->setCellValue('M'.$counter, $log['appointment_type_id'] ?? '-');
                    $activeSheet->setCellValue('N'.$counter, isset($log['created_at']) ? Carbon::parse($log['created_at'])->format('F j,Y h:i A') : '-');
                    $activeSheet->setCellValue('O'.$counter, $log['created_by'] ?? '-');
                    $activeSheet->setCellValue('P'.$counter, $log['converted_by'] ?? '-');
                    $activeSheet->setCellValue('Q'.$counter, $log['updated_by'] ?? '-');
                    $activeSheet->setCellValue('R'.$counter, isset($log['send_message']) ? ($log['send_message'] == 1) ? 'Sent' : 'Not Sent' : '-');
                    $counter++;
                }
            }
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'AppointmentLog'.'.xlsx"'); /* -- $filename is  xsl filename --- */
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    public function logPage(int $id): View
    {
        return view('admin.appointments.logs.appointmentlog', compact('id'));
    }
}
