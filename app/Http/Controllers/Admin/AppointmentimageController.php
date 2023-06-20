<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Appointmentimage;
use App\Models\Appointments;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppointmentimageController extends Controller
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
        if (! Gate::allows('appointments_image_manage')) {
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
        foreach ($request->file as $fileupload) {

            if ($fileupload) {
                $file = $fileupload;
                $ext = $file->getClientOriginalExtension();
                $fileName = time().'-'.str_replace(' ', '-', $file->getClientOriginalName());
                $file->storeAs('public/appointment_image', $fileName);

                if ($ext == 'jpg' || $ext == 'jpeg' || $ext == 'png' || $ext == 'gif') {
                    $data['image_name'] = $file->getClientOriginalName();
                    $data['image_path'] = $fileName;
                    $data['type'] = $type;
                    $data['appointment_id'] = $id;
                    $appointment = Appointmentimage::createRecord($data, $id);

                } else {
                    flash('JPG , JPEG, PNG, GIF Only Allow.')->warning()->important();

                    return response()->json([
                        'status' => true,
                        'message' => flash('JPG , JPEG, PNG, GIF Only Allow.')->warning()->important(),
                        'id' => $id,
                    ]);
                }
            } else {
                return response()->json([
                    'status' => true,
                    'message' => flash('Kindly Select Image First')->warning()->important(),
                    'id' => $id,
                ]);
            }
        }

        return response()->json([
            'status' => true,
            'message' => flash('Picture save successfully.')->success()->important(),
            'id' => $id,
        ]);

    }

    public function datatable(Request $request, $id)
    {

        $records = [];
        $records['data'] = [];

        $filters = getFilters($request->all());

        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $appointmentimages = Appointmentimage::getBulkData_forimage($ids);
            if ($appointmentimages) {
                foreach ($appointmentimages as $appointmentimages) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! Appointmentimage::isChildExists($appointmentimages->id, Auth::User()->account_id)) {
                        $appointmentimages->delete();
                    }
                }
            }
            $records['status'] = true; // pass custom message(useful for getting status of group actions)
            $records['message'] = 'Records has been deleted successfully!'; // pass custom message(useful for getting status of group actions)
        }

        // Get Total Records
        $iTotalRecords = Appointmentimage::getTotalRecords($request, Auth::User()->account_id, $id);

        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $appointmentimages = Appointmentimage::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $id);
        if ($appointmentimages) {
            foreach ($appointmentimages as $appointmentimg) {
                $records['data'][] = [
                    'id' => $appointmentimg->id,
                    'image_id' => $appointmentimg->id,
                    'patient_id' => $appointmentimg->appointment->patient_id,
                    'image_path' => $appointmentimg->image_path,
                    'type' => $appointmentimg->type,
                    'created_at' => Carbon::parse($appointmentimg->created_at)->format('F j,Y h:i A'),
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
            'delete' => Gate::allows('appointments_image_destroy'),
        ];

        return response()->json($records);
    }

    public function destroy($id)
    {

        if (! Gate::allows('appointments_image_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $response = Appointmentimage::DeleteRecord($id);

            return ApiHelper::apiResponse($this->success, $response['message'], $response['status']);

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }

    }
}
