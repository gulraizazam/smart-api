<?php

namespace App\Http\Controllers;

use App\HelperModule\ApiHelper;
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\ActivityLogger;
use App\Models\Appointments;
use App\Models\Feedback;
use App\Models\Locations;
use App\Models\Services;
use App\Models\User;
use Illuminate\Http\Request;
use DateTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class FeedbackController extends Controller
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
     * Display a listing of Lead.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Gate::allows('feedbacks_manage')) {
            return abort(401);
        }

        return view('admin.feedback.index');
    }
    public function datatable(Request $request)
    {
        try {
            $where = [];
            $records = [];
            $records['data'] = [];

            $filename = 'feedbacks';

            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, $filename);


            if (hasFilter($filters, 'created_at')) {
                $date_range = explode(' - ', $filters['created_at']);
                $start_date_time = date('Y-m-d H:i:s', strtotime($date_range[0]));
                $end_date_string = new DateTime($date_range[1]);
                $end_date_string->setTime(23, 59, 0);
                $end_date_time = $end_date_string->format('Y-m-d H:i:s');
            } else {
                $start_date_time = null;
                $end_date_time = null;
            }

            if (hasFilter($filters, 'patient_id')) {
                $where[] = ['patient_id', '=', $filters['patient_id']];
                Filters::put(Auth::User()->id, $filename, 'patient_id', $filters['patient_id']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $filename, 'patient_id');
                } else {
                    if (Filters::get(Auth::User()->id, $filename, 'patient_id')) {
                        $where[] = ['patient_id', '=', Filters::get(Auth::User()->id, $filename, 'patient_id')];
                    }
                }
            }
            if (hasFilter($filters, 'location_id')) {
                $where[] = ['location_id', '=', $filters['location_id']];
                Filters::put(Auth::User()->id, $filename, 'location_id', $filters['location_id']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $filename, 'location_id');
                } else {
                    if (Filters::get(Auth::User()->id, $filename, 'patient_id')) {
                        $where[] = ['location_id', '=', Filters::get(Auth::User()->id, $filename, 'location_id')];
                    }
                }
            }
            if (hasFilter($filters, 'doctor_id')) {
                $where[] = ['doctor_id', '=', $filters['doctor_id']];
                Filters::put(Auth::User()->id, $filename, 'doctor_id', $filters['doctor_id']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $filename, 'doctor_id');
                } else {
                    if (Filters::get(Auth::User()->id, $filename, 'doctor_id')) {
                        $where[] = ['doctor_id', '=', Filters::get(Auth::User()->id, $filename, 'doctor_id')];
                    }
                }
            }

            $iTotalRecords = Feedback::count();

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);
            $resultQuery = Feedback::with(['location', 'patient', 'doctor','service'])->where(function ($query) {
                $query->whereIn('feedback.location_id', ACL::getUserCentres());

            });
            if (count($where)) {
                $resultQuery->where($where);
            }


                $feedbacks = $resultQuery->limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderBy('id', 'desc')
                    ->get();

            $Users = User::getAllRecords(Auth::User()->account_id)->getDictionary();
            //$Locations = Locations::getAllRecords(Auth::User()->account_id)->getDictionary();


           $orderBy = 'created_at';
            $records = $this->getFiltersData($records, $filename);
            if ($feedbacks->count()) {
                $index = 0;
                foreach ($feedbacks as $feedback) {

                    $records['data'][$index] = [
                        'id' => $feedback->id,
                        'paient_id' => $feedback->patient_id,
                        'paient_name' => $feedback->patient_name,

                        'phone' => Gate::allows('contact') ? GeneralFunctions::prepareNumber4Call($feedback->patient->phone ?? '') : '***********',

                        'service_id' => $feedback->service_id ?? '',
                        'service'=> $feedback->service->name ?? '',
                        'treatment'=> $feedback->treatment->name ?? '',
                        'created_at' => Carbon::parse($feedback->created_at)->format('F j,Y h:i A'),
                        'created_by' => array_key_exists($feedback->created_by, $Users) ? $Users[$feedback->created_by]->name : 'N/A',
                        'location' => $feedback->location->name ?? '',
                        'doctor' => $feedback->doctor->name ?? '',
                        'rating' => $feedback->rating ?? '',
                    ];
                    $index++;
                }
                $records['meta'] = [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => $iDisplayLength,
                    'total' => $iTotalRecords,

                ];
            }
            $records['permissions'] = [
                'edit' => Gate::allows('feedbacks_edit'),
                'delete' => Gate::allows('feedbacks_delete'),

                'create' => Gate::allows('feedbacks_create'),


            ];

            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
    public function edit($id)
    {
        try {

            $feedback = Feedback::find($id);

            return response()->json($feedback);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
    public function update(Request $request,$id)
    {
        try {

            $feedback = Feedback::find($id);
            $feedback->rating = $request->rating;
            $feedback->save();
            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
    private function getFiltersData($records, $fileName)
    {
        $filters = Filters::all(Auth::User()->id, $fileName);

        $users = User::getAllActiveRecords(Auth::User()->account_id)->pluck('name', 'id');

        $records['filter_values'] = [

            'users' => $users,

        ];
        if (isset($filters['created_from'])) {
            $filters['created_from'] = date('Y-m-d', strtotime($filters['created_from']));
        }
        if (isset($filters['created_to'])) {
            $filters['created_to'] = date('Y-m-d', strtotime($filters['created_to']));
        }

        $records['active_filters'] = $filters;

        return $records;
    }
    public function getTreatment(Request $request)
    {
        $treatments = Appointments::with('service')
        ->where('patient_id', $request->patient_id)
        ->where('appointment_type_id', 2)
        ->where('appointment_status_id', 2)
        ->doesntHave('feedback')
        ->whereDate('scheduled_date', '>=', now()->subDays(7)) // Filter by scheduled_date
        ->get();
        return response()->json([
            'status' => 1,
            'message' => 'Treatment found',
            'treatments' => $treatments
        ]);
    }
    public function getTreatmentInfo(Request $request)
    {

        $treatments = Appointments::with(['doctor','location'])
        ->where('id', $request->treatment_id)
        ->where('appointment_type_id', 2)
        ->where('appointment_status_id', 2)
        ->first();
        return response()->json([
            'status' => 1,
            'message' => 'Treatment found',
            'treatments' => $treatments
        ]);
    }
    public function store(Request $request)
    {



        $checkFeedback = Feedback::where([
           'appointment_id'=>$request->treatment
        ])->first();

        if($checkFeedback)
        {
            return ApiHelper::apiResponse($this->error, 'Feedback already added', false);
        }
        // $validator = $this->verifyFields($request);

        // if ($validator->fails()) {
        //     return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        // }
        $treatment = Appointments::select('patient_id','doctor_id','location_id','service_id')->whereId($request->treatment)->first();
        $patintPhone = User::whereId($treatment->patient_id)->first();
        $parentId = Services::whereId($treatment->service_id)->first();
        $feedback = new Feedback();

        $feedback->patient_id = $treatment->patient_id;
        $feedback->patient_name = $patintPhone->name;
        $feedback->patient_phone = $patintPhone->phone;
        $feedback->service_id = $parentId->parent_id;
        $feedback->treatment_id = $treatment->service_id;
        $feedback->appointment_id = $request->treatment;
        $feedback->created_by = Auth::User()->id;
        $feedback->location_id = $treatment->location_id;
        $feedback->doctor_id = $treatment->doctor_id;
        $feedback->rating = $request->rating;
        $feedback->comment = $request->comment;
        $feedback->save();
        
        // Log feedback activity
        $appointment = Appointments::find($request->treatment);
        $location = Locations::find($treatment->location_id);
        $service = Services::find($treatment->service_id);
        ActivityLogger::logFeedbackAdded($feedback, $appointment, $patintPhone, $service, $location);
        
        return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');



    }
    public function destroy($id)
    {

        $feedback = Feedback::find($id);
        $feedback->delete();


        return ApiHelper::apiResponse($this->success, 'Record has been deleted successfully.');



    }
}
