<?php

namespace App\Http\Controllers\Admin\Patients;

use App\Helpers\Filters;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Input;
use App\Models\CustomFormFeedbackDetails;
use App\Models\CustomFormFeedbacks;
use App\Models\CustomForms;
use App\Models\Patients;
use App\User;
use Carbon\Carbon;
use Spatie\Browsershot\Browsershot;

class CustomFormFeedbacksController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        if (!Gate::allows('patients_customform_manage')) {
            return abort(401);
        }
        $filters = Filters::all(Auth::User()->id, 'patient_custom_form_feedbacks');
        $patient = User::finduser($id);
        return view('admin.patients..card.custom_form_feedbacks.index',compact('patient','filters'));
    }
    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     * @throws \Throwable
     */
    public function datatable(Request $request,$id)
    {
        $filename = 'patient_custom_form_feedbacks' ;
        $apply_filter = false;
        if ($request->get('action')) {
            $action = $request->get('action');
            if (isset($action[0]) && $action[0] == 'filter_cancel') {
                Filters::flush(Auth::User()->id, $filename);
            } else if ($action == 'filter') {
                $apply_filter = true;
            }
        }
        $records = array();
        $records["data"] = array();

        if ($request->get('customActionType') && $request->get('customActionType') == "group_action") {
            $CustomFormFeedbacks = CustomFormFeedbacks::getBulkData($request->get('id'));
            if ($CustomFormFeedbacks) {
                foreach ($CustomFormFeedbacks as $custom_form_feedback) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (!CustomFormFeedbacks::isChildExists($custom_form_feedback->id, Auth::User()->account_id)) {
                        $custom_form_feedback->delete();
                    }
                }
            }
            $records["customActionStatus"] = "OK"; // pass custom message(useful for getting status of group actions)
            $records["customActionMessage"] = "Records has been deleted successfully!"; // pass custom message(useful for getting status of group actions)
        }

        // Get Total Records
        $iTotalRecords = CustomFormFeedbacks::getTotalRecords($request, Auth::User()->account_id, $apply_filter,$id, $filename);


        $iDisplayLength = intval($request->get('length'));
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->get('start'));
        $sEcho = intval($request->get('draw'));

        $end = $iDisplayStart + $iDisplayLength;
        $end = $end > $iTotalRecords ? $iTotalRecords : $end;

        $CustomFormFeedbacks = CustomFormFeedbacks::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter, $id, $filename);

//        return response()->json($CustomFormFeedbacks);
        if ($CustomFormFeedbacks) {
            foreach ($CustomFormFeedbacks as $custom_form_feedback) {
                $records["data"][] = array(
                    'form_name' => $custom_form_feedback->form_name,
                    "patient_name" => $custom_form_feedback->user? $custom_form_feedback->user->name : null,
                    'created_at' => Carbon::parse($custom_form_feedback->created_at_form)->format('F j,Y h:i A'),
                    'actions' => view('admin.patients.card.custom_form_feedbacks.actions', compact('custom_form_feedback'))->render(),
                );
            }
        }

        $records["draw"] = $sEcho;
        $records["recordsTotal"] = $iTotalRecords;
        $records["recordsFiltered"] = $iTotalRecords;

        return response()->json($records);
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!Gate::allows('patients_customform_edit')) {
            return abort(401);
        }
        $custom_form_feedback = CustomFormFeedbacks::getAllFields($id);

        $patient_id = $custom_form_feedback->reference_id;

        if (!$custom_form_feedback) {
            return view('error');
        }
        $patient_name  =  User::where('id','=',$custom_form_feedback->reference_id)->first();

        return view('admin.patients.card.custom_form_feedbacks.edit', ['custom_form' => $custom_form_feedback,'patient_name'=>$patient_name,'patient_id'=>$patient_id]);
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function filled_preview($id)
    {
        if (!Gate::allows('custom_form_feedbacks_manage') && !Gate::allows('patients_customform_manage')) {
            return abort(401);
        }


        $custom_form_feedback = CustomFormFeedbacks::getAllFields($id);
        $patient_id = $custom_form_feedback->reference_id;

        if (!$custom_form_feedback) {
            return view('error');
        }
        return view('admin.patients.card.custom_form_feedbacks.filled_preview', ['custom_form' => $custom_form_feedback, 'thisId' => $id, 'patientId' => $patient_id]);
    }
    /**
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function filledPrint($id)
    {
        if (!Gate::allows('custom_form_feedbacks_manage') && !Gate::allows('patients_customform_manage')) {
            return abort(401);
        }


        $custom_form_feedback = CustomFormFeedbacks::getAllFields($id);

        if (!$custom_form_feedback) {
            return view('error');
        }

        return view('admin.custom_form_feedbacks.filled_print', ['custom_form' => $custom_form_feedback, 'thisId' => $id]);
    }

    /**
     * @param $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|void
     */
    public function exportPdf($id)
    {
        if (!Gate::allows('custom_form_feedbacks_manage') && !Gate::allows('patients_customform_manage')) {
            return abort(401);
        }


        $custom_form_feedback = CustomFormFeedbacks::getAllFields($id);

        if (!$custom_form_feedback) {
            return view('error');
        }

        //return view('admin.custom_form_feedbacks.filled_export_pdf', ['custom_form' => $custom_form_feedback, 'thisId' => $id]);
        $pdfName = 'custom_form'.'_'.$id.'_'.date('YmdHis') . ".pdf";
        $custom_form = $custom_form_feedback;
        $thisId = $id;
        $html = \View::make('admin.custom_form_feedbacks.filled_export_pdf', compact('custom_form','thisId'))->render();
        $pdfPath = public_path('pdf_download/'.$pdfName);
        $file = Browsershot::html( $html )
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
    public function AddNewForm($id){

        if (!Gate::allows('patients_customform_create')) {
            return abort(401);
        }

        $where = array();

        if (Auth::User()->account_id) {
            $where[] = array(
                'account_id',
                '=',
                Auth::User()->account_id
            );
        }
        $where[] = array(
            'custom_form_type',
            '=',
            '0'
        );
        if (count($where)) {
            $CustomForms = CustomForms::where($where)->orderBy('sort_number','asc')->get();
        } else {
            $CustomForms = CustomForms::orderBy('sort_number','asc')->get();
        }
        return view('admin.patients.card.custom_form_feedbacks.AddNewForms',compact('CustomForms','id'));
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function fill_form($form_id,$patient_id)
    {
        if (!Gate::allows('patients_customform_create')) {
            return abort(401);
        }
        $users = Patients::where([
            ['active','=','1'],
            ['id','=',$patient_id]
        ])->get();

        $custom_form = CustomForms::get_all_fields_data($form_id);

        return view("admin.patients.card.custom_form_feedbacks.create", compact('custom_form', 'users','patient_id'));
    }


}
