<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CustomForm\CustomFormFeedbackService;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomFormFeedbacksController extends Controller
{
    public function __construct(
        private readonly CustomFormFeedbackService $customFormFeedbackService,
    ) {}

    /**
     * Display a listing of Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): \Illuminate\View\View
    {

        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return abort(401);
        }

        return view('admin.custom_form_feedbacks.index');
    }

    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     *
     * @throws \Throwable
     */
    public function datatable(Request $request): \Illuminate\Http\JsonResponse
    {
        $records = $this->customFormFeedbackService->getDatatableData($request->all());

        return response()->json($records);
    }

    /**
     * Return the list of forms available to fill (consumed by the SPA).
     */
    public function create(): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return $this->errorResponse('You are not authorized to perform this action.', 403);
        }

        $forms = $this->customFormFeedbackService->getAllForms();

        if (! $forms) {
            return $this->errorResponse('No Form Available to fill, please try again later.', 404);
        }

        return $this->successResponse('Record found.', ['forms' => $forms]);
    }

    /**
     * update form field
     *
     * @param $form_id
     * @param $field_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update_field(Request $request, int $feedback_id, int $feedback_field_id): \Illuminate\Http\JsonResponse
    {

        if (! Gate::allows('custom_form_feedbacks_manage') && ! Gate::allows('patients_customform_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }
        try {

            $result = $this->customFormFeedbackService->updateField($request->all(), $feedback_id, $feedback_field_id);

            if ($result['success']) {
                return $this->successResponse('Record updated successfully.', $result['data'], 200);
            } else {
                return $this->errorResponse('Failed to update the record.', 200);
            }

        } catch (\Exception $e) {
            return $this->handleException($e, 'CustomFormFeedbacksController');
        }
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function fill_form(int $form_id): \Illuminate\View\View
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return abort(401);
        }
        $custom_form = $this->customFormFeedbackService->getFormFieldsData($form_id);

        return view('admin.custom_form_feedbacks.create', compact('custom_form'));

    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function preview_form(int $form_id): \Illuminate\View\View
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return abort(401);
        }

        $data = $this->customFormFeedbackService->getPreviewFormData($form_id);

        $users = $data['users'];
        $custom_form = $data['custom_form'];

        return view('admin.custom_form_feedbacks.preview', compact('custom_form', 'users'));

    }

    /**
     * Store a newly created Permission in storage.
     *
     * @return void
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        // Note: Form submission is handled by submit_form() method
        return $this->errorResponse('Use fill_form and submit_form to create feedback records.', 400);
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function submit_form(Request $request, int $id): \Illuminate\Http\JsonResponse
    {

        if (! Gate::allows('custom_form_feedbacks_manage') && ! Gate::allows('patients_customform_create')) {
            return abort(401);
        }

        $result = $this->customFormFeedbackService->submitForm($request->all(), $id);

        if (! $result['success']) {
            return response()->json(['message' => 'Invalid request', 'code' => 402], 402);
        }

        return response()->json(['message' => 'your Form is filled successfully', 'code' => '200'], 200);
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(int $id): \Illuminate\View\View
    {
        if (! Gate::allows('custom_form_feedbacks_edit')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        $data = $this->customFormFeedbackService->getEditData($id);

        if (! $data) {
            return view('error');
        }

        return $this->successResponse('Record found.', $data);
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function filled_preview(int $id): \Illuminate\View\View
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        $data = $this->customFormFeedbackService->getFilledPreviewData($id);

        if (! $data) {
            return view('error');
        }

        return $this->successResponse('Record found.', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function filledPrint(int $id): \Illuminate\View\View
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return abort(401);
        }

        $data = $this->customFormFeedbackService->getFilledPrintData($id);

        if (! $data) {
            return view('error');
        }

        return view('admin.custom_form_feedbacks.filled_print', ['custom_form' => $data['custom_form'], 'thisId' => $data['thisId']]);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function exportPdf(int $id): \Illuminate\View\View
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return abort(401);
        }

        $data = $this->customFormFeedbackService->getExportPdfData($id);

        if (! $data) {
            return view('error');
        }

        //return view('admin.custom_form_feedbacks.filled_export_pdf', ['custom_form' => $custom_form_feedback, 'thisId' => $id]);
        $pdfName = 'custom_patient_feedback_form'.'_'.$id.'_'.date('YmdHis').'.pdf';
        $custom_form = $data['custom_form'];
        $thisId = $data['thisId'];
        $dompdf = new Dompdf();
        $content = \View::make('admin.custom_form_feedbacks.filled_export_pdf', compact('custom_form', 'thisId'));
        $dompdf->loadHtml($content);
        $dompdf->setPaper('A3');
        $dompdf->render();

        return $dompdf->stream();
    }

    /**
     * Update Permission in storage.
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateCustomFormFeedbacksRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {

        if (! Gate::allows('custom_form_feedbacks_edit') && ! Gate::allows('patients_customform_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }
        try {

            if ($this->customFormFeedbackService->updateFeedback($id, $request->all())) {

                return $this->successResponse('your Feedback is updated successfully.', null, 200);

            }

            return $this->errorResponse('Invalid request.', 200);

        } catch (\Exception $e) {
            return $this->handleException($e, 'CustomFormFeedbacksController');
        }

    }

    /**
     * Remove Permission from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $result = $this->customFormFeedbackService->destroyFeedback($id);

        if (! $result['success']) {
            return $this->errorResponse($result['message'], 200);
        }

        return $this->successResponse($result['message'], null, 200);
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function inactive(int $id): \Illuminate\Http\RedirectResponse
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return abort(401);
        }

        $result = $this->customFormFeedbackService->inactivateFeedback($id);

        if (! $result['success']) {
            flash($result['message'])->error()->important();

            return redirect()->route('admin.custom_form_feedbacks.index');
        }

        flash($result['message'])->success()->important();

        return redirect()->route('admin.custom_form_feedbacks.index');
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function active(int $id): \Illuminate\Http\RedirectResponse
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return abort(401);
        }

        $result = $this->customFormFeedbackService->activateFeedback($id);

        if (! $result['success']) {
            flash($result['message'])->error()->important();

            return redirect()->route('admin.custom_form_feedbacks.index');
        }

        flash($result['message'])->success()->important();

        return redirect()->route('admin.custom_form_feedbacks.index');
    }
}
