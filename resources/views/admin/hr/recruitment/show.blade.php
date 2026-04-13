@extends('admin.layouts.master')
@section('title', $candidate->name . ' — Candidate')
@section('content')

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

        @include('admin.partials.breadcrumb', ['module' => 'HRM', 'title' => $candidate->name])

        <div class="d-flex flex-column-fluid">
            <div class="container">

                {{-- Top Info Card --}}
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-user text-primary mr-2"></i>{{ $candidate->name }}</h3>
                        </div>
                        <div class="card-toolbar">
                            @can('hr_recruitment_edit')
                                <a href="javascript:void(0);" class="btn btn-sm btn-light-primary mr-2" data-toggle="modal" data-target="#modal_edit_candidate">
                                    <i class="la la-pencil"></i> Edit
                                </a>
                                @if(!$candidate->converted_user_id)
                                    <form id="form_update_status" class="d-inline-flex align-items-center">
                                        <select id="candidate_status" class="form-control form-control-sm mr-2" style="width: 160px;">
                                            @foreach(\App\Enums\CandidateStatus::cases() as $s)
                                                <option value="{{ $s->value }}" {{ $candidate->status === $s ? 'selected' : '' }}>{{ $s->label() }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" id="btn_save_status" class="btn btn-sm btn-primary">Save</button>
                                    </form>
                                @endif
                            @endcan
                            @can('hr_recruitment_edit')
                                @if($candidate->status === \App\Enums\CandidateStatus::Hired && !$candidate->converted_user_id)
                                    <a href="{{ route('admin.hr.recruitment.convert', $candidate) }}" class="btn btn-sm btn-success ml-2">
                                        <i class="la la-user-plus"></i> Convert to Employee
                                    </a>
                                @endif
                            @endcan
                            @if($candidate->converted_user_id)
                                <span class="label label-light-success label-inline font-weight-bold ml-2">
                                    Converted &rarr; {{ $candidate->convertedUser?->name }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless table-sm mb-0">
                                    <tr><td class="font-weight-bold text-muted" style="width:140px">Designation</td><td>{{ $candidate->designation?->name ?? '---' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">City</td><td>{{ $candidate->city?->name ?? '---' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Branch / Centre</td><td>{{ $candidate->location?->name ?? '---' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Created</td><td>{{ $candidate->created_at->format('d M Y') }}</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless table-sm mb-0">
                                    <tr><td class="font-weight-bold text-muted" style="width:140px">Email</td><td>{{ $candidate->email ?? '---' }}</td></tr>
                                    <tr><td class="font-weight-bold text-muted">Phone</td><td>{{ $candidate->phone ?? '---' }}</td></tr>
                                    <tr>
                                        <td class="font-weight-bold text-muted">Status</td>
                                        <td><span class="label {{ $candidate->status->badgeClass() }} label-inline">{{ $candidate->status->label() }}</span></td>
                                    </tr>
                                    <tr>
                                        <td class="font-weight-bold text-muted">CV</td>
                                        <td>
                                            @if($candidate->cv_file_path)
                                                @php $cvExt = strtolower(pathinfo($candidate->cv_file_path, PATHINFO_EXTENSION)); @endphp
                                                <a href="javascript:void(0);" class="btn-preview-cv" title="Preview CV"
                                                   data-preview="{{ route('admin.hr.recruitment.preview-cv', $candidate) }}"
                                                   data-download="{{ route('admin.hr.recruitment.download-cv', $candidate) }}"
                                                   data-name="{{ $candidate->name }}_CV.{{ $cvExt }}">
                                                    <i class="la la-file-{{ $cvExt === 'pdf' ? 'pdf text-danger' : 'alt text-primary' }}" style="font-size:1.5rem;"></i>
                                                    <span class="text-primary ml-1">View CV</span>
                                                </a>
                                            @else
                                                <span class="text-muted">Not uploaded</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        @if($candidate->notes)
                            <div class="mt-4 p-4 bg-light-primary rounded">
                                <strong class="text-muted d-block mb-1">Notes</strong>
                                {{ $candidate->notes }}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Interview History --}}
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-comments text-primary mr-2"></i>Interview History</h3>
                        </div>
                        @can('hr_recruitment_create')
                            <div class="card-toolbar">
                                <a href="javascript:void(0);" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modal_add_interview">
                                    <i class="la la-plus"></i> Add Interview Round
                                </a>
                            </div>
                        @endcan
                    </div>
                    <div class="card-body">
                        @forelse($candidate->interviews as $index => $interview)
                            <div class="timeline timeline-3">
                                <div class="timeline-item pb-5 {{ !$loop->last ? 'border-left border-left-2 border-light ml-3 pl-5' : 'ml-3 pl-5' }}">
                                    <div class="d-flex align-items-start mb-2">
                                        <span class="label label-lg label-light-primary label-inline font-weight-bold mr-3">Round {{ $candidate->interviews->count() - $index }}</span>
                                        @if($interview->interview_type)
                                            <span class="label label-light-info label-inline font-weight-bold mr-3">{{ $interview->interview_type->label() }}</span>
                                        @endif
                                        <span class="text-muted font-size-sm">{{ $interview->interview_date->format('d M Y, h:i A') }}</span>
                                    </div>
                                    <div class="bg-light rounded p-4">
                                        <div class="row mb-2">
                                            <div class="col-md-4">
                                                <strong class="text-muted">Interviewer:</strong>
                                                <span class="ml-1">{{ $interview->interviewer?->name ?? '---' }}</span>
                                            </div>
                                            <div class="col-md-4">
                                                <strong class="text-muted">Verdict:</strong>
                                                @if($interview->verdict)
                                                    <span class="label {{ $interview->verdict->badgeClass() }} label-inline ml-1">{{ $interview->verdict->label() }}</span>
                                                @else
                                                    <span class="text-muted ml-1">---</span>
                                                @endif
                                            </div>
                                            <div class="col-md-4">
                                                <strong class="text-muted">Avg Score:</strong>
                                                <span class="ml-1 font-weight-bold">{{ $interview->average_score ? $interview->average_score . '/5' : '---' }}</span>
                                            </div>
                                        </div>
                                        @if($interview->interview_notes)
                                            <div class="mt-3 pt-3 border-top">
                                                <strong class="text-muted d-block mb-1">Notes:</strong>
                                                {{ $interview->interview_notes }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-5">
                                <i class="la la-comments icon-3x mb-3 d-block"></i>
                                No interview rounds recorded yet.
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Actions --}}
                <div class="d-flex justify-content-between mb-10">
                    <a href="{{ route('admin.hr.recruitment.index') }}" class="btn btn-light">
                        <i class="la la-arrow-left"></i> Back to Candidates
                    </a>
                    @can('hr_recruitment_delete')
                        <button type="button" id="btn_delete_candidate" class="btn btn-danger">
                            <i class="la la-trash"></i> Delete Candidate
                        </button>
                    @endcan
                </div>

            </div>
        </div>
    </div>

    {{-- CV Preview Modal --}}
    <div class="modal fade" id="modal_cv_preview" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-3" style="background:#F3F6F9;border-bottom:2px solid #E4E6EF;">
                    <h5 class="modal-title font-weight-bolder"><i class="la la-file text-primary mr-2"></i><span id="cv-preview-name">CV Preview</span></h5>
                    <div>
                        <a id="cv-preview-download" href="#" class="btn btn-sm btn-light-primary mr-2"><i class="la la-download mr-1"></i>Download</a>
                        <button type="button" class="close ml-2" data-dismiss="modal" aria-label="Close"><i class="la la-times"></i></button>
                    </div>
                </div>
                <div class="modal-body p-0">
                    <iframe id="cv-preview-iframe" src="" style="width:100%;height:75vh;border:none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    @include('admin.hr.recruitment.partials.edit-modal', ['candidate' => $candidate, 'designations' => $designations, 'cities' => $cities, 'locations' => $locations])
    @include('admin.hr.recruitment.partials.interview-modal', ['candidate' => $candidate, 'interviewers' => $interviewers])

    @push('js')
    <script>
        $(function () {
            $('.select2').select2();

            // Update Status
            $('#btn_save_status').on('click', function () {
                let btn = $(this);
                let newStatus = $('#candidate_status').val();
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');

                $.ajax({
                    url: '{{ route("admin.hr.recruitment.update-status", $candidate) }}',
                    method: 'PUT',
                    data: { status: newStatus },
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) {
                        if (res.status) {
                            toastr.success(res.message);
                            location.reload();
                        } else {
                            toastr.error(res.message);
                        }
                    },
                    error: function (xhr) {
                        if (xhr.status === 422) {
                            Object.values(xhr.responseJSON.errors).forEach(function (m) { toastr.error(m[0]); });
                        } else {
                            toastr.error('Something went wrong.');
                        }
                    },
                    complete: function () {
                        btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right');
                    }
                });
            });

            // Add Interview Round
            $('#form_add_interview').on('submit', function (e) {
                e.preventDefault();
                let form = $(this);
                let btn = form.find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');

                $.ajax({
                    url: form.attr('action'),
                    method: 'POST',
                    data: form.serialize(),
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) {
                        if (res.status) {
                            toastr.success(res.message);
                            location.reload();
                        } else {
                            toastr.error(res.message);
                        }
                    },
                    error: function (xhr) {
                        if (xhr.status === 422) {
                            Object.values(xhr.responseJSON.errors).forEach(function (m) { toastr.error(m[0]); });
                        } else {
                            toastr.error('Something went wrong.');
                        }
                    },
                    complete: function () {
                        btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right');
                    }
                });
            });

            // Scorecard Star Ratings
            $(document).on('click', '.scorecard-stars i', function () {
                let val = $(this).data('value');
                let wrapper = $(this).closest('.scorecard-stars');
                wrapper.find('input[type="hidden"]').val(val);
                wrapper.find('i').each(function () {
                    $(this).toggleClass('la-star', $(this).data('value') <= val);
                    $(this).toggleClass('la-star-o', $(this).data('value') > val);
                });
            });

            // Edit Candidate
            $('#form_edit_candidate').on('submit', function (e) {
                e.preventDefault();
                let form = $(this), btn = form.find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');
                let formData = new FormData(this);
                $.ajax({
                    url: form.attr('action'), method: 'POST', data: formData, processData: false, contentType: false,
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) { if (res.status) { toastr.success(res.message); location.reload(); } else { toastr.error(res.message); } },
                    error: function (xhr) { if (xhr.status === 422) { Object.values(xhr.responseJSON.errors).forEach(function (m) { toastr.error(m[0]); }); } else { toastr.error('Something went wrong.'); } },
                    complete: function () { btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right'); }
                });
            });

            // Preview CV in Modal
            $(document).on('click', '.btn-preview-cv', function () {
                var previewUrl = $(this).data('preview');
                var downloadUrl = $(this).data('download');
                var fileName = $(this).data('name');

                $('#cv-preview-name').text(fileName);
                $('#cv-preview-download').attr('href', downloadUrl);
                $('#cv-preview-iframe').attr('src', previewUrl);
                $('#modal_cv_preview').modal('show');
            });

            $('#modal_cv_preview').on('hidden.bs.modal', function () {
                $('#cv-preview-iframe').attr('src', '');
            });

            // Delete Candidate
            $('#btn_delete_candidate').on('click', function () {
                if (!confirm('Are you sure you want to delete this candidate? This action cannot be undone.')) return;
                let btn = $(this);
                btn.attr('disabled', true);

                $.ajax({
                    url: '{{ route("admin.hr.recruitment.destroy", $candidate) }}',
                    method: 'DELETE',
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) {
                        if (res.status) {
                            toastr.success(res.message);
                            window.location.href = '{{ route("admin.hr.recruitment.index") }}';
                        } else {
                            toastr.error(res.message);
                        }
                    },
                    error: function () {
                        toastr.error('Something went wrong.');
                    },
                    complete: function () {
                        btn.attr('disabled', false);
                    }
                });
            });
        });
    </script>
    @endpush

@endsection
