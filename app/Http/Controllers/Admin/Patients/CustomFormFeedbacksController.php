<?php

namespace App\Http\Controllers\Admin\Patients;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\CustomFormFeedbacks;
use App\Models\CustomForms;
use App\Models\Patients;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        if (! Gate::allows('patients_customform_manage')) {
            return abort(401);
        }
        $filters = Filters::all(Auth::User()->id, 'patient_custom_form_feedbacks');
        $patient = User::finduser($id);

        return view('admin.patients..card.custom_form_feedbacks.index', compact('patient', 'filters'));
    }

    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Throwable
     */
    public function datatable(Request $request, $id)
    {
        $filename = 'patient_custom_form_feedbacks';

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
        $iTotalRecords = CustomFormFeedbacks::getTotalRecords($request, Auth::user()->account_id, $apply_filter, $id, $filename);

        [$orderBy, $order] = getSortBy($request, 'created_at', 'desc', 'custom_form_feedbacks');

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $CustomFormFeedbacks = CustomFormFeedbacks::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::user()->account_id, $apply_filter, $id, $filename);

        $records = $this->getFilters($records, $filename);

        if ($CustomFormFeedbacks) {

            $records['data'] = $CustomFormFeedbacks;

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
            'edit' => Gate::allows('patients_customform_edit'),
            'manage' => Gate::allows('patients_customform_manage'),
        ];

        return ApiHelper::apiDataTable($records);
    }

    private function getFilters($records, $filename)
    {

        $records['active_filters'] = Filters::all(Auth::user()->id, $filename);

        return $records;
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        if (! Gate::allows('patients_customform_edit')) {
            return abort(401);
        }
        $custom_form_feedback = CustomFormFeedbacks::getAllFields($id);

        $patient_id = $custom_form_feedback->reference_id;

        if (! $custom_form_feedback) {
            return abort(404);
        }
        $patient_name = User::where('id', '=', $custom_form_feedback->reference_id)->first();

        return ApiHelper::makeResponse([
            'custom_form' => $custom_form_feedback,
            'patient_name' => $patient_name,
            'patient_id' => $patient_id,
        ], 'admin.patients.card.custom_form_feedbacks.edit');

    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
     */
    public function filled_preview($id)
    {
        if (! Gate::allows('custom_form_feedbacks_manage') && ! Gate::allows('patients_customform_manage')) {
            return abort(401);
        }

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($id);
        $patient_id = $custom_form_feedback->reference_id;

        if (! $custom_form_feedback) {
            return abort(404);
        }

        return ApiHelper::makeResponse([
            'custom_form' => $custom_form_feedback,
            'thisId' => $id,
            'patientId' => $patient_id,
        ], 'admin.patients.card.custom_form_feedbacks.filled_preview');

    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function filledPrint($id)
    {
        if (! Gate::allows('custom_form_feedbacks_manage') && ! Gate::allows('patients_customform_manage')) {
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
        if (! Gate::allows('custom_form_feedbacks_manage') && ! Gate::allows('patients_customform_manage')) {
            return abort(401);
        }

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($id);

        if (! $custom_form_feedback) {
            return view('error');
        }

        //return view('admin.custom_form_feedbacks.filled_export_pdf', ['custom_form' => $custom_form_feedback, 'thisId' => $id]);
        $pdfName = 'custom_form'.'_'.$id.'_'.date('YmdHis').'.pdf';
        $custom_form = $custom_form_feedback;
        $thisId = $id;
        $html = \View::make('admin.custom_form_feedbacks.filled_export_pdf', compact('custom_form', 'thisId'))->render();
        $pdfPath = public_path('pdf_download/'.$pdfName);
        $file = Browsershot::html($html)
            //->hideBackground()
            ->waitUntilNetworkIdle()
            ->landscape()
            //->showBackground()
            //->margins(0, 0, 0, 0)
            //->paperSize(216, 280)
            ->save($pdfPath);
        $headers = [
            'Content-Type: application/pdf',
        ];

        return response()->download($pdfPath, $pdfName, $headers);
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
     * Show the form for submitt to patient.
     *
     * @return $id
     */
    public function AddNewForm($id)
    {

        if (! Gate::allows('patients_customform_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $where = [];

        if (Auth::User()->account_id) {
            $where[] = [
                'account_id',
                '=',
                Auth::User()->account_id,
            ];
        }
        $where[] = [
            'custom_form_type',
            '=',
            '0',
        ];
        if (count($where)) {
            $CustomForms = CustomForms::where($where)->orderBy('sort_number', 'asc')->get();
        } else {
            $CustomForms = CustomForms::orderBy('sort_number', 'asc')->get();
        }

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'CustomForms' => $CustomForms,
            'id' => $id,
        ]);
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
     */
    public function fill_form($form_id, $patient_id)
    {
        if (! Gate::allows('patients_customform_create')) {
            return abort(401);
        }
        $users = Patients::where([
            ['active', '=', '1'],
            ['id', '=', $patient_id],
        ])->get();

        $custom_form = CustomForms::get_all_fields_data($form_id);

        return ApiHelper::makeResponse([
            'custom_form' => $custom_form,
            'users' => $users,
            'patient_id' => $patient_id,
        ], 'admin.patients.card.custom_form_feedbacks.create');
    }
}
