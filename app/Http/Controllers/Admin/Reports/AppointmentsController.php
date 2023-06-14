<?php

namespace App\Http\Controllers\Admin\Reports;

use App;
use App\Helpers\ACL;
use App\Helpers\NodesTree;
use App\Http\Controllers\Controller;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\Cities;
use App\Models\Doctors;
use App\Models\Invoices;
use App\Models\Locations;
use App\Models\Regions;
use App\Models\Services;
use App\Models\User;
use App\Reports\Appointments;
use Barryvdh\DomPDF\Facade as PDF;
use Carbon\Carbon;
use Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AppointmentsController extends Controller
{
    public function report()
    {
        if (! Gate::allows('appointment_reports_manage')) {
            return abort(401);
        }

        $allserviceslug = Services::where('slug', '=', 'all')->first();

        $cities = Cities::getActiveSortedFeatured(ACL::getUserCities());
        $cities->prepend('All', '');

        $regions = Regions::getActiveSorted(ACL::getUserRegions());
        $regions->prepend('Select a Region', '');

        $doctors = Doctors::getActiveOnly(ACL::getUserCentres());
        $doctors->prepend('All', '');

        $locations = Locations::getActiveSorted(ACL::getUserCentres());
        $locations->prepend('All', '');

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);
        $services = $parentGroups->nodeList;

        foreach ($services as $key => $ser) {
            if ($key) {
                if ($ser['name'] == $allserviceslug->name) {
                    unset($services[$key]);
                }
            }
        }
        $appointment_statuses = AppointmentStatuses::getAllParentRecords(Auth::User()->account_id);
        if ($appointment_statuses) {
            $appointment_statuses = $appointment_statuses->pluck('name', 'id');
        }
        $appointment_statuses->prepend('All', '');

        $appointment_types = AppointmentTypes::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $appointment_types->prepend('All', '');

        $employees = User::getAllActiveEmployeeRecords(Auth::User()->account_id, ACL::getUserCentres())->pluck('name', 'id');
        $operators = User::getAllActivePractionersRecords(Auth::User()->account_id, ACL::getUserCentres())->pluck('name', 'id');
        $select_All = ['' => 'All'];

        $users = ($select_All + $employees->toArray() + $operators->toArray());

        return view('admin.reports.appointments.general', compact('cities', 'regions', 'doctors', 'locations', 'services', 'appointment_statuses', 'appointment_types', 'users'));
    }

    /**
     * Load Report
     *
     * @return \Illuminate\Http\Response
     */
    public function reportLoad(Request $request)
    {
        switch ($request->get('report_type')) {
            case 'general':
                return self::generalReport($request);
                break;
            case 'general_summary':
                return self::generalReportSummary($request);
                break;
            case 'staff_appointment':
                return self::staffAppointmentScheduleReport($request);
                break;
            case 'referred_by_staff_appointment':
                return self::staffReferredByAppointmentScheduleReport($request);
                break;
            case 'empolyee_summary':
                return self::EmployeeAppointmentSummaryReport($request);
                break;
            case 'summary_by_service':
                return self::appointmentSummaryByServiceReport($request);
                break;
            case 'summary_by_appointment_status':
                return self::appointmentSummaryByStatusReport($request);
                break;
            case 'clients_by_appointment_status':
                return self::clientByAppointmentStatusReport($request);
                break;
            case 'compliance_report':
                return self::complianceReport($request);
                break;
            case 'rescheduled_count_report':
                return self::rescheduledcountreport($request);
                break;
            default:
                return self::generalReport($request);
                break;
        }
    }

    /**
     * Load Clients by Appointment Status (Date Wise) Report
     *
     * @return \Illuminate\Http\Response
     */
    public function clientByAppointmentStatusReport(Request $request)
    {
        if (! Gate::allows('appointment_reports_clients_by_appointment_status')) {
            return abort(401);
        }

        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $filters = [];

        $filters['regions'] = Regions::getAll(Auth::User()->account_id)->getDictionary();
        $filters['doctors'] = Doctors::getAll(Auth::User()->account_id)->getDictionary();
        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_statuses'] = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_types'] = AppointmentTypes::getAllRecords(Auth::User()->account_id)->getDictionary();
        $filters['users'] = User::getAllRecords(Auth::User()->account_id)->getDictionary();
        $reportData = Appointments::getClientByAppointmentStatusReport($request->all(), $filters, Auth::User()->account_id);

        $filters['reportData'] = $reportData;

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.appointments.clients_by_appointment_status.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.appointments.clients_by_appointment_status.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.appointments.clients_by_appointment_status.reportpdf', compact('reportData', 'filters', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A2', 'landscape');

                return $pdf->stream('Employee Appointment Summary Report', 'landscape');
                break;
            case 'excel':
                self::clientByAppointmentStatusReportExcel($reportData, $filters, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.appointments.clients_by_appointment_status.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Load Clients by Appointment Status (Date Wise) Report
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return \Illuminate\Http\Response
     */
    private static function clientByAppointmentStatusReportExcel($reportData, $filters, $start_date, $end_date)
    {
        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');
        $activeSheet->setCellValue('B3', '');

        $activeSheet->setCellValue('A4', 'Region')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Centre')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Date')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', '')->getStyle('D4')->getFont()->setBold(true);
        $activeSheet->setCellValue('E4', '')->getStyle('E4')->getFont()->setBold(true);
        $activeSheet->setCellValue('F4', '')->getStyle('F4')->getFont()->setBold(true);
        $activeSheet->setCellValue('G4', '')->getStyle('G4')->getFont()->setBold(true);
        $activeSheet->setCellValue('H4', 'Patient Information')->getStyle('H4')->getFont()->setBold(true);
        $activeSheet->setCellValue('I4', '')->getStyle('I4')->getFont()->setBold(true);
        $activeSheet->setCellValue('J4', '')->getStyle('J4')->getFont()->setBold(true);
        $activeSheet->setCellValue('K4', '')->getStyle('K4')->getFont()->setBold(true);
        $activeSheet->setCellValue('L4', '')->getStyle('L4')->getFont()->setBold(true);
        $activeSheet->setCellValue('M4', '')->getStyle('M4')->getFont()->setBold(true);

        $activeSheet->setCellValue('A5', '')->getStyle('A5')->getFont()->setBold(true);
        $activeSheet->setCellValue('B5', '')->getStyle('B5')->getFont()->setBold(true);
        $activeSheet->setCellValue('C5', '')->getStyle('C5')->getFont()->setBold(true);
        $activeSheet->setCellValue('D5', 'Sr.')->getStyle('D5')->getFont()->setBold(true);
        $activeSheet->setCellValue('E5', 'ID')->getStyle('E5')->getFont()->setBold(true);
        $activeSheet->setCellValue('F5', 'Patient')->getStyle('E5')->getFont()->setBold(true);

        $activeSheet->setCellValue('G5', 'Email')->getStyle('G5')->getFont()->setBold(true);
        $activeSheet->setCellValue('H5', 'Scheduled')->getStyle('H5')->getFont()->setBold(true);
        $activeSheet->setCellValue('I5', 'Doctor')->getStyle('I5')->getFont()->setBold(true);
        $activeSheet->setCellValue('J5', 'Type')->getStyle('J5')->getFont()->setBold(true);
        $activeSheet->setCellValue('K5', 'Consultancy Type')->getStyle('K5')->getFont()->setBold(true);
        $activeSheet->setCellValue('L5', 'Status')->getStyle('L5')->getFont()->setBold(true);
        $activeSheet->setCellValue('M5', 'Created By')->getStyle('M5')->getFont()->setBold(true);
        $activeSheet->setCellValue('N5', 'Referred By')->getStyle('N5')->getFont()->setBold(true);

        $counter = 6;
        if (count($reportData)) {
            $grand_total = 0;
            foreach ($reportData as $region) {
                if (count($region['centres'])) {
                    $activeSheet->setCellValue('A'.$counter, $region['name'])->getStyle('A'.$counter)->getFont()->setBold(true);
                    $counter++;
                    foreach ($region['centres'] as $centre) {
                        $activeSheet->setCellValue('A'.$counter, '');
                        $activeSheet->setCellValue('B'.$counter, $centre['name'])->getStyle('B'.$counter)->getFont()->setBold(true);
                        $counter++;

                        if (count($centre['dates'])) {
                            foreach ($centre['dates'] as $data) {
                                if (count($data['appointments'])) {
                                    $activeSheet->setCellValue('A'.$counter, '');
                                    $activeSheet->setCellValue('B'.$counter, '');
                                    $activeSheet->setCellValue('C'.$counter, $data['date'])->getStyle('B'.$counter)->getFont()->setBold(true);
                                    $counter++;

                                    $sr = 1;

                                    foreach ($data['appointments'] as $appointment) {
                                        $activeSheet->setCellValue('A'.$counter, '');
                                        $activeSheet->setCellValue('B'.$counter, '');
                                        $activeSheet->setCellValue('C'.$counter, '');
                                        $activeSheet->setCellValue('D'.$counter, $sr++);
                                        $activeSheet->setCellValue('E'.$counter, $appointment['patient_id']);
                                        $activeSheet->setCellValue('F'.$counter, $appointment['name']);
                                        $activeSheet->setCellValue('G'.$counter, $appointment['email']);
                                        $activeSheet->setCellValue('H'.$counter, $appointment['scheduled_date']);
                                        $activeSheet->setCellValue('I'.$counter, $appointment['doctor_name']);
                                        $activeSheet->setCellValue('J'.$counter, $appointment['appointment_type_name']);
                                        $activeSheet->setCellValue('K'.$counter, $appointment['consultancy_type']);
                                        $activeSheet->setCellValue('L'.$counter, $appointment['appointment_status_name']);
                                        $activeSheet->setCellValue('M'.$counter, $appointment['created_by_name']);
                                        $activeSheet->setCellValue('N'.$counter, $appointment['referred_by_name']);
                                        $counter++;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'PatientByAppointmentStatus-DateWise'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /**
     * Load Appointment Summary by Status Report
     *
     * @return \Illuminate\Http\Response
     */
    public function appointmentSummaryByStatusReport(Request $request)
    {

        if (! Gate::allows('appointment_reports_summary_by_appointment_status')) {
            return abort(401);
        }

        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $filters = [];

        $filters['regions'] = Regions::getAll(Auth::User()->account_id)->getDictionary();
        $filters['doctors'] = Doctors::getAll(Auth::User()->account_id)->getDictionary();
        $filters['cities'] = Cities::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['services'] = Services::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_statuses'] = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_types'] = AppointmentTypes::getAllRecords(Auth::User()->account_id)->getDictionary();
        $filters['users'] = User::getAllRecords(Auth::User()->account_id)->getDictionary();
        $reportData = Appointments::getAppointmentSummaryByStatusReport($request->all(), $filters, Auth::User()->account_id);

        $filters['reportData'] = $reportData;

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.appointments.summary_appointment_status.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.appointments.summary_appointment_status.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $pdf = PDF::loadView('admin.reports.appointments.summary_appointment_status.reportpdf', compact('reportData', 'filters', 'start_date', 'end_date'));
                $pdf->setPaper('A4', 'landscape');

                return $pdf->stream('Employee Appointment Summary Report', 'landscape');
                break;
            case 'excel':
                self::appointmentSummaryByStatusReportExcel($reportData, $filters, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.appointments.summary_appointment_status.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Load Appointments Summary by Service Report in Excel
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return \Illuminate\Http\Response
     */
    private static function appointmentSummaryByStatusReportExcel($reportData, $filters, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');
        $activeSheet->setCellValue('B3', '');

        $activeSheet->setCellValue('A4', 'Region')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Centre')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Service')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'Total Appointments')->getStyle('D4')->getFont()->setBold(true);

        $counter = 5;
        if (count($reportData)) {
            $grand_total = 0;
            foreach ($reportData as $region) {
                if (count($region['centres'])) {
                    $activeSheet->setCellValue('A'.$counter, $region['name'])->getStyle('A'.$counter)->getFont()->setBold(true);
                    $region_appointments = 0;
                    $counter++;
                    foreach ($region['centres'] as $centre) {
                        $activeSheet->setCellValue('A'.$counter, '');
                        $activeSheet->setCellValue('B'.$counter, $centre['name'])->getStyle('B'.$counter)->getFont()->setBold(true);
                        $counter++;
                        $centre_appointments = 0;
                        if (count($centre['appointment_statuses'])) {
                            foreach ($centre['appointment_statuses'] as $appointment_status) {
                                $centre_appointments = $centre_appointments + $appointment_status['total_appointments'];
                                $activeSheet->setCellValue('A'.$counter, '');
                                $activeSheet->setCellValue('C'.$counter, '');
                                $activeSheet->setCellValue('C'.$counter, $appointment_status['name']);
                                $activeSheet->setCellValue('D'.$counter, number_format($appointment_status['total_appointments']));
                                $counter++;
                            }
                            $region_appointments = $region_appointments + $centre_appointments;
                            $activeSheet->setCellValue('A'.$counter, '');
                            $activeSheet->setCellValue('C'.$counter, '');
                            $activeSheet->setCellValue('C'.$counter, 'Total for '.$centre['name'])->getStyle('C'.$counter)->getFont()->setBold(true);
                            $activeSheet->setCellValue('D'.$counter, number_format($centre_appointments))->getStyle('D'.$counter)->getFont()->setBold(true);
                            $counter++;
                        }
                    }
                    $grand_total = $grand_total + $region_appointments;
                    $activeSheet->setCellValue('A'.$counter, '');
                    $activeSheet->setCellValue('C'.$counter, '');
                    $activeSheet->setCellValue('C'.$counter, 'Total for '.$region['name'])->getStyle('C'.$counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('D'.$counter, number_format($region_appointments))->getStyle('D'.$counter)->getFont()->setBold(true);
                    $counter++;
                }
            }

            $activeSheet->setCellValue('A'.$counter, '');
            $activeSheet->setCellValue('C'.$counter, '');
            $activeSheet->setCellValue('C'.$counter, 'Grand Total for All Regions')->getStyle('C'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('D'.$counter, number_format($grand_total))->getStyle('D'.$counter)->getFont()->setBold(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'AppointmentsSummaryByStatus'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * Load Appointment Summary by Service Report
     *
     * @return \Illuminate\Http\Response
     */
    public function appointmentSummaryByServiceReport(Request $request)
    {
        if (! Gate::allows('appointment_reports_summary_by_service')) {
            return abort(401);
        }

        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $filters = [];

        $filters['regions'] = Regions::getAll(Auth::User()->account_id)->getDictionary();
        $filters['doctors'] = Doctors::getAll(Auth::User()->account_id)->getDictionary();
        $filters['cities'] = Cities::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['services'] = Services::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_statuses'] = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_types'] = AppointmentTypes::getAllRecords(Auth::User()->account_id)->getDictionary();
        $filters['users'] = User::getAllRecords(Auth::User()->account_id)->getDictionary();
        $reportData = Appointments::getAppointmentSummaryByServiceReport($request->all(), $filters, Auth::User()->account_id);

        $filters['reportData'] = $reportData;

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.appointments.summary_service.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.appointments.summary_service.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $pdf = PDF::loadView('admin.reports.appointments.summary_service.reportpdf', compact('reportData', 'filters', 'start_date', 'end_date'));
                $pdf->setPaper('A4', 'landscape');

                return $pdf->stream('Employee Appointment Summary Report', 'landscape');
                break;
            case 'excel':
                self::appointmentSummaryByServiceReportExcel($reportData, $filters, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.appointments.summary_service.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Load Appointments Summary by Service Report in Excel
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return \Illuminate\Http\Response
     */
    private static function appointmentSummaryByServiceReportExcel($reportData, $filters, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');
        $activeSheet->setCellValue('B3', '');

        $activeSheet->setCellValue('A4', 'Region')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Centre')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Service')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'Total Appointments')->getStyle('D4')->getFont()->setBold(true);

        $counter = 5;
        if (count($reportData)) {
            $grand_total = 0;
            foreach ($reportData as $region) {
                if (count($region['centres'])) {
                    $activeSheet->setCellValue('A'.$counter, $region['name'])->getStyle('A'.$counter)->getFont()->setBold(true);
                    $region_appointments = 0;
                    $counter++;
                    foreach ($region['centres'] as $centre) {
                        $activeSheet->setCellValue('A'.$counter, '');
                        $activeSheet->setCellValue('B'.$counter, $centre['name'])->getStyle('B'.$counter)->getFont()->setBold(true);
                        $counter++;
                        $centre_appointments = 0;
                        if (count($centre['services'])) {
                            foreach ($centre['services'] as $service) {
                                $centre_appointments = $centre_appointments + $service['total_appointments'];
                                $activeSheet->setCellValue('A'.$counter, '');
                                $activeSheet->setCellValue('C'.$counter, '');
                                $activeSheet->setCellValue('C'.$counter, $service['name']);
                                $activeSheet->setCellValue('D'.$counter, number_format($service['total_appointments']));
                                $counter++;
                            }
                            $region_appointments = $region_appointments + $centre_appointments;
                            $activeSheet->setCellValue('A'.$counter, '');
                            $activeSheet->setCellValue('C'.$counter, '');
                            $activeSheet->setCellValue('C'.$counter, 'Total for '.$centre['name'])->getStyle('C'.$counter)->getFont()->setBold(true);
                            $activeSheet->setCellValue('D'.$counter, number_format($centre_appointments))->getStyle('D'.$counter)->getFont()->setBold(true);
                            $counter++;
                        }
                    }
                    $grand_total = $grand_total + $region_appointments;
                    $activeSheet->setCellValue('A'.$counter, '');
                    $activeSheet->setCellValue('C'.$counter, '');
                    $activeSheet->setCellValue('C'.$counter, 'Total for '.$region['name'])->getStyle('C'.$counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('D'.$counter, number_format($region_appointments))->getStyle('D'.$counter)->getFont()->setBold(true);
                    $counter++;
                }
            }

            $activeSheet->setCellValue('A'.$counter, '');
            $activeSheet->setCellValue('C'.$counter, '');
            $activeSheet->setCellValue('C'.$counter, 'Grand Total for All Regions')->getStyle('C'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('D'.$counter, number_format($grand_total))->getStyle('D'.$counter)->getFont()->setBold(true);
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'AppointmentsSummaryByService'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * Load General Report
     *
     * @return \Illuminate\Http\Response
     */
    private static function generalReport(Request $request)
    {
        if (! Gate::allows('appointment_reports_general_report')) {
            return abort(401);
        }
        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $filters = [];

        $users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $users->prepend('All', '');

        $reportData = Appointments::getGeneralReport($request->all(), Auth::User()->account_id);

        $filters['reportData'] = $reportData;
        $filters['doctors'] = Doctors::getAll(Auth::User()->account_id)->getDictionary();
        $filters['cities'] = Cities::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['services'] = Services::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_statuses'] = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_types'] = AppointmentTypes::getAllRecords(Auth::User()->account_id)->getDictionary();
        $filters['users'] = User::getAllRecords(Auth::User()->account_id)->getDictionary();

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.appointments.generalReport', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.appointments.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.appointments.generalReportPDF', compact('reportData', 'filters', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A2', 'landscape');

                return $pdf->stream('GeneralReport', 'landscape');
                break;
            case 'excel':
                self::AppointmentReportExcel($reportData, $filters, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.appointments.generalReport', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Appointment Reports
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return \Illuminate\Http\Response
     */
    private static function AppointmentReportExcel($reportData, $filters, $start_date, $end_date)
    {
        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');
        $activeSheet->setCellValue('B3', '');

        $activeSheet->setCellValue('A4', 'ID')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Client')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Email')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'Scheduled')->getStyle('D4')->getFont()->setBold(true);
        $activeSheet->setCellValue('E4', 'Doctor')->getStyle('E4')->getFont()->setBold(true);
        $activeSheet->setCellValue('F4', 'City')->getStyle('F4')->getFont()->setBold(true);
        $activeSheet->setCellValue('G4', 'Centre')->getStyle('G4')->getFont()->setBold(true);
        $activeSheet->setCellValue('H4', 'Treatment/Consultancy')->getStyle('H4')->getFont()->setBold(true);
        $activeSheet->setCellValue('I4', 'Status')->getStyle('I4')->getFont()->setBold(true);
        $activeSheet->setCellValue('J4', 'Type')->getStyle('J4')->getFont()->setBold(true);
        $activeSheet->setCellValue('K4', 'Consultancy Type')->getStyle('K4')->getFont()->setBold(true);
        $activeSheet->setCellValue('L4', 'Created At')->getStyle('L4')->getFont()->setBold(true);
        $activeSheet->setCellValue('M4', 'Created By')->getStyle('M4')->getFont()->setBold(true);
        $activeSheet->setCellValue('N4', 'Updated By')->getStyle('N4')->getFont()->setBold(true);
        $activeSheet->setCellValue('O4', 'Rescheduled By')->getStyle('O4')->getFont()->setBold(true);
        $activeSheet->setCellValue('P4', 'Referred By')->getStyle('P4')->getFont()->setBold(true);

        $counter = 5;

        if (count($reportData)) {
            foreach ($reportData as $reportRow) {
                if ($reportRow->consultancy_type == 'in_person') {
                    $consultancy_type = 'In Person';
                } elseif ($reportRow->consultancy_type == 'virtual') {
                    $consultancy_type = 'Virtual';
                } else {
                    $consultancy_type = '';
                }
                $activeSheet->setCellValue('A'.$counter, $reportRow->patient_id);
                $activeSheet->setCellValue('B'.$counter, $reportRow->patient->name);
                $activeSheet->setCellValue('C'.$counter, $reportRow->patient->email);
                $activeSheet->setCellValue('D'.$counter, ($reportRow->scheduled_date) ? \Carbon\Carbon::parse($reportRow->scheduled_date, null)->format('M j, Y').' at '.\Carbon\Carbon::parse($reportRow->scheduled_time, null)->format('h:i A') : '-');
                $activeSheet->setCellValue('E'.$counter, (array_key_exists($reportRow->doctor_id, $filters['doctors'])) ? $filters['doctors'][$reportRow->doctor_id]->name : '');
                $activeSheet->setCellValue('F'.$counter, (array_key_exists($reportRow->city_id, $filters['cities'])) ? $filters['cities'][$reportRow->city_id]->name : '');
                $activeSheet->setCellValue('G'.$counter, (array_key_exists($reportRow->location_id, $filters['locations'])) ? $filters['locations'][$reportRow->location_id]->name : '');
                $activeSheet->setCellValue('H'.$counter, (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->name : '');
                $activeSheet->setCellValue('I'.$counter, (array_key_exists($reportRow->base_appointment_status_id, $filters['appointment_statuses'])) ? $filters['appointment_statuses'][$reportRow->base_appointment_status_id]->name : '');
                $activeSheet->setCellValue('J'.$counter, (array_key_exists($reportRow->appointment_type_id, $filters['appointment_types'])) ? $filters['appointment_types'][$reportRow->appointment_type_id]->name : '');
                $activeSheet->setCellValue('K'.$counter, $consultancy_type);
                $activeSheet->setCellValue('L'.$counter, \Carbon\Carbon::parse($reportRow->created_at)->format('M j, Y H:i A'));
                $activeSheet->setCellValue('M'.$counter, (array_key_exists($reportRow->created_by, $filters['users'])) ? $filters['users'][$reportRow->created_by]->name : '');
                $activeSheet->setCellValue('N'.$counter, (array_key_exists($reportRow->converted_by, $filters['users'])) ? $filters['users'][$reportRow->converted_by]->name : '');
                $activeSheet->setCellValue('O'.$counter, (array_key_exists($reportRow->updated_by, $filters['users'])) ? $filters['users'][$reportRow->updated_by]->name : '');
                $activeSheet->setCellValue('P'.$counter, (array_key_exists($reportRow->referred_by, $filters['users'])) ? $filters['users'][$reportRow->referred_by]->name : '');
                $counter++;
            }
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'General Report'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /*
     * General report summary
     *
     */
    public function generalReportSummary(Request $request)
    {
        if (! Gate::allows('appointment_reports_general_summary_report')) {
            return abort(401);
        }
        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $filters = [];

        $users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $users->prepend('All', '');

        $reportData = Appointments::getGeneralReport($request->all(), Auth::User()->account_id);

        $slugs = AppointmentTypes::get()->pluck('id')->toArray();

        $reportData2 = [];

        foreach ($slugs as $sing_slug) {
            $reportData2[$sing_slug] = [];
        }
        $services = [];

        foreach ($reportData as $reportsingle) {
            if (! in_array($reportsingle->service_id, $services)) {
                $reportData2[$reportsingle->appointment_type_id][$reportsingle->service_id] = [
                    'name' => $reportsingle->service->name,
                    'count' => 0,
                ];
                $services[] = $reportsingle->service_id;
            }
            $reportData2[$reportsingle->appointment_type_id][$reportsingle->service_id]['count'] += 1;
        }

        $filters['reportData'] = $reportData;
        $filters['doctors'] = Doctors::getAll(Auth::User()->account_id)->getDictionary();
        $filters['cities'] = Cities::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['services'] = Services::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_statuses'] = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_types'] = AppointmentTypes::getAllRecords(Auth::User()->account_id)->getDictionary();
        $filters['users'] = User::getAllRecords(Auth::User()->account_id)->getDictionary();

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.appointments.generalreportsummary.generalReport', compact('reportData', 'filters', 'start_date', 'end_date', 'reportData2'));
                break;
            case 'pdf':
                $content = view('admin.reports.appointments.generalreportsummary.generalReportPDF', compact('reportData', 'filters', 'start_date', 'end_date', 'reportData2'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A4', 'landscape');

                return $pdf->stream('GeneralReport', 'landscape');
                break;
            case 'print':
                return view('admin.reports.appointments.generalreportsummary.generalReportPrint', compact('reportData', 'filters', 'start_date', 'end_date', 'reportData2'));
            case 'excel':
                self::GeneralReportsummaryExcel($reportData, $filters, $start_date, $end_date, $reportData2);
                break;
            default:
                return view('admin.reports.appointments.generalreportsummary.generalReport', compact('reportData', 'filters', 'start_date', 'end_date', 'reportData2'));
                break;
        }
    }

    /*
     * General Report Summary
     */
    public function GeneralReportsummaryExcel($reportData, $filters, $start_date, $end_date, $reportData2)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');
        $activeSheet->setCellValue('B3', '');

        $counter = 4;
        $grand_total = 0;
        foreach ($reportData2 as $key => $reporttype) {

            if ($key === config('constants.appointment_type_consultancy')) {

                $activeSheet->setCellValue('A'.$counter, 'Consultancy Name')->getStyle('A'.$counter)->getFont()->setBold(true);

            } else {

                $activeSheet->setCellValue('A'.$counter, 'Treatment Name')->getStyle('A'.$counter)->getFont()->setBold(true);
            }

            $activeSheet->setCellValue('B'.$counter, 'Count')->getStyle('B'.$counter)->getFont()->setBold(true);

            $counter++;

            $total = 0;
            foreach ($reporttype as $reportcount) {
                $activeSheet->setCellValue('A'.$counter, $reportcount['name']);
                $activeSheet->setCellValue('B'.$counter, number_format($reportcount['count']));
                $total += $reportcount['count'];
                $grand_total += $reportcount['count'];
                $counter++;
            }
            $activeSheet->setCellValue('A'.$counter, 'Total')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B'.$counter, number_format($total))->getStyle('B'.$counter)->getFont()->setBold(true);
            $counter++;
        }

        $activeSheet->setCellValue('A'.$counter, 'Total')->getStyle('A'.$counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('B'.$counter, number_format($grand_total))->getStyle('B'.$counter)->getFont()->setBold(true);
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'General Report Summary'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * Load Staff (Referred By) Appointment Schedule Report
     *
     * @return \Illuminate\Http\Response
     */
    public function staffReferredByAppointmentScheduleReport(Request $request)
    {

        if (! Gate::allows('appointment_reports_referred_by_staff_appointment')) {
            return abort(401);
        }

        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $filters = [];

        $users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $users->prepend('All', '');

        $filters['doctors'] = Doctors::getAll(Auth::User()->account_id)->getDictionary();
        $filters['cities'] = Cities::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['services'] = Services::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_statuses'] = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_types'] = AppointmentTypes::getAllRecords(Auth::User()->account_id)->getDictionary();
        $filters['users'] = User::getAllRecords(Auth::User()->account_id)->getDictionary();

        $reportData = Appointments::getStaffReferredByAppointmentScheduleReport($request->all(), $filters, Auth::User()->account_id);

        foreach ($reportData as $key1 => $report_Data) {
            foreach ($report_Data['records'] as $key2 => $report_row) {
                $Salestotal = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->where('appointment_id', '=', $report_row->id)->first();

                if ($Salestotal) {
                    $reportData[$key1]['records'][$key2]['Salestotal'] = $Salestotal->tax_including_price;
                } else {
                    $reportData[$key1]['records'][$key2]['Salestotal'] = 0;
                }
            }
        }
        $filters['reportData'] = $reportData;

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.staffreferredbyappointments.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.staffreferredbyappointments.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.staffreferredbyappointments.reportpdf', compact('reportData', 'filters', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A3', 'landscape');

                return $pdf->stream('Staff Referred By Appointment Schedule', 'landscape');
                break;
            case 'excel':
                self::StaffReferredByAppointmentScheduleReportExcel($reportData, $filters, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.staffreferredbyappointments.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Staff Appointment Schedule Report (Summary) Excel
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $filters
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return \Illuminate\Http\Response
     */
    private static function StaffReferredByAppointmentScheduleReportExcel($reportData, $filters, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A4', 'ID')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Created By')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Created At')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'Client Name')->getStyle('D4')->getFont()->setBold(true);
        $activeSheet->setCellValue('E4', 'Doctor')->getStyle('E4')->getFont()->setBold(true);
        $activeSheet->setCellValue('F4', 'Service')->getStyle('F4')->getFont()->setBold(true);
        $activeSheet->setCellValue('G4', 'Email')->getStyle('G4')->getFont()->setBold(true);
        $activeSheet->setCellValue('H4', 'Scheduled')->getStyle('H4')->getFont()->setBold(true);
        $activeSheet->setCellValue('I4', 'Service Price')->getStyle('I4')->getFont()->setBold(true);
        $activeSheet->setCellValue('J4', 'Invoiced')->getStyle('J4')->getFont()->setBold(true);
        $activeSheet->setCellValue('K4', 'City')->getStyle('K4')->getFont()->setBold(true);
        $activeSheet->setCellValue('L4', 'Centre')->getStyle('L4')->getFont()->setBold(true);
        $activeSheet->setCellValue('M4', 'Status')->getStyle('M4')->getFont()->setBold(true);
        $activeSheet->setCellValue('N4', 'Type')->getStyle('N4')->getFont()->setBold(true);

        $counter = 4;
        if (count($reportData)) {
            $count = 0;
            $salesgrandtotal = 0;
            $servicegrandtotal = 0;
            $grandcount = 0;
            foreach ($reportData as $reportpackagedata) {
                $activeSheet->setCellValue('A'.$counter, $reportpackagedata['name'])->getStyle('A'.$counter)->getFont()->setBold(true);
                $counter++;
                $count = 0;
                $salestotal = 0;
                $servicetotal = 0;
                foreach ($reportpackagedata['records'] as $reportRow) {

                    $serviceprice = (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->price : '';
                    $servicetotal += $serviceprice;
                    $salestotal += $reportRow->Salestotal;

                    $activeSheet->setCellValue('A'.$counter, $reportRow->patient_id);
                    $activeSheet->setCellValue('B'.$counter, (array_key_exists($reportRow->created_by, $filters['users'])) ? $filters['users'][$reportRow->created_by]->name : '');
                    $activeSheet->setCellValue('C'.$counter, \Carbon\Carbon::parse($reportRow->created_at)->format('M j, Y H:i A'))->getStyle('B'.$counter);
                    $activeSheet->setCellValue('D'.$counter, $reportRow->patient->name)->getStyle('C'.$counter)->getFont();
                    $activeSheet->setCellValue('E'.$counter, (array_key_exists($reportRow->doctor_id, $filters['doctors'])) ? $filters['doctors'][$reportRow->doctor_id]->name : '');
                    $activeSheet->setCellValue('F'.$counter, (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->name : '');
                    $activeSheet->setCellValue('G'.$counter, $reportRow->patient->email);
                    $activeSheet->setCellValue('H'.$counter, ($reportRow->scheduled_date) ? \Carbon\Carbon::parse($reportRow->scheduled_date, null)->format('M j, Y').' at '.\Carbon\Carbon::parse($reportRow->scheduled_time, null)->format('h:i A') : '-');
                    $activeSheet->setCellValue('I'.$counter, number_format($serviceprice, 2));
                    $activeSheet->setCellValue('J'.$counter, number_format($reportRow->Salestotal, 2));
                    $activeSheet->setCellValue('K'.$counter, (array_key_exists($reportRow->city_id, $filters['cities'])) ? $filters['cities'][$reportRow->city_id]->name : '');
                    $activeSheet->setCellValue('L'.$counter, (array_key_exists($reportRow->location_id, $filters['locations'])) ? $filters['locations'][$reportRow->location_id]->name : '');
                    $activeSheet->setCellValue('M'.$counter, (array_key_exists($reportRow->base_appointment_status_id, $filters['appointment_statuses'])) ? $filters['appointment_statuses'][$reportRow->base_appointment_status_id]->name : '');
                    $activeSheet->setCellValue('N'.$counter, (array_key_exists($reportRow->appointment_type_id, $filters['appointment_types'])) ? $filters['appointment_types'][$reportRow->appointment_type_id]->name : '');
                    $counter++;
                    $grandcount++;
                    $count++;
                }
                $servicegrandtotal += $servicetotal;
                $salesgrandtotal += $salestotal;

                $activeSheet->setCellValue('A'.$counter, '');
                $counter++;

                $activeSheet->setCellValue('A'.$counter, $reportpackagedata['name'])->getStyle('A'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B'.$counter, 'Total');
                $activeSheet->setCellValue('C'.$counter, $count)->getStyle('B'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('I'.$counter, number_format($servicetotal, 2))->getStyle('I'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('J'.$counter, number_format($salestotal, 2))->getStyle('J'.$counter)->getFont()->setBold(true);
                $counter++;
            }
            $activeSheet->setCellValue('A'.$counter, '');
            $counter++;

            $activeSheet->setCellValue('B'.$counter, 'Grand Total');
            $activeSheet->setCellValue('C'.$counter, $grandcount)->getStyle('B'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('I'.$counter, number_format($servicegrandtotal, 2))->getStyle('I'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('J'.$counter, number_format($salesgrandtotal, 2))->getStyle('J'.$counter)->getFont()->setBold(true);
            $counter++;

        }
        $activeSheet->setCellValue('A'.$counter, '');
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'StaffReferredByEmployeeReport'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * Load Staff Appointment Schedule Report
     *
     * @return \Illuminate\Http\Response
     */
    public function staffAppointmentScheduleReport(Request $request)
    {

        if (! Gate::allows('appointment_reports_staff_appointment')) {
            return abort(401);
        }

        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $filters = [];

        $users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $users->prepend('All', '');

        $filters['doctors'] = Doctors::getAll(Auth::User()->account_id)->getDictionary();
        $filters['cities'] = Cities::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['services'] = Services::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_statuses'] = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_types'] = AppointmentTypes::getAllRecords(Auth::User()->account_id)->getDictionary();
        $filters['users'] = User::getAllRecords(Auth::User()->account_id)->getDictionary();

        $reportData = Appointments::getStaffAppointmentScheduleReport($request->all(), $filters);

        foreach ($reportData as $key1 => $report_Data) {
            foreach ($report_Data['records'] as $key2 => $report_row) {
                $Salestotal = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->where('appointment_id', '=', $report_row->id)->first();
                if ($Salestotal) {
                    $reportData[$key1]['records'][$key2]['Salestotal'] = $Salestotal->tax_including_price;
                } else {
                    $reportData[$key1]['records'][$key2]['Salestotal'] = 0;
                }
            }
        }
        $filters['reportData'] = $reportData;

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.staffappointments.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.staffappointments.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.staffappointments.reportpdf', compact('reportData', 'filters', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A2', 'landscape');

                return $pdf->stream('Staff Appointment Schedule', 'landscape');
                break;
            case 'excel':
                self::StaffAppointmentScheduleReportExcel($reportData, $filters, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.staffappointments.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Load Employee Appointment Summary Report
     *
     * @return \Illuminate\Http\Response
     */
    public function EmployeeAppointmentSummaryReport(Request $request)
    {

        if (! Gate::allows('appointment_reports_empolyee_summary')) {
            return abort(401);
        }

        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $filters = [];

        $users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $users->prepend('All', '');

        $filters['doctors'] = Doctors::getAll(Auth::User()->account_id)->getDictionary();
        $filters['cities'] = Cities::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['services'] = Services::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_statuses'] = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_types'] = AppointmentTypes::getAllRecords(Auth::User()->account_id)->getDictionary();
        $filters['users'] = User::getAllRecords(Auth::User()->account_id)->getDictionary();
        $reportData = Appointments::getEmployeeAppointmentSummaryReport($request->all(), $filters, Auth::User()->account_id);

        foreach ($reportData as $key1 => $report_Data) {
            foreach ($report_Data['records'] as $key2 => $report_row) {
                $Salestotal = Invoices::where('appointment_id', '=', $report_row->id)->first();
                if ($Salestotal) {
                    $reportData[$key1]['records'][$key2]['Salestotal'] = $Salestotal->total_price;
                } else {
                    $reportData[$key1]['records'][$key2]['Salestotal'] = 0;
                }
            }
        }
        $filters['reportData'] = $reportData;

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.employeesummaryreports.reports', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.employeesummaryreports.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.employeesummaryreports.reportspdf', compact('reportData', 'filters', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A4', 'landscape');

                return $pdf->stream('Employee Appointment Summary Report', 'landscape');
                break;
            case 'excel':
                self::employeeAppointmentSummary($reportData, $filters, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.employeesummaryreports.reports', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Employee Appointment Summary Report
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $filters
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return \Illuminate\Http\Response
     */
    private static function employeeAppointmentSummary($reportData, $filters, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', 'Created By')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'Total Appointments')->getStyle('B3')->getFont()->setBold(true);

        $activeSheet->setCellValue('A4', '');
        $activeSheet->setCellValue('B4', '');

        $counter = 5;
        if (count($reportData)) {
            $count = 0;
            foreach ($reportData as $reportpackagedata) {
                foreach ($reportpackagedata['records'] as $reportRow) {
                    $created_by = (array_key_exists($reportRow->created_by, $filters['users'])) ? $filters['users'][$reportRow->created_by]->name : '';
                    $count++;
                }
                $activeSheet->setCellValue('A'.$counter, $created_by);
                $activeSheet->setCellValue('B'.$counter, $count);
                $counter++;
                $count = 0;
            }
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'EmployeeAppointmentSummaryReport'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * Staff Appointment Schedule Report (Summary) Excel
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $filters
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return \Illuminate\Http\Response
     */
    private static function StaffAppointmentScheduleReportExcel($reportData, $filters, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', 'ID')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'Created By')->getStyle('B3')->getFont()->setBold(true);
        $activeSheet->setCellValue('C3', 'Updated By')->getStyle('C3')->getFont()->setBold(true);
        $activeSheet->setCellValue('D3', 'Rescheduled By')->getStyle('D3')->getFont()->setBold(true);
        $activeSheet->setCellValue('E3', 'Created At')->getStyle('E3')->getFont()->setBold(true);
        $activeSheet->setCellValue('F3', 'Client Name')->getStyle('F3')->getFont()->setBold(true);
        $activeSheet->setCellValue('G3', 'Doctor')->getStyle('G3')->getFont()->setBold(true);
        $activeSheet->setCellValue('H3', 'Service')->getStyle('H3')->getFont()->setBold(true);
        $activeSheet->setCellValue('I3', 'Email')->getStyle('I3')->getFont()->setBold(true);
        $activeSheet->setCellValue('J3', 'Scheduled')->getStyle('J3')->getFont()->setBold(true);
        $activeSheet->setCellValue('K3', 'Service Price')->getStyle('K3')->getFont()->setBold(true);
        $activeSheet->setCellValue('L3', 'Invoiced')->getStyle('L3')->getFont()->setBold(true);
        $activeSheet->setCellValue('M3', 'City')->getStyle('M3')->getFont()->setBold(true);
        $activeSheet->setCellValue('N3', 'Centre')->getStyle('N3')->getFont()->setBold(true);
        $activeSheet->setCellValue('O3', 'Status')->getStyle('O3')->getFont()->setBold(true);
        $activeSheet->setCellValue('P3', 'Type')->getStyle('P3')->getFont()->setBold(true);
        $activeSheet->setCellValue('Q3', 'Consultancy Type')->getStyle('Q3')->getFont()->setBold(true);

        $counter = 4;
        if (count($reportData)) {
            $count = 0;
            $salesgrandtotal = 0;
            $servicegrandtotal = 0;
            $grandcount = 0;
            foreach ($reportData as $reportpackagedata) {
                $activeSheet->setCellValue('A'.$counter, $reportpackagedata['name'])->getStyle('A'.$counter)->getFont()->setBold(true);
                $counter++;
                $count = 0;
                $salestotal = 0;
                $servicetotal = 0;
                foreach ($reportpackagedata['records'] as $reportRow) {

                    if ($reportRow->consultancy_type == 'in_person') {
                        $consultancy_type = 'In Person';
                    } elseif ($reportRow->consultancy_type == 'virtual') {
                        $consultancy_type = 'Virtual';
                    } else {
                        $consultancy_type = '';
                    }

                    $serviceprice = (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->price : '';
                    $servicetotal += $serviceprice;
                    $salestotal += $reportRow->Salestotal;

                    $activeSheet->setCellValue('A'.$counter, $reportRow->id);
                    $activeSheet->setCellValue('B'.$counter, (array_key_exists($reportRow->created_by, $filters['users'])) ? $filters['users'][$reportRow->created_by]->name : '');
                    $activeSheet->setCellValue('C'.$counter, (array_key_exists($reportRow->converted_by, $filters['users'])) ? $filters['users'][$reportRow->converted_by]->name : '');
                    $activeSheet->setCellValue('D'.$counter, (array_key_exists($reportRow->updated_by, $filters['users'])) ? $filters['users'][$reportRow->updated_by]->name : '');
                    $activeSheet->setCellValue('E'.$counter, \Carbon\Carbon::parse($reportRow->created_at)->format('M j, Y H:i A'));
                    $activeSheet->setCellValue('F'.$counter, $reportRow->patient->name);
                    $activeSheet->setCellValue('G'.$counter, (array_key_exists($reportRow->doctor_id, $filters['doctors'])) ? $filters['doctors'][$reportRow->doctor_id]->name : '');
                    $activeSheet->setCellValue('H'.$counter, (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->name : '');
                    $activeSheet->setCellValue('I'.$counter, $reportRow->patient->email);
                    $activeSheet->setCellValue('J'.$counter, ($reportRow->scheduled_date) ? \Carbon\Carbon::parse($reportRow->scheduled_date, null)->format('M j, Y').' at '.\Carbon\Carbon::parse($reportRow->scheduled_time, null)->format('h:i A') : '-');
                    $activeSheet->setCellValue('K'.$counter, number_format($serviceprice, 2));
                    $activeSheet->setCellValue('L'.$counter, number_format($reportRow->Salestotal, 2));
                    $activeSheet->setCellValue('M'.$counter, (array_key_exists($reportRow->city_id, $filters['cities'])) ? $filters['cities'][$reportRow->city_id]->name : '');
                    $activeSheet->setCellValue('N'.$counter, (array_key_exists($reportRow->location_id, $filters['locations'])) ? $filters['locations'][$reportRow->location_id]->name : '');
                    $activeSheet->setCellValue('O'.$counter, (array_key_exists($reportRow->base_appointment_status_id, $filters['appointment_statuses'])) ? $filters['appointment_statuses'][$reportRow->base_appointment_status_id]->name : '');
                    $activeSheet->setCellValue('P'.$counter, (array_key_exists($reportRow->appointment_type_id, $filters['appointment_types'])) ? $filters['appointment_types'][$reportRow->appointment_type_id]->name : '');
                    $activeSheet->setCellValue('Q'.$counter, $consultancy_type);
                    $counter++;
                    $grandcount++;
                    $count++;
                }
                $servicegrandtotal += $servicetotal;
                $salesgrandtotal += $salestotal;

                $activeSheet->setCellValue('A'.$counter, '');
                $activeSheet->setCellValue('B'.$counter, '');
                $counter++;

                $activeSheet->setCellValue('A'.$counter, $reportpackagedata['name'])->getStyle('A'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B'.$counter, '');
                $activeSheet->setCellValue('C'.$counter, 'Total');
                $activeSheet->setCellValue('D'.$counter, $count)->getStyle('D'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('K'.$counter, number_format($servicetotal, 2))->getStyle('K'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('L'.$counter, number_format($salestotal, 2))->getStyle('L'.$counter)->getFont()->setBold(true);
                $counter++;
            }
            $activeSheet->setCellValue('A'.$counter, '');
            $activeSheet->setCellValue('B'.$counter, '');
            $counter++;

            $activeSheet->setCellValue('A'.$counter, '');
            $activeSheet->setCellValue('B'.$counter, '');
            $activeSheet->setCellValue('C'.$counter, 'Grand Total');
            $activeSheet->setCellValue('D'.$counter, $grandcount)->getStyle('D'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('K'.$counter, number_format($servicegrandtotal, 2))->getStyle('K'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('L'.$counter, number_format($salesgrandtotal, 2))->getStyle('L'.$counter)->getFont()->setBold(true);
            $counter++;
        }
        $activeSheet->setCellValue('A'.$counter, '');
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'DailyEmployeeStatsSummary'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /**
     * Compliance Report
     *
     * @return \Illuminate\Http\Response
     */
    public function complianceReport(Request $request)
    {

        if (! Gate::allows('appointment_reports_compliance_reports')) {
            abort(404);
        }

        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $reportData = Appointments::complianceReport($request->all(), Auth::user()->account_id);

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.compliancereport.report', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.compliancereport.reportprint', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.compliancereport.reportpdf', compact('reportData', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A2', 'landscape');

                return $pdf->stream('ComplianceReport', 'landscape');
                break;
            case 'excel':
                return self::complianceReportExcel($reportData, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.compliancereport.report', compact('reportData', 'start_date', 'end_date'));
                break;
        }

    }

    /**
     * Compliance Report
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return \Illuminate\Http\Response
     */
    private static function complianceReportExcel($reportData, $start_date, $end_date)
    {
        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');
        $activeSheet->setCellValue('B3', '');

        $activeSheet->setCellValue('A4', 'ID')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Client')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Email')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'Scheduled')->getStyle('D4')->getFont()->setBold(true);
        $activeSheet->setCellValue('E4', 'Doctor')->getStyle('E4')->getFont()->setBold(true);
        $activeSheet->setCellValue('F4', 'City')->getStyle('F4')->getFont()->setBold(true);
        $activeSheet->setCellValue('G4', 'Centre')->getStyle('G4')->getFont()->setBold(true);
        $activeSheet->setCellValue('H4', 'Treatment/Consultancy')->getStyle('H4')->getFont()->setBold(true);
        $activeSheet->setCellValue('I4', 'Status')->getStyle('I4')->getFont()->setBold(true);
        $activeSheet->setCellValue('J4', 'Type')->getStyle('J4')->getFont()->setBold(true);
        $activeSheet->setCellValue('K4', 'consultancy Type')->getStyle('K4')->getFont()->setBold(true);
        $activeSheet->setCellValue('L4', 'Created At')->getStyle('L4')->getFont()->setBold(true);
        $activeSheet->setCellValue('M4', 'Created By')->getStyle('M4')->getFont()->setBold(true);
        $activeSheet->setCellValue('N4', 'Updated By')->getStyle('N4')->getFont()->setBold(true);
        $activeSheet->setCellValue('O4', 'Rescheduled By')->getStyle('O4')->getFont()->setBold(true);
        $activeSheet->setCellValue('P4', 'Referred By')->getStyle('P4')->getFont()->setBold(true);
        $activeSheet->setCellValue('Q4', 'Medical History Form')->getStyle('Q4')->getFont()->setBold(true);
        $activeSheet->setCellValue('R4', 'Images Before Service')->getStyle('R4')->getFont()->setBold(true);
        $activeSheet->setCellValue('S4', 'Images After Service')->getStyle('S4')->getFont()->setBold(true);
        $activeSheet->setCellValue('T4', 'Measurement Before Service')->getStyle('T4')->getFont()->setBold(true);
        $activeSheet->setCellValue('U4', 'Measurement After Service')->getStyle('U4')->getFont()->setBold(true);
        $activeSheet->setCellValue('V4', 'Invoice')->getStyle('V4')->getFont()->setBold(true);

        $counter = 5;

        if (count($reportData)) {
            foreach ($reportData as $reportRow) {

                $activeSheet->setCellValue('A'.$counter, $reportRow['id']);
                $activeSheet->setCellValue('B'.$counter, $reportRow['client']);
                $activeSheet->setCellValue('C'.$counter, $reportRow['email']);
                $activeSheet->setCellValue('D'.$counter, ($reportRow['scheduled_date']) ? \Carbon\Carbon::parse($reportRow['scheduled_date'], null)->format('M j, Y').' at '.\Carbon\Carbon::parse($reportRow['scheduled_time'], null)->format('h:i A') : '-');
                $activeSheet->setCellValue('E'.$counter, $reportRow['doctor']);
                $activeSheet->setCellValue('F'.$counter, $reportRow['city']);
                $activeSheet->setCellValue('G'.$counter, $reportRow['centre']);
                $activeSheet->setCellValue('H'.$counter, $reportRow['service']);
                $activeSheet->setCellValue('I'.$counter, $reportRow['status']);
                $activeSheet->setCellValue('J'.$counter, $reportRow['type']);
                $activeSheet->setCellValue('K'.$counter, $reportRow['consultancy_type']);
                $activeSheet->setCellValue('L'.$counter, \Carbon\Carbon::parse($reportRow['created_at'])->format('M j, Y H:i A'));
                $activeSheet->setCellValue('M'.$counter, $reportRow['created_by']);
                $activeSheet->setCellValue('N'.$counter, $reportRow['converted_by']);
                $activeSheet->setCellValue('O'.$counter, $reportRow['updated_by']);
                $activeSheet->setCellValue('P'.$counter, $reportRow['referred_by']);
                $activeSheet->setCellValue('Q'.$counter, array_key_exists('medical_form', $reportRow) ? $reportRow['medical_form'] : 'N/A');
                $activeSheet->setCellValue('R'.$counter, array_key_exists('images_before', $reportRow) ? $reportRow['images_before'] : 'N/A');
                $activeSheet->setCellValue('S'.$counter, array_key_exists('images_after', $reportRow) ? $reportRow['images_after'] : 'N/A');
                $activeSheet->setCellValue('T'.$counter, array_key_exists('measurement_before', $reportRow) ? $reportRow['measurement_before'] : 'N/A');
                $activeSheet->setCellValue('U'.$counter, array_key_exists('measurement_after', $reportRow) ? $reportRow['measurement_after'] : 'N/A');
                $activeSheet->setCellValue('V'.$counter, $reportRow['invoice']);
                $counter++;
            }
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'ComplianceReport'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * Appointment rescheduled Count
     *
     * @return \Illuminate\Http\Response
     */
    private static function rescheduledcountreport(Request $request)
    {
        if (! Gate::allows('appointment_reports_rescheduled_count_report')) {
            return abort(401);
        }
        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $filters = [];

        $users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $users->prepend('All', '');

        $reportData = Appointments::rescheduledcount($request->all(), Auth::User()->account_id);

        $filters['reportData'] = $reportData;
        $filters['doctors'] = Doctors::getAll(Auth::User()->account_id)->getDictionary();
        $filters['cities'] = Cities::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['services'] = Services::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_statuses'] = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_types'] = AppointmentTypes::getAllRecords(Auth::User()->account_id)->getDictionary();
        $filters['users'] = User::getAllRecords(Auth::User()->account_id)->getDictionary();

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.appointments.rescheduledcountreport.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.appointments.rescheduledcountreport.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.appointments.rescheduledcountreport.reportpdf', compact('reportData', 'filters', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A2', 'landscape');

                return $pdf->stream('GeneralReport', 'landscape');
                break;
            case 'excel':
                self::RescheduledcountreportExcel($reportData, $filters, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.appointments.rescheduledcountreport.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Appointment Rescheduled count report excel
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return \Illuminate\Http\Response
     */
    private static function RescheduledcountreportExcel($reportData, $filters, $start_date, $end_date)
    {
        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');
        $activeSheet->setCellValue('B3', '');

        $activeSheet->setCellValue('A4', 'ID')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Client')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Scheduled')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'First Scheduled')->getStyle('D4')->getFont()->setBold(true);
        $activeSheet->setCellValue('E4', 'Doctor')->getStyle('E4')->getFont()->setBold(true);
        $activeSheet->setCellValue('F4', 'City')->getStyle('F4')->getFont()->setBold(true);
        $activeSheet->setCellValue('G4', 'Centre')->getStyle('G4')->getFont()->setBold(true);
        $activeSheet->setCellValue('H4', 'Treatment/Consultancy')->getStyle('H4')->getFont()->setBold(true);
        $activeSheet->setCellValue('I4', 'Status')->getStyle('I4')->getFont()->setBold(true);
        $activeSheet->setCellValue('J4', 'Type')->getStyle('J4')->getFont()->setBold(true);
        $activeSheet->setCellValue('K4', 'Consultancy Type')->getStyle('K4')->getFont()->setBold(true);
        $activeSheet->setCellValue('L4', 'Scheduled Count')->getStyle('L4')->getFont()->setBold(true);
        $activeSheet->setCellValue('M4', 'Created At')->getStyle('M4')->getFont()->setBold(true);
        $activeSheet->setCellValue('N4', 'Created By')->getStyle('N4')->getFont()->setBold(true);
        $activeSheet->setCellValue('O4', 'Updated By')->getStyle('O4')->getFont()->setBold(true);
        $activeSheet->setCellValue('P4', 'Rescheduled By')->getStyle('P4')->getFont()->setBold(true);
        $activeSheet->setCellValue('Q4', 'Referred By')->getStyle('Q4')->getFont()->setBold(true);

        $counter = 5;

        if (count($reportData)) {
            foreach ($reportData as $reportRow) {

                if ($reportRow->consultancy_type == 'in_person') {
                    $consultancy_type = 'In Person';
                } elseif ($reportRow->consultancy_type == 'virtual') {
                    $consultancy_type = 'Virtual';
                } else {
                    $consultancy_type = '';
                }

                $activeSheet->setCellValue('A'.$counter, $reportRow->patient_id);
                $activeSheet->setCellValue('B'.$counter, $reportRow->patient->name);
                $activeSheet->setCellValue('C'.$counter, ($reportRow->scheduled_date) ? \Carbon\Carbon::parse($reportRow->scheduled_date, null)->format('M j, Y').' at '.\Carbon\Carbon::parse($reportRow->scheduled_time, null)->format('h:i A') : '-');
                $activeSheet->setCellValue('D'.$counter, ($reportRow->first_scheduled_date) ? \Carbon\Carbon::parse($reportRow->first_scheduled_date, null)->format('M j, Y').' at '.\Carbon\Carbon::parse($reportRow->first_scheduled_time, null)->format('h:i A') : '-');
                $activeSheet->setCellValue('E'.$counter, (array_key_exists($reportRow->doctor_id, $filters['doctors'])) ? $filters['doctors'][$reportRow->doctor_id]->name : '');
                $activeSheet->setCellValue('F'.$counter, (array_key_exists($reportRow->city_id, $filters['cities'])) ? $filters['cities'][$reportRow->city_id]->name : '');
                $activeSheet->setCellValue('G'.$counter, (array_key_exists($reportRow->location_id, $filters['locations'])) ? $filters['locations'][$reportRow->location_id]->name : '');
                $activeSheet->setCellValue('H'.$counter, (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->name : '');
                $activeSheet->setCellValue('I'.$counter, (array_key_exists($reportRow->base_appointment_status_id, $filters['appointment_statuses'])) ? $filters['appointment_statuses'][$reportRow->base_appointment_status_id]->name : '');
                $activeSheet->setCellValue('J'.$counter, (array_key_exists($reportRow->appointment_type_id, $filters['appointment_types'])) ? $filters['appointment_types'][$reportRow->appointment_type_id]->name : '');
                $activeSheet->setCellValue('K'.$counter, $consultancy_type);
                $activeSheet->setCellValue('L'.$counter, $reportRow->scheduled_at_count);
                $activeSheet->setCellValue('M'.$counter, \Carbon\Carbon::parse($reportRow->created_at)->format('M j, Y H:i A'));
                $activeSheet->setCellValue('N'.$counter, (array_key_exists($reportRow->created_by, $filters['users'])) ? $filters['users'][$reportRow->created_by]->name : '');
                $activeSheet->setCellValue('O'.$counter, (array_key_exists($reportRow->converted_by, $filters['users'])) ? $filters['users'][$reportRow->converted_by]->name : '');
                $activeSheet->setCellValue('P'.$counter, (array_key_exists($reportRow->updated_by, $filters['users'])) ? $filters['users'][$reportRow->updated_by]->name : '');
                $activeSheet->setCellValue('Q'.$counter, (array_key_exists($reportRow->referred_by, $filters['users'])) ? $filters['users'][$reportRow->referred_by]->name : '');
                $counter++;
            }
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'Appointment Rescheduled Count Report'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }
}
