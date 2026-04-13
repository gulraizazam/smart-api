<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\HR;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Designation;
use App\Models\RecruitmentCandidate;
use App\Services\HR\RecruitmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RecruitmentController extends Controller
{
    public function __construct(
        protected readonly RecruitmentService $service,
    ) {}

    public function index(): View
    {
        $accountId = Auth::user()->account_id;

        $designations = Designation::active()
            ->where('account_id', $accountId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $cities = \App\Models\Cities::where('account_id', $accountId)
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        $locations = \App\Models\Locations::where('account_id', $accountId)
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        $departments = Department::active()
            ->where('account_id', $accountId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $interviewers = \App\Models\User::where('account_id', $accountId)
            ->whereIn('user_type_id', [2, 5])
            ->where('active', 1)
            ->where('hr_managed', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        $roles = \Spatie\Permission\Models\Role::where('guard_name', 'web')->orderBy('name')->get(['id', 'name']);

        return view('admin.hr.recruitment.index', compact('designations', 'cities', 'locations', 'departments', 'interviewers', 'roles'));
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $accountId = $user->account_id;

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'designation_id' => ['required', "exists:designations,id,account_id,{$accountId}"],
                'city_id' => ['required', "exists:cities,id,account_id,{$accountId}"],
                'location_id' => ['required', "exists:locations,id,account_id,{$accountId}"],
                'notes' => ['nullable', 'string', 'max:2000'],
                'cv' => ['nullable', 'file', 'max:5120', 'mimes:pdf,doc,docx'],
                'interview_type' => ['required', 'in:ceo,director,hr'],
                'interviewer_id' => ['required', "exists:users,id,account_id,{$accountId}"],
                'interview_date' => ['required', 'date'],
            ]);

            $candidateData = collect($validated)->except('cv', 'interview_type', 'interviewer_id', 'interview_date')->toArray();
            $candidateData['account_id'] = $accountId;
            $candidateData['created_by'] = $user->id;
            $candidateData['status'] = 'scheduled';

            if ($request->hasFile('cv')) {
                $file = $request->file('cv');
                $candidateData['cv_file_path'] = $file->store("accounts/{$accountId}/hr/recruitment", 'r2');
            }

            $candidate = RecruitmentCandidate::create($candidateData);

            $candidate->interviews()->create([
                'interviewer_id' => $validated['interviewer_id'],
                'interview_date' => $validated['interview_date'],
                'interview_type' => $validated['interview_type'],
                'account_id' => $accountId,
            ]);

            return $this->successResponse('Candidate added and interview scheduled.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'RecruitmentController@store');
        }
    }

    public function show(RecruitmentCandidate $candidate)
    {
        abort_if($candidate->account_id !== Auth::user()->account_id, 403);

        $candidate->load([
            'designation',
            'city',
            'location',
            'interviews.interviewer',
            'convertedUser',
        ]);

        $accountId = Auth::user()->account_id;
        $interviewers = \App\Models\User::where('account_id', $accountId)
            ->whereIn('user_type_id', [2, 5])
            ->where('active', 1)
            ->where('hr_managed', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        $designations = Designation::active()->where('account_id', $accountId)->orderBy('name')->get(['id', 'name']);
        $cities = \App\Models\Cities::where('account_id', $accountId)->where('active', 1)->orderBy('name')->get(['id', 'name']);
        $locations = \App\Models\Locations::where('account_id', $accountId)->where('active', 1)->orderBy('name')->get(['id', 'name']);
        $departments = Department::active()->where('account_id', $accountId)->orderBy('name')->get(['id', 'name']);
        $roles = \Spatie\Permission\Models\Role::where('guard_name', 'web')->orderBy('name')->get(['id', 'name']);

        if (request()->ajax()) {
            return view('admin.hr.recruitment.partials.detail', compact('candidate', 'interviewers', 'designations', 'cities', 'locations', 'departments', 'roles'));
        }

        return redirect()->route('admin.hr.recruitment.index', ['candidate' => $candidate->id]);
    }

    public function update(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        try {
            $accountId = Auth::user()->account_id;
            abort_if($candidate->account_id !== $accountId, 403);

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'designation_id' => ['required', "exists:designations,id,account_id,{$accountId}"],
                'city_id' => ['required', "exists:cities,id,account_id,{$accountId}"],
                'location_id' => ['required', "exists:locations,id,account_id,{$accountId}"],
                'notes' => ['nullable', 'string', 'max:2000'],
                'cv' => ['nullable', 'file', 'max:5120', 'mimes:pdf,doc,docx'],
            ]);

            $data = collect($validated)->except('cv')->toArray();

            if ($request->hasFile('cv')) {
                $file = $request->file('cv');

                if ($candidate->cv_file_path) {
                    Storage::disk('r2')->delete($candidate->cv_file_path);
                }

                $data['cv_file_path'] = $file->store("accounts/{$accountId}/hr/recruitment", 'r2');
            }

            $candidate->update($data);

            return $this->successResponse('Candidate updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'RecruitmentController@update');
        }
    }

    public function previewCv(RecruitmentCandidate $candidate)
    {
        abort_if($candidate->account_id !== Auth::user()->account_id, 403);
        $disk = Storage::disk('r2');

        abort_unless($candidate->cv_file_path && $disk->exists($candidate->cv_file_path), 404);

        $fileName = $candidate->name . '_CV.' . pathinfo($candidate->cv_file_path, PATHINFO_EXTENSION);

        return response($disk->get($candidate->cv_file_path), 200, [
            'Content-Type' => $disk->mimeType($candidate->cv_file_path),
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    public function downloadCv(RecruitmentCandidate $candidate)
    {
        abort_if($candidate->account_id !== Auth::user()->account_id, 403);
        $disk = Storage::disk('r2');

        abort_unless($candidate->cv_file_path && $disk->exists($candidate->cv_file_path), 404);

        $fileName = $candidate->name . '_CV.' . pathinfo($candidate->cv_file_path, PATHINFO_EXTENSION);

        return response($disk->get($candidate->cv_file_path), 200, [
            'Content-Type' => $disk->mimeType($candidate->cv_file_path),
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    public function updateStatus(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        try {
            abort_if($candidate->account_id !== Auth::user()->account_id, 403);

            $validated = $request->validate([
                'status' => ['required', 'in:scheduled,in_review,shortlisted,on_hold,offer,hired,offer_declined,rejected,blacklisted'],
            ]);

            $candidate->update(['status' => $validated['status']]);

            return $this->successResponse('Candidate status updated to ' . ucfirst($validated['status']) . '.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'RecruitmentController@updateStatus');
        }
    }

    public function convert(RecruitmentCandidate $candidate): View
    {
        abort_if($candidate->account_id !== Auth::user()->account_id, 403);
        if ($candidate->converted_user_id) {
            abort(403, 'Candidate has already been converted.');
        }

        $candidate->load(['designation', 'city', 'location']);
        $accountId = Auth::user()->account_id;

        $departments = Department::active()
            ->where('account_id', $accountId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $roles = Role::where('guard_name', 'web')->orderBy('name')->get(['id', 'name']);

        return view('admin.hr.recruitment.convert', compact('candidate', 'departments', 'roles'));
    }

    public function storeConversion(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        try {
            $accountId = Auth::user()->account_id;
            abort_if($candidate->account_id !== $accountId, 403);

            if ($candidate->converted_user_id) {
                return $this->errorResponse('Candidate has already been converted.');
            }
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'password' => ['required', 'string', 'min:8'],
                'user_type_id' => ['required', 'in:2,5'],
                'department_id' => ['nullable', "exists:departments,id,account_id,{$accountId}"],
                'hire_date' => ['nullable', 'date'],
                'employment_type' => ['nullable', 'in:full_time,part_time,contract'],
                'salary' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
                'role_id' => ['nullable', 'exists:roles,id'],
            ]);

            $user = $this->service->convertToEmployee($candidate, $validated);

            return $this->successResponse('Candidate converted to employee successfully. User ID: ' . $user->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'RecruitmentController@storeConversion');
        }
    }

    public function destroy(RecruitmentCandidate $candidate): JsonResponse
    {
        try {
            abort_if($candidate->account_id !== Auth::user()->account_id, 403);
            $candidate->delete();

            return $this->successResponse('Candidate deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'RecruitmentController@destroy');
        }
    }
}
