<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ACL;
use App\Http\Requests\Feedback\FeedbackReportRequest;
use App\Services\Feedback\FeedbackService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
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

        $locationIds = !empty($validated['centre_id'])
            ? array_values(array_filter(array_map('intval', (array) $validated['centre_id']), fn (int $id): bool => $id > 0))
            : [];
        if (empty($locationIds)) {
            $locationIds = array_map('intval', ACL::getUserCentres());
        }
        $doctorId = !empty($validated['doctor_id']) ? (int) $validated['doctor_id'] : null;
        $serviceId = !empty($validated['service_id']) ? (int) $validated['service_id'] : null;

        $result = $this->feedbackService->getReportData(
            locationIds: $locationIds,
            doctorId: $doctorId,
            serviceId: $serviceId,
            dateRange: $validated['date_range'],
        );

        return view('admin.reports.feedbackReport', compact('result'));
    }

    public function futureTreatmentsReport(): View
    {
        $data = $this->feedbackService->getFutureTreatmentsPageData();

        $locations = $data['locations'];
        $services = $data['services'];

        return view('admin.reports.future_treatments_report', compact('locations', 'services'));
    }

    public function loadFutureTreatmentsReport(Request $request): View
    {
        $centreIds = $request->input('centre_id');
        $centreIds = empty($centreIds)
            ? []
            : array_values(array_filter(array_map('intval', (array) $centreIds), fn (int $id): bool => $id > 0));
        if (empty($centreIds)) {
            $centreIds = array_map('intval', ACL::getUserCentres());
        }

        $result = $this->feedbackService->getFutureTreatmentsData(
            $centreIds,
            $request->input('service_id'),
        );

        $appointments = $result['appointments'];
        $filters = $result['filters'];

        return view('admin.reports.future_treatments_report_data', compact('appointments', 'filters'));
    }
}
