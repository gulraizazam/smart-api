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
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::User()->account_id);
        $services = Services::where('parent_id', 0)
            ->where('active', 1)
            ->where('slug', '!=', 'all')
            ->where('name', 'NOT LIKE', '%refund%')
            ->where('name', 'NOT LIKE', '%settlement%')
            ->get();

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
       // CASE 6: service + doctor → Rating for that doctor + service
    $record = $feedbacks->select('doctor_id', 'service_id')
        ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_feedbacks')
        ->with('doctor', 'service')
        ->first();

    $result = $record ? [$record] : [];

    } elseif ($locationId && $doctorId && $serviceId) {
        $feedback = Feedback::where('location_id', $locationId)
        ->where('doctor_id', $doctorId)
        ->where('service_id', $serviceId)
        ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_feedbacks')
        ->first();

        $result = $feedback ? [$feedback] : [];

    } else {

        // Default: fallback to full feedback list if no logic matched
       $result = $feedbacks->select('doctor_id')
    ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_feedbacks')
    ->groupBy('doctor_id')
    ->with('doctor')
    ->get();
    }

    return view('admin.reports.feedbackReport', compact('result'));
}

    public function loadFutureTreatmentsReport(Request $request)
    {
        // Set fixed date range: today + next 7 days (including today)
        $startDate = Carbon::today()->startOfDay();
        $endDate = Carbon::today()->addDays(6)->endOfDay();

        $centreId = $request->input('centre_id');
        $serviceId = $request->input('service_id');

        // Get child service IDs if service is selected
        $serviceIds = [];
        if ($serviceId) {
            $serviceIds[] = $serviceId; // Include parent service ID

            // Fetch all child service IDs
            $childServices = DB::table('services')
                ->where('parent_id', $serviceId)
                ->where('active', 1)
                ->pluck('id')
                ->toArray();

            $serviceIds = array_merge($serviceIds, $childServices);
        }

        // Query appointments table with the specified filters
        $appointments = DB::table('appointments')
            ->join('users', 'appointments.patient_id', '=', 'users.id')
            ->join('services', 'appointments.service_id', '=', 'services.id')
            ->join('appointment_statuses', 'appointments.appointment_status_id', '=', 'appointment_statuses.id')
            ->where('appointments.appointment_type_id', 2)
            ->where('appointments.appointment_status_id', 1)
            ->whereBetween('appointments.scheduled_date', [$startDate, $endDate])
            ->when($centreId, function ($query) use ($centreId) {
                return $query->where('appointments.location_id', $centreId);
            })
            ->when(!empty($serviceIds), function ($query) use ($serviceIds) {
                return $query->whereIn('appointments.service_id', $serviceIds);
            })
            ->select(
                'users.name as patient_name',
                'services.name as service_name',
                'appointments.scheduled_date',
                'appointment_statuses.name as appointment_status'
            )
            ->orderBy('appointments.scheduled_date', 'asc')
            ->get();

        $filters = [
            'start_date' => $startDate->format('d M Y'),
            'end_date' => $endDate->format('d M Y'),
            'centre_id' => $centreId,
            'service_id' => $serviceId
        ];

        return view('admin.reports.future_treatments_report_data', compact('appointments', 'filters'));
    }
}
