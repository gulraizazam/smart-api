{{-- Candidate Detail Panel (loaded via AJAX into right panel) --}}
<div id="candidate-detail-inner" data-id="{{ $candidate->id }}">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-5">
        <div>
            <h4 class="mb-0">{{ $candidate->name }}</h4>
            <span class="text-muted font-size-sm">{{ $candidate->designation?->name ?? '---' }} &middot; {{ $candidate->location?->name ?? '---' }}</span>
        </div>
        <div class="d-flex align-items-center">
            <span class="label {{ $candidate->status->badgeClass() }} label-inline mr-3">{{ $candidate->status->label() }}</span>
            @can('hr_recruitment_edit')
                <a href="javascript:void(0);" class="btn btn-sm btn-light-primary btn-icon mr-1" id="btn_edit_candidate" title="Edit"><i class="la la-pencil"></i></a>
            @endcan
            @can('hr_recruitment_delete')
                <a href="javascript:void(0);" class="btn btn-sm btn-light-danger btn-icon" id="btn_delete_candidate" title="Delete"><i class="la la-trash"></i></a>
            @endcan
        </div>
    </div>

    {{-- Status Change --}}
    @if(!$candidate->converted_user_id)
        @canany(['hr_recruitment_status_update', 'hr_recruitment_convert'])
            <div class="d-flex align-items-center mb-5 p-3 bg-light rounded">
                @can('hr_recruitment_status_update')
                    <span class="font-weight-bold text-muted mr-3">Status:</span>
                    <select id="candidate_status" class="form-control form-control-sm mr-2" style="width:140px;">
                        @foreach(\App\Enums\CandidateStatus::cases() as $s)
                            <option value="{{ $s->value }}" {{ $candidate->status === $s ? 'selected' : '' }}>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                    <button type="button" id="btn_save_status" class="btn btn-sm btn-primary" data-url="{{ route('admin.hr.recruitment.update-status', $candidate) }}">Update</button>
                @endcan
                @can('hr_recruitment_convert')
                    @if($candidate->status === \App\Enums\CandidateStatus::Hired)
                        <button type="button" id="btn_show_convert" class="btn btn-sm btn-success ml-auto"><i class="la la-user-plus mr-1"></i>Convert to Employee</button>
                    @endif
                @endcan
            </div>
        @endcanany
    @else
        <div class="mb-5 p-3 bg-light-success rounded">
            <i class="la la-check-circle text-success mr-1"></i>
            Converted to employee: <strong>{{ $candidate->convertedUser?->name }}</strong>
        </div>
    @endif

    {{-- Info --}}
    <div class="row mb-5">
        <div class="col-6">
            <table class="table table-borderless table-sm mb-0">
                <tr><td class="font-weight-bold text-muted" style="width:100px">Email</td><td>{{ $candidate->email ?? '---' }}</td></tr>
                <tr><td class="font-weight-bold text-muted">Phone</td><td>{{ $candidate->phone ?? '---' }}</td></tr>
                <tr><td class="font-weight-bold text-muted">City</td><td>{{ $candidate->city?->name ?? '---' }}</td></tr>
            </table>
        </div>
        <div class="col-6">
            <table class="table table-borderless table-sm mb-0">
                <tr><td class="font-weight-bold text-muted" style="width:100px">Centre</td><td>{{ $candidate->location?->name ?? '---' }}</td></tr>
                <tr><td class="font-weight-bold text-muted">Created</td><td>{{ $candidate->created_at->format('d M Y') }}</td></tr>
                <tr>
                    <td class="font-weight-bold text-muted">CV</td>
                    <td>
                        @if($candidate->cv_file_path)
                            @php $cvExt = strtolower(pathinfo($candidate->cv_file_path, PATHINFO_EXTENSION)); @endphp
                            <a href="javascript:void(0);" class="btn-preview-cv mr-2" title="Preview CV"
                               data-preview="{{ route('admin.hr.recruitment.preview-cv', $candidate) }}"
                               data-download="{{ route('admin.hr.recruitment.download-cv', $candidate) }}"
                               data-name="{{ $candidate->name }}_CV.{{ $cvExt }}">
                                <i class="la la-eye text-primary"></i>
                                <span class="text-primary">View</span>
                            </a>
                            <a href="{{ route('admin.hr.recruitment.download-cv', $candidate) }}" title="Download CV">
                                <i class="la la-download text-success"></i>
                                <span class="text-success">Download</span>
                            </a>
                        @else
                            <span class="text-muted">---</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    </div>

    @if($candidate->notes)
        <div class="mb-5 p-3 bg-light-primary rounded">
            <strong class="text-muted d-block mb-1">Notes</strong>
            {{ $candidate->notes }}
        </div>
    @endif

    <hr class="my-5">

    {{-- Interview History --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h5 class="mb-0"><i class="la la-comments text-primary mr-1"></i>Interviews ({{ $candidate->interviews->count() }})</h5>
        @can('hr_recruitment_interview_manage')
            <a href="javascript:void(0);" id="btn_toggle_interview_form" class="btn btn-sm btn-light-primary"><i class="la la-plus mr-1"></i>Add Round</a>
        @endcan
    </div>

    {{-- Inline Interview Form (hidden by default) --}}
    @can('hr_recruitment_interview_manage')
    <div id="interview_form_wrapper" class="mb-5" style="display:none;">
        <div class="border rounded p-4 bg-light">
            <form id="form_add_interview" data-url="{{ route('admin.hr.interviews.store') }}">
                <input type="hidden" name="candidate_id" value="{{ $candidate->id }}">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Interview Type <span class="text-danger">*</span></label>
                        <select name="interview_type" class="form-control form-control-sm" required>
                            <option value="">--- Select ---</option>
                            @foreach(\App\Enums\InterviewType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Interviewer <span class="text-danger">*</span></label>
                        <select name="interviewer_id" class="form-control form-control-sm detail-select2" style="width:100%;" required>
                            <option value="">--- Select ---</option>
                            @foreach($interviewers as $interviewer)
                                <option value="{{ $interviewer->id }}">{{ $interviewer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Date <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="interview_date" class="form-control form-control-sm" required>
                    </div>
                </div>

                {{-- Scorecard --}}
                <div class="border rounded p-3 mb-3 bg-white">
                    <label class="font-weight-bold font-size-sm mb-2 d-block">Scorecard <span class="text-muted font-weight-normal">(1-5 each)</span></label>
                    <div class="row">
                        @foreach(['score_communication' => 'Communication', 'score_technical' => 'Technical Knowledge', 'score_cultural_fit' => 'Cultural Fit', 'score_experience' => 'Experience Relevance', 'score_personality' => 'Personality & Demeanor'] as $field => $label)
                        <div class="col mb-2" style="min-width:120px;">
                            <label class="font-size-xs text-muted d-block mb-1">{{ $label }}</label>
                            <div class="scorecard-stars" data-field="{{ $field }}" style="font-size:18px;cursor:pointer;">
                                <input type="hidden" name="{{ $field }}" value="">
                                @for($i = 1; $i <= 5; $i++)
                                    <i class="la la-star-o text-warning" data-value="{{ $i }}"></i>
                                @endfor
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Verdict</label>
                        <select name="verdict" class="form-control form-control-sm">
                            <option value="">--- Select ---</option>
                            @foreach(\App\Enums\InterviewVerdict::cases() as $v)
                                <option value="{{ $v->value }}">{{ $v->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Notes</label>
                        <input type="text" name="interview_notes" class="form-control form-control-sm" placeholder="Feedback, next steps, etc." maxlength="150">
                    </div>
                </div>
                <div class="text-right">
                    <button type="button" id="btn_cancel_interview" class="btn btn-sm btn-light mr-2">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Interview</button>
                </div>
            </form>
        </div>
    </div>
    @endcan

    {{-- Interview Timeline --}}
    @forelse($candidate->interviews as $index => $interview)
        <div class="d-flex mb-4">
            <div class="mr-3 text-center" style="min-width:40px;">
                <span class="label label-light-primary label-circle font-weight-bold" style="width:32px;height:32px;line-height:32px;">{{ $candidate->interviews->count() - $index }}</span>
            </div>
            <div class="flex-grow-1 border rounded p-3">
                <div class="d-flex justify-content-between mb-1">
                    <div>
                        <span class="font-weight-bold">{{ $interview->interviewer?->name ?? '---' }}</span>
                        @if($interview->interview_type)
                            <span class="label label-light-primary label-inline font-size-xs ml-2">{{ $interview->interview_type->label() }}</span>
                        @endif
                    </div>
                    <span class="text-muted font-size-sm">{{ $interview->interview_date->format('d M Y, h:i A') }}</span>
                </div>

                {{-- Scorecard display --}}
                @php
                    $scores = [
                        'Communication' => $interview->score_communication,
                        'Technical' => $interview->score_technical,
                        'Cultural Fit' => $interview->score_cultural_fit,
                        'Experience' => $interview->score_experience,
                        'Personality' => $interview->score_personality,
                    ];
                    $hasScores = collect($scores)->filter()->isNotEmpty();
                @endphp
                @if($hasScores)
                    <div class="d-flex flex-wrap mt-2 mb-1" style="gap:12px;">
                        @foreach($scores as $label => $score)
                            @if($score)
                                <div class="text-center">
                                    <div class="font-size-xs text-muted">{{ $label }}</div>
                                    <div>
                                        @for($i = 1; $i <= 5; $i++)
                                            <i class="la la-star{{ $i <= $score ? '' : '-o' }} text-warning" style="font-size:12px;"></i>
                                        @endfor
                                    </div>
                                </div>
                            @endif
                        @endforeach
                        @if($interview->average_score)
                            <div class="text-center">
                                <div class="font-size-xs text-muted">Average</div>
                                <div class="font-weight-bolder text-primary">{{ $interview->average_score }}/5</div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Verdict --}}
                <div class="d-flex align-items-center mt-1" style="gap:8px;">
                    @if($interview->verdict)
                        <span class="label {{ $interview->verdict->badgeClass() }} label-inline font-size-xs">{{ $interview->verdict->label() }}</span>
                    @endif
                </div>

                @if($interview->interview_notes)
                    <div class="text-muted font-size-sm mt-2">{{ $interview->interview_notes }}</div>
                @endif
            </div>
        </div>
    @empty
        <div class="text-center text-muted py-4">
            <i class="la la-comments icon-2x d-block mb-2"></i>
            No interview rounds yet.
        </div>
    @endforelse

    {{-- Convert to Employee Form (hidden by default) --}}
    @can('hr_recruitment_convert')
    @if($candidate->status === \App\Enums\CandidateStatus::Hired && !$candidate->converted_user_id)
    <div id="convert_form_wrapper" style="display:none;">
        <hr class="my-5">
        <h5 class="mb-4"><i class="la la-user-plus text-success mr-1"></i>Convert to Employee</h5>
        <div class="border rounded p-4 bg-light">
            <form id="form_convert_candidate" data-url="{{ route('admin.hr.recruitment.convert.store', $candidate) }}">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $candidate->name }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Email</label>
                        <input type="email" name="email" class="form-control form-control-sm" value="{{ $candidate->email }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Phone</label>
                        <input type="text" name="phone" class="form-control form-control-sm" value="{{ $candidate->phone }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">User Type <span class="text-danger">*</span></label>
                        <select name="user_type_id" class="form-control form-control-sm" required>
                            <option value="2">Application User</option>
                            <option value="5">Practitioner</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Department</label>
                        <select name="department_id" class="form-control form-control-sm detail-select2" style="width:100%;">
                            <option value="">--- Select ---</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Hire Date</label>
                        <input type="date" name="hire_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Employment Type</label>
                        <select name="employment_type" class="form-control form-control-sm">
                            <option value="">--- Select ---</option>
                            @foreach(\App\Enums\EmploymentType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Salary</label>
                        <input type="number" name="salary" class="form-control form-control-sm" min="0" step="0.01">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control form-control-sm" minlength="8" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Role</label>
                        <select name="role_id" class="form-control form-control-sm detail-select2" style="width:100%;">
                            <option value="">--- Select ---</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="text-right mt-2">
                    <button type="button" id="btn_cancel_convert" class="btn btn-sm btn-light mr-2">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success"><i class="la la-check mr-1"></i>Convert</button>
                </div>
            </form>
        </div>
    </div>
    @endif
    @endcan

    {{-- Edit Form (hidden by default) --}}
    @can('hr_recruitment_edit')
    <div id="edit_form_wrapper" style="display:none;">
        <div class="border rounded p-4 bg-light">
            <h5 class="mb-4">Edit Candidate</h5>
            <form id="form_edit_candidate" data-url="{{ route('admin.hr.recruitment.update', $candidate) }}" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $candidate->name }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Email</label>
                        <input type="email" name="email" class="form-control form-control-sm" value="{{ $candidate->email }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Phone</label>
                        <input type="text" name="phone" class="form-control form-control-sm" value="{{ $candidate->phone }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Designation <span class="text-danger">*</span></label>
                        <select name="designation_id" class="form-control form-control-sm detail-select2" style="width:100%;" required>
                            <option value="">--- Select ---</option>
                            @foreach($designations as $d)
                                <option value="{{ $d->id }}" {{ $candidate->designation_id == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">City <span class="text-danger">*</span></label>
                        <select name="city_id" class="form-control form-control-sm detail-select2" style="width:100%;" required>
                            <option value="">--- Select ---</option>
                            @foreach($cities as $c)
                                <option value="{{ $c->id }}" {{ $candidate->city_id == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Centre <span class="text-danger">*</span></label>
                        <select name="location_id" class="form-control form-control-sm detail-select2" style="width:100%;" required>
                            <option value="">--- Select ---</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}" {{ $candidate->location_id == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Replace CV</label>
                        <input type="file" name="cv" class="form-control form-control-sm" accept=".pdf,.doc,.docx">
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="font-weight-bold font-size-sm mb-1">Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2">{{ $candidate->notes }}</textarea>
                    </div>
                </div>
                <div class="text-right">
                    <button type="button" id="btn_cancel_edit" class="btn btn-sm btn-light mr-2">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    @endcan
</div>
