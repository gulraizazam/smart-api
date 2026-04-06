<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App;
use App\Http\Controllers\Controller;
use App\Services\Appointment\AppointmentMeasurementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppointmentMeasurementController extends Controller
{
    public function __construct(
        private readonly AppointmentMeasurementService $service,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        if (! Gate::allows('appointments_measurement_manage')) {
            return abort(401);
        }

        $data = $this->service->getIndexData($id);

        return view('admin.appointments.measurements.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($id)
    {
        if (! Gate::allows('appointments_measurement_create')) {
            return abort(401);
        }

        $data = $this->service->getCreateData($id);

        return view('admin.appointments.measurements.AddNewMeasurements', $data);
    }

    /**
     * Show the form for creating new Measurements.
     *
     * @return \Illuminate\Http\Response
     */
    public function fill_form($form_id, $appointment_id)
    {
        if (! Gate::allows('appointments_measurement_create')) {
            return abort(401);
        }

        $data = $this->service->getFillFormData($form_id, $appointment_id);

        return view('admin.appointments.measurements.create', $data);
    }

    /**
     * Store the forms for measurement.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function submit_form(Request $request, $id, $appointment_id)
    {
        if (! Gate::allows('appointments_measurement_create')) {
            return abort(401);
        }

        $result = $this->service->submitForm($request, $id, $appointment_id);

        return response()->json(['message' => $result['message'], 'code' => $result['code']], $result['code']);
    }

    /**
     * Display a listing of Appointment measurement.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request, $id)
    {
        $records = $this->service->getDatatableData($request, $id);

        return response()->json($records);
    }

    /**
     * Show the form for editing Measurement.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! Gate::allows('appointments_measurement_edit')) {
            return abort(401);
        }

        $data = $this->service->getEditData($id);

        if (isset($data['error'])) {
            return view('error');
        }

        return view('admin.appointments.measurements.edit', $data);
    }

    /**
     * Update measurement in storage.
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateCustomFormFeedbacksRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update_measurement_field(Request $request, $id)
    {
        if (! Gate::allows('appointments_measurement_edit')) {
            return abort(401);
        }

        $result = $this->service->updateMeasurementField($request);

        return response()->json(['message' => $result['message'], 'code' => $result['code']], $result['code']);
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function filled_preview($id)
    {
        if (! Gate::allows('appointments_measurement_manage')) {
            return abort(401);
        }

        $data = $this->service->getFilledPreviewData($id);

        if (isset($data['error'])) {
            return view('error');
        }

        return view('admin.appointments.measurements.filled_preview', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function filledPrint($id)
    {
        if (! Gate::allows('appointments_measurement_manage')) {
            return abort(401);
        }

        $data = $this->service->getFilledPrintData($id);

        if (isset($data['error'])) {
            return view('error');
        }

        return view('admin.custom_form_feedbacks.appointment_measurement_filled_print', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function exportPdf($id)
    {
        ini_set('max_execution_time', '0');
        if (! Gate::allows('appointments_measurement_manage')) {
            return abort(401);
        }

        $data = $this->service->getExportPdfData($id);

        if (isset($data['error'])) {
            return view('error');
        }

        $content = view('admin.custom_form_feedbacks.appointment_measurement_filled_export_pdf', $data)->render();
        $pdf = App::make('dompdf.wrapper');

        $pdf->loadHTML($content)->setOptions(['defaultFont' => 'sans-serif']);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream('Measurement Form Report', 'landscape');
    }
}
