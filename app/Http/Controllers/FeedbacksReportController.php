<?php

namespace App\Http\Controllers;

use App\Helpers\ACL;
use App\Models\Feedback;
use App\Models\Locations;
use App\Models\Services;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeedbacksReportController extends Controller
{
    public function feedbackReport()
    {

        $Users = User::getAllRecords(Auth::User()->account_id)->where('user_type_id', 5)->where('active', 1)->getDictionary();
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::User()->account_id);
        $services = Services::where('parent_id', 0)->where('active', 1)->get();

        return view('admin.reports.feedback_report', get_defined_vars());

    }
    public function loadFeedbackReport(Request $request)
{


    $locationId = $request->centre_id ?? null;
    $doctorId = $request->doctor_id ?? null;
    $serviceId = $request->service_id ?? null;

    $doctorId = $doctorId === '' ? null : $doctorId;
    $serviceId = $serviceId === '' ? null : $serviceId;

    // Parse date range
    // $dates = explode(' - ', $request->input('date_range'));
    // $startDate = date('Y-m-d 00:00:00', strtotime($dates[0]));
    // $endDate = date('Y-m-d 23:59:59', strtotime($dates[1]));

    $feedbacks = Feedback::query()
        ->when($locationId, fn($q) => $q->where('location_id', $locationId))
        ->when($serviceId, fn($q) => $q->where('service_id', $serviceId))
        ->when($doctorId, fn($q) => $q->where('doctor_id', $doctorId));
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
        $result = $feedbacks->select('service_id')
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_feedbacks')
            ->groupBy('service_id')
            ->with('service')
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
}
