<div class="modal fade" id="modal_add_interview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Add Interview Round</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-dismiss="modal">
                    <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 my-5">
                <form id="form_add_interview" method="post" action="{{ route('admin.hr.interviews.store') }}">
                    @csrf
                    <input type="hidden" name="candidate_id" value="{{ $candidate->id }}">
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <label class="required fw-bold fs-6 mb-2">Interview Type</label>
                            <select name="interview_type" class="form-control form-control-solid" required>
                                @foreach(\App\Enums\InterviewType::cases() as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="required fw-bold fs-6 mb-2">Interviewer</label>
                            <select name="interviewer_id" class="form-control form-control-solid select2" style="width:100%;" required>
                                <option value="">--- Select ---</option>
                                @foreach($interviewers as $interviewer)
                                    <option value="{{ $interviewer->id }}">{{ $interviewer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="required fw-bold fs-6 mb-2">Interview Date</label>
                            <input type="datetime-local" name="interview_date" class="form-control form-control-solid" required>
                        </div>
                    </div>

                    {{-- Scorecard --}}
                    <div class="border rounded p-4 mb-4 bg-light">
                        <label class="fw-bold fs-6 mb-3">Scorecard <span class="text-muted font-size-xs">(optional — fill after interview)</span></label>
                        <div class="row">
                            @foreach(['communication' => 'Communication', 'technical' => 'Technical', 'cultural_fit' => 'Cultural Fit', 'experience' => 'Experience', 'personality' => 'Personality'] as $key => $label)
                                <div class="col mb-2 text-center">
                                    <div class="font-size-sm text-muted mb-1">{{ $label }}</div>
                                    <div class="scorecard-stars" style="font-size:20px; cursor:pointer;">
                                        <input type="hidden" name="score_{{ $key }}" value="">
                                        @for($i = 1; $i <= 5; $i++)
                                            <i class="la la-star-o text-warning" data-value="{{ $i }}"></i>
                                        @endfor
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="fw-bold fs-6 mb-2">Verdict</label>
                            <select name="verdict" class="form-control form-control-solid">
                                <option value="">--- Select ---</option>
                                @foreach(\App\Enums\InterviewVerdict::cases() as $v)
                                    <option value="{{ $v->value }}">{{ $v->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="fw-bold fs-6 mb-2">Notes</label>
                            <input type="text" name="interview_notes" class="form-control form-control-solid" maxlength="150" placeholder="Feedback, next steps, etc.">
                        </div>
                    </div>

                    <hr>
                    <div class="text-center">
                        <button type="reset" class="btn btn-light me-3" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
