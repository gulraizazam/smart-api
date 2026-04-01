<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ACL;
use App\Http\Requests\Feedback\FeedbackReportRequest;
use App\Models\Locations;
use App\Models\Services;
use App\Services\Feedback\FeedbackService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FeedbacksReportController extends Controller
{
    public function __construct(
        private readonly FeedbackService $feedbackService,
    ) {}

    public function feedbackReport(): View
    {
        abort_unless(Gate::allows('feedbacks_manage'), 401);

        $data = $this->feedbackService->getReportPageData();

        $Users = $data['doctors'];
        $locations = $data['locations'];
        $services = $data['services'];
        $feedbacks = $data['feedbacks'];

        return view('admin.reports.feedback_report', compact(
            'Users',
            'locations',
            'services',
            'feedbacks',
        ));
    }

    public function loadFeedbackReport(FeedbackReportRequest $request): View
    {
        $validated = $request->validated();

        $locationId = !empty($validated['centre_id']) ? (int) $validated['centre_id'] : null;
        $doctorId = !empty($validated['doctor_id']) ? (int) $validated['doctor_id'] : null;
        $serviceId = !empty($validated['service_id']) ? (int) $validated['service_id'] : null;

        $result = $this->feedbackService->getReportData(
            locationId: $locationId,
            doctorId: $doctorId,
            serviceId: $serviceId,
            dateRange: $validated['date_range'],
        );

        return view('admin.reports.feedbackReport', compact('result'));
    }

    public function futureTreatmentsReport(): View
    {
        $accountId = Auth::user()->account_id;

        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), $accountId);
        $services = Services::where('parent_id', 0)
            ->where('active', 1)
            ->where('slug', '!=', 'all')
            ->where('name', 'NOT LIKE', '%refund%')
            ->where('name', 'NOT LIKE', '%settlement%')
            ->get();

        return view('admin.reports.future_treatments_report', compact('locations', 'services'));
    }

    public function loadFutureTreatmentsReport(Request $request): View
    {
        $startDate = Carbon::today()->startOfDay();
        $endDate = Carbon::today()->addDays(6)->endOfDay();

        $centreId = $request->input('centre_id');
        $serviceId = $request->input('service_id');

        $serviceIds = [];
        if ($serviceId) {
            $serviceIds[] = $serviceId;

            $childServices = DB::table('services')
                ->where('parent_id', $serviceId)
                ->where('active', 1)
                ->pluck('id')
                ->toArray();

            $serviceIds = array_merge($serviceIds, $childServices);
        }

        $appointments = DB::table('appointments')
            ->join('users', 'appointments.patient_id', '=', 'users.id')
            ->join('services', 'appointments.service_id', '=', 'services.id')
            ->join('appointment_statuses', 'appointments.appointment_status_id', '=', 'appointment_statuses.id')
            ->where('appointments.appointment_type_id', 2)
            ->where('appointments.appointment_status_id', 1)
            ->whereBetween('appointments.scheduled_date', [$startDate, $endDate])
            ->when($centreId, fn ($query) => $query->where('appointments.location_id', $centreId))
            ->when(!empty($serviceIds), fn ($query) => $query->whereIn('appointments.service_id', $serviceIds))
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
            'service_id' => $serviceId,
        ];

        return view('admin.reports.future_treatments_report_data', compact('appointments', 'filters'));
    }
}
