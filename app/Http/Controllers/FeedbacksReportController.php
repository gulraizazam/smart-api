<?php

namespace App\Http\Controllers;

use App\Helpers\ACL;
use App\Models\Feedback;
use App\Models\Locations;
use App\Models\MembershipType;
use App\Models\Services;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FeedbacksReportController extends Controller
{
    public function feedbackReport()
    {

        $Users = User::getAllRecords(Auth::User()->account_id)->where('user_type_id', 5)->where('active', 1)->getDictionary();
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::User()->account_id);
        $services = Services::where('parent_id', 0)->where('active', 1)->get();
        $feedbacks = Feedback::with('doctor', 'service')->select('doctor_id')
        ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_feedbacks')
        ->groupBy('doctor_id')
        ->with('doctor')
        ->get();
       
        return view('admin.reports.feedback_report', get_defined_vars());

    }
    public function futureTreatmentsReport()
    {

        $membershipTypes = MembershipType::pluck('name', 'id');
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::User()->account_id);
        return view('admin.reports.future_treatments_report', get_defined_vars());

    }
    public function loadFeedbackReport(Request $request)
{


    $locationId = $request->centre_id ?? null;
    $doctorId = $request->doctor_id ?? null;
    $serviceId = $request->service_id ?? null;

    $doctorId = $doctorId === '' ? null : $doctorId;
    $serviceId = $serviceId === '' ? null : $serviceId;

    // Parse date range
     $period = $request->date_range;
    $dates = explode(' - ', $request->input('date_range'));
    $startDate = date('Y-m-d 00:00:00', strtotime($dates[0]));
    $endDate = date('Y-m-d 23:59:59', strtotime($dates[1]));

    $feedbacks = Feedback::query()
        ->when($locationId, fn($q) => $q->where('location_id', $locationId))
        ->when($serviceId, fn($q) => $q->where('service_id', $serviceId))
        ->when($doctorId, fn($q) => $q->where('doctor_id', $doctorId))
       ->whereHas('appointment', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('scheduled_date', [$startDate, $endDate]);
        });
       // ->whereBetween('created_at', [$startDate, $endDate]);

    // CASE LOGIC
    if ($locationId && !$serviceId && !$doctorId) {

        // CASE 1: Only centre → Avg rating of all doctors in that centre
        $result = $feedbacks->select('doctor_id')
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_feedbacks')
            ->groupBy('doctor_id')
            ->with('doctor')
            ->get();

    } elseif ($doctorId && !$serviceId && !$locationId) {

        // CASE 2: Only doctor → Avg rating per service
        $result = $feedbacks->select('service_id')
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_feedbacks')
            ->groupBy('service_id')
            ->with('service')
            ->get();

    } elseif ($serviceId && !$doctorId && !$locationId) {

        // CASE 3: Only service → All doctors’ rating against that service
        $result = $feedbacks->select('doctor_id')
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_feedbacks')
            ->groupBy('doctor_id')
            ->with('doctor')
            ->get();

    } elseif ($locationId && $doctorId && !$serviceId) {

        // CASE 4: location + doctor → That doctor's rating per service in that location
        $result = $feedbacks->select('service_id', 'doctor_id')
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_feedbacks')
            ->groupBy('service_id')
            ->with('service', 'doctor')
            ->get();

    } elseif ($locationId && $serviceId && !$doctorId) {

        // CASE 5: location + service → All doctors’ rating in that location for that service
        $result = $feedbacks->select('doctor_id')
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_feedbacks')
            ->groupBy('doctor_id')
            ->with('doctor')
            ->get();

    } elseif ($serviceId && $doctorId && !$locationId) {

        // CASE 6: service + doctor → Rating for that doctor + service
        $result = $feedbacks->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_feedbacks')->first();

    } elseif ($locationId && $doctorId && $serviceId) {
        $feedback = Feedback::where('location_id', $locationId)
        ->where('doctor_id', $doctorId)
        ->where('service_id', $serviceId)
        ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_feedbacks')
        ->first();

        $result = $feedback ? [$feedback] : [];

    } else {

        // Default: fallback to full feedback list if no logic matched
        $result = $feedbacks->with(['doctor', 'service', 'location'])->get();
    }

    return view('admin.reports.feedbackReport', compact('result'));
}

    public function loadFutureTreatmentsReport(Request $request) 
    {
        
        $dates = explode(' - ', $request->input('date_range'));
        $startDate = date('Y-m-d 00:00:00', strtotime($dates[0]));
        $endDate = date('Y-m-d 23:59:59', strtotime($dates[1]));
        $centreId = $request->input('centre_id');
        $patientId = $request->input('patient_id');
        $membershipId = $request->input('membership_id');
        $today = Carbon::today()->startOfDay();

    $patients = User::whereHas('appointmentsPatient', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('scheduled_date', [$startDate, $endDate])
                  ->where('appointment_type_id', 2)
                  ->where('appointment_status_id', 2); // arrived treatments in range
        })
        // ❌ No arrived treatments AFTER the end of selected range
        ->whereDoesntHave('appointmentsPatient', function ($query) use ($endDate) {
            $query->where('scheduled_date', '>', $endDate)
                  ->where('appointment_type_id', 2)
                  ->where('appointment_status_id', 2); // exclude if any arrived treatment exists after end date
        })
        // ❌ No appointment (any status/type) today or after
        ->whereDoesntHave('appointmentsPatient', function ($query) use ($today) {
            $query->where('scheduled_date', '>=', $today);
        })
        ->when($centreId, function ($query) use ($centreId) {
            $query->where('appointmentsPatient.location_id', $centreId);
        })
        ->when($patientId, function ($query) use ($patientId) {
            $query->where('appointmentsPatient.patient_id', $patientId);
        })
        ->when($membershipId, function ($query) use ($membershipId) {
            $query->whereHas('membership', function ($q) use ($membershipId) {
                $q->where('membership_type_id', $membershipId);
            });
        })
        ->with([
            'appointmentsPatient' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('scheduled_date', [$startDate, $endDate])
                      ->where('appointment_type_id', 2)
                      ->where('appointment_status_id', 2);
            },
            'membership.membershipType'
        ])
        ->get();

    return view('admin.reports.futureTreatmentsReport', compact('patients'));
    }
}
