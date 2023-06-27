<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\CustomFormFeedbackDetails;
use App\Models\CustomFormFeedbacks;
use App\Models\CustomForms;
use App\Models\Patients;
use App\Models\User;
use Barryvdh\DomPDF\Facade as PDF;
use Carbon\Carbon;
//use Barryvdh\DomPDF\Facade as PDF;
//use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;

class CustomFormFeedbacksController extends Controller
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
     * Display a listing of Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
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
    public function datatable(Request $request)
    {
        $filename = 'custom_form_feedbacks';

        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, $filename);

        $records = [];
        $records['data'] = [];

        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $CustomFormFeedbacks = CustomFormFeedbacks::getBulkData($ids);
            if ($CustomFormFeedbacks) {
                foreach ($CustomFormFeedbacks as $custom_form_feedback) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! CustomFormFeedbacks::isChildExists($custom_form_feedback->id, Auth::User()->account_id)) {
                        $custom_form_feedback->delete();
                    }
                }
            }
            $records['status'] = true; // pass custom message(useful for getting status of group actions)
            $records['message'] = 'Records has been deleted successfully!'; // pass custom message(useful for getting status of group actions)
        }

        // Get Total Records
        $iTotalRecords = CustomFormFeedbacks::getTotalRecords($request, Auth::User()->account_id, $apply_filter, false, $filename);

        [$orderBy, $order] = getSortBy($request);

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $CustomFormFeedbacks = CustomFormFeedbacks::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter, false, $filename);

        $records = $this->getFilters($records);

        if ($CustomFormFeedbacks) {
            foreach ($CustomFormFeedbacks as $custom_form_feedback) {
                $records['data'][] = [
                    'id' => $custom_form_feedback->internal_id,
                    'patient_id' => $custom_form_feedback->user ? \App\Helpers\GeneralFunctions::patientSearchStringAdd($custom_form_feedback->user->id) : null,
                    'form_name' => $custom_form_feedback->form_name,
                    'patient_name' => $custom_form_feedback->user ? $custom_form_feedback->user->name : null,
                    'created_at' => Carbon::parse($custom_form_feedback->created_at)->format('F j,Y h:i A'),
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

        $records['permissions'] = [
            'edit' => Gate::allows('custom_form_feedbacks_edit'),
            'delete' => Gate::allows('custom_form_feedbacks_destroy'),
            'active' => Gate::allows('custom_form_feedbacks_active'),
            'inactive' => Gate::allows('custom_form_feedbacks_inactive'),
            'preview' => Gate::allows('custom_form_feedbacks_preview'),
        ];

        return ApiHelper::apiDataTable($records);
    }

    private function getFilters($records)
    {

        $records['active_filters'] = Filters::all(Auth::User()->id, 'custom_form_feedbacks');

        return $records;
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return abort(401);
        }

        $forms = CustomForms::getAllForms(Auth::User()->account_id)->toArray();

        if (! $forms) {
            flash('No Form Available to fill, please try again later.')->error()->important();

            return redirect()->route('admin.custom_form_feedbacks.index');
        } else {
            return view('admin.custom_form_feedbacks.create', ['forms' => $forms]);
        }

    }

    /**
     * update form field
     *
     * @param $form_id
     * @param $field_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update_field(Request $request, $feedback_id, $feedback_field_id)
    {

        if (! Gate::allows('custom_form_feedbacks_manage') && ! Gate::allows('patients_customform_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        try {

            $data = $request->all();

            $data = CustomFormFeedbackDetails::updateRecord($request, Auth::User()->account_id, Auth::id(), $feedback_id, $feedback_field_id);

            if ($data) {
                return ApiHelper::apiResponse($this->success, 'Record updated successfully.', true, $data);
            } else {
                return ApiHelper::apiResponse($this->success, 'Failed to update the record.', false, $data);
            }

        } catch (\Exception $e) {
            ApiHelper::apiException($e);
        }
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function fill_form($form_id)
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return abort(401);
        }
        $custom_form = CustomForms::get_all_fields_data($form_id);

        return view('admin.custom_form_feedbacks.create', compact('custom_form'));

    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function preview_form($form_id)
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return abort(401);
        }
        $users = Patients::getActiveOnly()->toArray();

        $custom_form = CustomForms::get_all_fields_data($form_id);

        return view('admin.custom_form_feedbacks.preview', compact('custom_form', 'users'));

    }

    /**
     * Store a newly created Permission in storage.
     *
     * @return void
     */
    public function store(Request $request)
    {
        dd($request->all());
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function submit_form(Request $request, $id)
    {

        if (! Gate::allows('custom_form_feedbacks_manage') && ! Gate::allows('patients_customform_create')) {
            return abort(401);
        }

        $custom_form_feedback = CustomFormFeedbacks::createRecord($request, $id, Auth::User()->account_id, Auth::id());

        if (! $custom_form_feedback) {
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
    public function edit($id)
    {
        if (! Gate::allows('custom_form_feedbacks_edit')) {
            return ApiHelper::denyAccess();
        }

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($id);

        if (! $custom_form_feedback) {
            return view('error');
        }
        $patient_name = User::where('id', '=', $custom_form_feedback->reference_id)->first();

        return ApiHelper::makeResponse([
            'custom_form' => $custom_form_feedback,
            'patient_name' => $patient_name,
        ], 'admin.custom_form_feedbacks.edit');
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function filled_preview($id)
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return ApiHelper::denyAccess();
        }

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($id);

        if (! $custom_form_feedback) {
            return view('error');
        }

        return ApiHelper::makeResponse([
            'custom_form' => $custom_form_feedback,
            'thisId' => $id,
        ], 'admin.custom_form_feedbacks.filled_preview');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function filledPrint($id)
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return abort(401);
        }

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($id);

        if (! $custom_form_feedback) {
            return view('error');
        }

        return view('admin.custom_form_feedbacks.filled_print', ['custom_form' => $custom_form_feedback, 'thisId' => $id]);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function exportPdf($id)
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return abort(401);
        }

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($id);

        if (! $custom_form_feedback) {
            return view('error');
        }

        //return view('admin.custom_form_feedbacks.filled_export_pdf', ['custom_form' => $custom_form_feedback, 'thisId' => $id]);
        $pdfName = 'custom_patient_feedback_form'.'_'.$id.'_'.date('YmdHis').'.pdf';
        $custom_form = $custom_form_feedback;
        $thisId = $id;
        $dompdf = new Dompdf();
        $content = \View::make('admin.custom_form_feedbacks.filled_export_pdf', compact('custom_form', 'thisId'));
        $dompdf->loadHtml($content);
        $dompdf->setPaper('A3');
        $dompdf->render();

        return $dompdf->stream();
        /*$pdfPath = public_path('pdf_download/' . $pdfName);
        $file = Browsershot::html($html)
            //->hideBackground()
            ->waitUntilNetworkIdle()
            ->landscape()
            //->showBackground()
            //->margins(0, 0, 0, 0)
            //->paperSize(216, 280)
            ->save($pdfPath);
        $headers = array(
            'Content-Type: application/pdf',
        );
        return response()->download($pdfPath, $pdfName, $headers);*/
        //return $file->download($pdfName);
        /*
                try {
                    $options = [
                        'orientation'   => 'landscape',
                        'encoding'      => 'UTF-8',
                        //'header-html'   => $page_header_html,
                        //'footer-html'   => $page_footer_html
                        'zoom' => 1,
                        //'margin-bottom' => '10mm'
                    ];
                    $pdf = PDF::loadView('admin.custom_form_feedbacks.filled_export_pdf', ['custom_form' => $custom_form_feedback, 'thisId' => $id])
                        ->setPaper('A4', 'landscape')
                        //->setOption('zoom', 1)
                        //->setOption('margin-top', '40mm')
                        //->setOption('margin-bottom', '10mm');
                        ->setOptions($options);
                    $pdfName = 'custom_form'.'_'.$id.'_'.date('YmdHis') . ".pdf";
                    return $pdf->download($pdfName);
                    //return $pdf->inline($pdfName);
                } catch (Exception $e) {
                    Log::info($e);
                    return redirect()->back()->withError(Lang::get('messages.error.general'));
                }
        */
        //$pdf = PDF::loadView('admin.custom_form_feedbacks.filled_export_pdf', ['custom_form' => $custom_form_feedback, 'thisId' => $id]);
        //$pdf->setPaper('A4', 'landscape');
        //return $pdf->stream('staffReport', 'landscape');
        /*
                $pdfName = 'custom_form'.'_'.$id.'_'.date('YmdHis') . ".pdf";
                $output_file = public_path("assets/pdf_download/".$pdfName);
                $pdf = PDF::loadView('admin.custom_form_feedbacks.filled_export_pdf',['custom_form' => $custom_form_feedback, 'thisId' => $id])->setPaper('A4', 'landscape')->save($output_file);

                $headers = array(
                    'Content-Type: application/pdf',
                );
                return response()->download($output_file, $pdfName, $headers);
        */
    }

    /**
     * Update Permission in storage.
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateCustomFormFeedbacksRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        if (! Gate::allows('custom_form_feedbacks_edit') && ! Gate::allows('patients_customform_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        try {

            if (CustomFormFeedbacks::updateRecord($id, $request, Auth::User()->account_id, Auth::id())) {

                return ApiHelper::apiResponse($this->success, 'your Feedback is updated successfully.');

            }

            return ApiHelper::apiResponse($this->success, 'Invalid request.', false);

        } catch (\Exception $e) {
            ApiHelper::apiException($e);
        }

    }

    /**
     * Remove Permission from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $custom_form_feedback = CustomFormFeedbacks::getData($id);

        if (! $custom_form_feedback) {
            return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (CustomFormFeedbacks::isChildExists($id, Auth::User()->account_id)) {
            return ApiHelper::apiResponse($this->success, 'Child records exist, unable to delete resource.', false);
        }

        CustomFormFeedbacks::deleteRecord($id);

        return ApiHelper::apiResponse($this->success, 'Record has been deleted successfully.');
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function inactive($id)
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return abort(401);
        }

        $custom_form_feedback = CustomFormFeedbacks::getData($id);

        if (! $custom_form_feedback) {
            flash('Resource not found.')->error()->important();

            return redirect()->route('admin.custom_form_feedbacks.index');
        }

        CustomFormFeedbacks::inactivateRecord($id);

        flash('Record has been inactivated successfully.')->success()->important();

        return redirect()->route('admin.custom_form_feedbacks.index');
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function active($id)
    {
        if (! Gate::allows('custom_form_feedbacks_manage')) {
            return abort(401);
        }

        $custom_form_feedback = CustomFormFeedbacks::getData($id);

        if (! $custom_form_feedback) {
            flash('Resource not found.')->error()->important();

            return redirect()->route('admin.custom_form_feedbacks.index');
        }

        CustomFormFeedbacks::activateRecord($id);

        flash('Record has been inactivated successfully.')->success()->important();

        return redirect()->route('admin.custom_form_feedbacks.index');
    }
}
