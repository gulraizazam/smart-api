<?php

namespace App\Http\Controllers\Admin;

use Validator;
use Carbon\Carbon;
use App\Helpers\ACL;
use App\Models\User;
use App\Models\Refunds;
use App\Helpers\Filters;
use App\Models\Packages;
use App\Models\Settings;
use App\Models\Locations;
use App\Models\Appointments;
use App\Models\PaymentModes;
use Illuminate\Http\Request;
use App\Models\PackageBundles;
use App\Models\PackageService;
use App\HelperModule\ApiHelper;
use App\Models\PackageAdvances;
use App\Helpers\GeneralFunctions;
use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class RefundsController extends Controller
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
                $query->whereIn('leads.location_id', ACL::getUserCentres());
               
            });
            if (count($where)) {
                $resultQuery->where($where);
            }
           
            
                $feedbacks = $resultQuery->limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderBy('id', 'desc')
                    ->get();
           
            $Users = User::getAllRecords(Auth::User()->account_id)->getDictionary();
            $Locations = Locations::getAllRecords(Auth::User()->account_id)->getDictionary();
           
            
           
            $records = $this->getFiltersData($records, $filename);
            if ($feedbacks->count()) {
                $index = 0;
                foreach ($feedbacks as $feedback) {
                    
                    $records['data'][$index] = [
                        'id' => $feedback->id,
                        'paient_id' => $feedback->patient_id,
                        'paient_name' => $feedback->patient_name,
                        
                        'phone' => Gate::allows('contact') ? GeneralFunctions::prepareNumber4Call($feedback->phone) : '***********',
                        
                        'service_id' => $feedback->service_id ?? '',
                        
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
                    'sort' => $order,
                ];
            }
            $records['permissions'] = [
                'edit' => Gate::allows('feedbacks_edit'),
                'delete' => Gate::allows('feedbacks_destroy'),
                
                'create' => Gate::allows('feedbacks_create'),
               
            ];

            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
    public function getTreatment(Request $request)
    {
        $treatments = Appointments::with('service')
        ->where('patient_id', $request->patient_id)
        ->where('appointment_type_id', 2)
        ->where('appointment_status_id', 2)
        ->get();
        return response()->json([
            'status' => 1,
            'message' => 'Treatment found',
            'treatments' => $treatments
        ]);
    }
    public function getTreatmentInfo(Request $request)
    {
       
        $treatments = Appointments::with('doctor')
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
           'service_id'=>$request->treatment_id
        ])->first();
      
        if($checkFeedback)
        {
            return ApiHelper::apiResponse($this->error, 'Feedback already added', false);
        }
        // $validator = $this->verifyFields($request);
       
        // if ($validator->fails()) {
        //     return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        // }
        $patintPhone = User::whereId($request->patient_id)->first();
        $feedback = new Feedback();
     
        $feedback->patient_id = $request->patient_id;
        $feedback->patient_name = $request->patient_name;
        $feedback->phone = $patintPhone->phone;
        $feedback->service_id = $request->treatment_id;
        $feedback->created_by = Auth::User()->id;
        $feedback->created_at = Carbon::now();
        $feedback->save();
        return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        
        
       
    }
    
}
