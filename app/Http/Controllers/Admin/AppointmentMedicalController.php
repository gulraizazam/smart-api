<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Appointment\AppointmentMedicalService;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppointmentMedicalController extends Controller
{
    public function __construct(
        private readonly AppointmentMedicalService $service,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id): mixed
    {
        if (! Gate::allows('appointments_medical_form_manage')) {
            return abort(401);
        }

        $data = $this->service->getIndexData($id);

        return view('admin.appointments.medicals.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create($id): mixed
    {
        if (! Gate::allows('appointments_medical_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $data = $this->service->getCustomFormsForCreate($id);

        return $this->successResponse('Records found.', $data, 200);
    }

    /**
     * Show the form for creating new Medical history form.
     *
     * @return \Illuminate\Http\Response
     */
    public function fill_form($form_id, $appointment_id): mixed
    {
        if (! Gate::allows('appointments_medical_create')) {
            return abort(401);
        }

        $data = $this->service->getFillFormData($form_id, $appointment_id);

        return view('admin.appointments.medicals.create', $data);
    }

    /**
     * Store the forms for medical history form.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function submit_form(Request $request, $id, $appointment_id): mixed
    {
        if (! Gate::allows('appointments_medical_create')) {
            return abort(401);
        }

        $result = $this->service->submitForm($request, $id, $appointment_id);

        return response()->json(['message' => $result['message'], 'code' => $result['code']], $result['code']);
    }

    /**
     * Display a listing of Appointment medical history form.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request, $id): mixed
    {
        $records = $this->service->getDatatableData($request, $id);

        return response()->json($records);
    }

    /**
     * Show the form for editing medical history form.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id): mixed
    {
        if (! Gate::allows('appointments_medical_edit')) {
            return abort(401);
        }

        $data = $this->service->getEditData($id);

        if (isset($data['error'])) {
            return view('error');
        }

        return view('admin.appointments.medicals.edit', $data);
    }

    /**
     * Update medical in storage.
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateCustomFormFeedbacksRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update_medical_field(Request $request, $id): mixed
    {
        if (! Gate::allows('appointments_measurement_edit')) {
            return abort(401);
        }

        $result = $this->service->updateMedicalField($request);

        return response()->json(['message' => $result['message'], 'code' => $result['code']], $result['code']);
    }

    /**
     * Show the medical form for preview .
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function filled_preview($id): mixed
    {
        if (! Gate::allows('appointments_medical_form_manage')) {
            return abort(401);
        }

        $data = $this->service->getFilledPreviewData($id);

        if (isset($data['error'])) {
            return view('error');
        }

        return view('admin.appointments.medicals.filled_preview', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function filledPrint($id): mixed
    {
        if (! Gate::allows('appointments_medical_form_manage')) {
            return abort(401);
        }

        $data = $this->service->getFilledPrintData($id);

        if (isset($data['error'])) {
            return view('error');
        }

        return view('admin.custom_form_feedbacks.appointment_medical_filled_print', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function exportPdf($id): mixed
    {
        if (! Gate::allows('appointments_medical_form_manage')) {
            return abort(401);
        }

        $data = $this->service->getExportPdfData($id);

        if (isset($data['error'])) {
            return view('error');
        }

        $dompdf = new Dompdf();
        $content = view('admin.custom_form_feedbacks.appointment_medical_filled_export_pdf', $data);

        $dompdf->loadHtml($content);
        $dompdf->setPaper('A3');
        $dompdf->render();

        return $dompdf->stream();
    }
}
