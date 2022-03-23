<?php

namespace App\Http\Controllers\Admin;

use App\Models\Appointments;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Appointmentimage;
use Auth;
use Illuminate\Support\Facades\Gate;
use DB;
use Validator;
use App\Models\Packages;
use App\Models\PackageBundles;
use App\Models\PackageAdvances;
use App\Models\Discounts;
use App\Models\Services;
use App\Models\User;
use Config;
use Carbon\Carbon;
use App\Models\PaymentModes;
use App\Models\Cities;
use App\Models\AuditTrails;

class AppointmentimageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        if (!Gate::allows('appointments_image_manage')) {
            return abort(401);
        }
        $appointment = Appointments::findorfail($id);
        return view('admin.appointments.images.index', compact('appointment'));
    }

    public function imagestore_before(Request $request, $id)
    {
        if ($request->type == 'checkedbefore') {
            $type = 'Before Appointment';
        } else {
            $type = 'After Appointment';
        }
        foreach ($request->file as $fileupload){

            if ($fileupload) {
                $file = $fileupload;
                $ext = $file->getClientOriginalExtension();
                $photo_url = md5(time() . rand(0001, 9999) . rand(78599, 99999)) . ".$ext";
                $file->move('appointment_image', $photo_url);

                if ($ext == 'jpg' || $ext == 'jpeg' || $ext == 'png' || $ext == 'gif') {
                    $data['image_name'] = $file->getClientOriginalName();
                    $data['image_path'] = $photo_url;
                    $data['type'] = $type;
                    $data['appointment_id'] = $id;
                    $appointment = Appointmentimage::createRecord($data,$id);

                } else {
                    flash('JPG , JPEG, PNG, GIF Only Allow.')->warning()->important();
                    return response()->json(array(
                        'status' => true,
                        'message' => flash('JPG , JPEG, PNG, GIF Only Allow.')->warning()->important(),
                        'id' => $id,
                    ));
                }
            } else {
                return response()->json(array(
                    'status' => true,
                    'message' => flash('Kindly Select Image First')->warning()->important(),
                    'id' => $id,
                ));
            }
        }
        return response()->json(array(
            'status' => true,
            'message' => flash('Picture save successfully.')->success()->important(),
            'id' => $id,
        ));

    }

    public function datatable(Request $request,$id){
        $records = array();
        $records["data"] = array();

        if ($request->get('customActionType') && $request->get('customActionType') == "group_action") {
            $appointmentimages = Appointmentimage::getBulkData_forimage($request->get('id'));
            if($appointmentimages) {
                foreach($appointmentimages as $appointmentimages) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if(!Appointmentimage::isChildExists($appointmentimages->id, Auth::User()->account_id)) {
                        $appointmentimages->delete();
                    }
                }
            }
            $records["customActionStatus"] = "OK"; // pass custom message(useful for getting status of group actions)
            $records["customActionMessage"] = "Records has been deleted successfully!"; // pass custom message(useful for getting status of group actions)
        }

        // Get Total Records
        $iTotalRecords = Appointmentimage::getTotalRecords($request, Auth::User()->account_id,$id);

        $iDisplayLength = intval($request->get('length'));
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->get('start'));
        $sEcho = intval($request->get('draw'));

        $appointmentimages = Appointmentimage::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id,$id);
        if($appointmentimages) {
            foreach($appointmentimages as $appointmentimg) {
                $records["data"][] = array(
                    'id' => '<label class="mt-checkbox mt-checkbox-single mt-checkbox-outline"><input name="id[]" type="checkbox" class="checkboxes" value="'.$appointmentimg->id.'"/><span></span></label>',
                    'patient_id' => $appointmentimg->appointment->patient_id,
                    'image' => view('admin.appointments.images.imagedisplay', compact('appointmentimg'))->render(),
                    'type' => $appointmentimg->type,
                    'created_at' => Carbon::parse($appointmentimg->created_at)->format('F j,Y h:i A'),
                    'actions' => view('admin.appointments.images.actions', compact('appointmentimg'))->render(),
                );
            }
        }
        $records["draw"] = $sEcho;
        $records["recordsTotal"] = $iTotalRecords;
        $records["recordsFiltered"] = $iTotalRecords;

        return response()->json($records);
    }

    public function destroy($id){

        if (!Gate::allows('appointments_image_destroy')) {
            return abort(401);
        }

        $appointmentimage = Appointmentimage::find($id);

        Appointmentimage::DeleteRecord($id);

        return redirect()->route('admin.appointmentsimage.imageindex',[$appointmentimage->appointment_id]);

    }
}
