<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\HR;

use App\Http\Controllers\Controller;
use App\Services\HR\RecruitmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class RecruitmentDatatableController extends Controller
{
    public function __construct(
        protected readonly RecruitmentService $service,
    ) {}

    public function datatable(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('hr_recruitment_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = Auth::user()->account_id;
            $datatableData = $this->service->getDatatableData($request->all(), $accountId);

            [$orderBy, $order] = getSortBy($request);
            [$displayLength, $displayStart, $pages, $page] = getPaginationElement($request, $datatableData['total']);

            $sortColumn = match ($orderBy) {
                'name' => 'recruitment_candidates.name',
                'designation' => 'designations.name',
                'city' => 'cities.name',
                'location' => 'locations.name',
                'status' => 'recruitment_candidates.status',
                default => 'recruitment_candidates.created_at',
            };

            $records = $datatableData['query']
                ->with(['interviews' => fn ($q) => $q->with('interviewer:id,name')->latest('interview_date')->limit(1)])
                ->withCount('interviews')
                ->limit($displayLength)
                ->offset($displayStart)
                ->orderBy($sortColumn, $order ?: 'desc')
                ->get();

            $data = $records->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'designation' => $c->designation_name,
                'city' => $c->city_name ?? 'â€”',
                'location' => $c->location_name ?? 'â€”',
                'status' => $c->status,
                'has_cv' => (bool) $c->cv_file_path,
                'created_at' => $c->created_at->format('d M Y'),
                'interviews_count' => $c->interviews_count,
                'last_interviewer' => $c->interviews->first()?->interviewer?->name ?? 'â€”',
                'last_type' => $c->interviews->first()?->interview_type?->label() ?? 'â€”',
                'last_verdict' => $c->interviews->first()?->verdict?->label() ?? 'â€”',
            ]);

            return response()->json([
                'data' => $data,
                'permissions' => [
                    'view' => Gate::allows('hr_recruitment_view'),
                    'create' => Gate::allows('hr_recruitment_create'),
                    'edit' => Gate::allows('hr_recruitment_edit'),
                    'status_update' => Gate::allows('hr_recruitment_status_update'),
                    'interview_manage' => Gate::allows('hr_recruitment_interview_manage'),
                    'convert' => Gate::allows('hr_recruitment_convert'),
                    'delete' => Gate::allows('hr_recruitment_delete'),
                ],
                'active_filters' => $datatableData['active_filters'],
                'filter_values' => [
                    'statuses' => collect(\App\Enums\CandidateStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
                ],
                'meta' => [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => $displayLength,
                    'total' => $datatableData['total'],
                    'sort' => $order,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'RecruitmentDatatableController');
        }
    }
}
