@extends('admin.layouts.master')
@section('title', 'Recruitment')
@section('content')

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'HRM', 'title' => 'Recruitment'])

        <div class="d-flex flex-column-fluid">
            <div class="container-fluid px-5">
                <div class="row">

                    {{-- Left Panel: Candidate List --}}
                    <div class="col-lg-5 col-xl-4">
                        <div class="card card-custom">
                            <div class="card-header py-3">
                                <div class="card-title">
                                    <h3 class="card-label">Candidates</h3>
                                </div>
                                @can('hr_recruitment_create')
                                <div class="card-toolbar">
                                    <a href="javascript:void(0);" id="btn_add_candidate" class="btn btn-primary btn-sm"><i class="la la-plus p-0"></i></a>
                                </div>
                                @endcan
                            </div>
                            <div class="card-body py-3 px-4">
                                {{-- Search --}}
                                <div class="input-group input-group-sm mb-3">
                                    <input type="text" class="form-control" id="search_name" placeholder="Search by name...">
                                    <div class="input-group-append">
                                        <button class="btn btn-light" id="btn_reset_filters" type="button" title="Reset filters"><i class="la la-undo p-0"></i></button>
                                    </div>
                                </div>
                                {{-- Filters --}}
                                <div class="row mb-3">
                                    <div class="col-12 mb-2">
                                        <select class="form-control form-control-sm select2" id="search_status" style="width:100%;">
                                            <option value="">-- Status --</option>
                                            @foreach(\App\Enums\CandidateStatus::cases() as $s)
                                                <option value="{{ $s->value }}">{{ $s->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <select class="form-control form-control-sm select2" id="search_designation" style="width:100%;">
                                            <option value="">-- Designation --</option>
                                            @foreach($designations as $d)
                                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <select class="form-control form-control-sm select2" id="search_location" style="width:100%;">
                                            <option value="">-- Centre --</option>
                                            @foreach($locations as $loc)
                                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            {{-- Candidate List --}}
                            <div id="candidate_list" class="px-4 pb-4" style="max-height:calc(100vh - 340px);overflow-y:auto;">
                                <div class="text-center text-muted py-5">Loading...</div>
                            </div>
                        </div>
                    </div>

                    {{-- Right Panel: Detail / Form --}}
                    <div class="col-lg-7 col-xl-8">
                        <div class="card card-custom">
                            <div class="card-body" id="right_panel">
                                <div id="empty_state" class="text-center text-muted py-15">
                                    <i class="la la-user-plus icon-4x mb-4 d-block"></i>
                                    <p class="font-size-lg">Select a candidate or add a new one</p>
                                </div>
                                <div id="detail_content" style="display:none;"></div>
                                <div id="create_content" style="display:none;">
                                    {{-- Add Candidate Form --}}
                                    <div class="d-flex align-items-center justify-content-between mb-5">
                                        <h4 class="mb-0"><i class="la la-user-plus text-primary mr-2"></i>Add Candidate</h4>
                                        <a href="javascript:void(0);" id="btn_cancel_add" class="btn btn-sm btn-light"><i class="la la-times mr-1"></i>Cancel</a>
                                    </div>
                                    <form id="form_add_candidate" action="{{ route('admin.hr.recruitment.store') }}" enctype="multipart/form-data">
                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label class="font-weight-bold mb-1">Candidate Name <span class="text-danger">*</span></label>
                                                <input type="text" name="name" class="form-control" required>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label class="font-weight-bold mb-1">Email</label>
                                                <input type="email" name="email" class="form-control">
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label class="font-weight-bold mb-1">Phone</label>
                                                <input type="text" name="phone" class="form-control">
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label class="font-weight-bold mb-1">Designation <span class="text-danger">*</span></label>
                                                <select name="designation_id" class="form-control select2" style="width:100%;" required>
                                                    <option value="">--- Select ---</option>
                                                    @foreach($designations as $d)
                                                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label class="font-weight-bold mb-1">City <span class="text-danger">*</span></label>
                                                <select name="city_id" class="form-control select2" style="width:100%;" required>
                                                    <option value="">--- Select ---</option>
                                                    @foreach($cities as $c)
                                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label class="font-weight-bold mb-1">Centre <span class="text-danger">*</span></label>
                                                <select name="location_id" class="form-control select2" style="width:100%;" required>
                                                    <option value="">--- Select ---</option>
                                                    @foreach($locations as $loc)
                                                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label class="font-weight-bold mb-1">Upload CV</label>
                                                <input type="file" name="cv" class="form-control" accept=".pdf,.doc,.docx">
                                                <small class="text-muted">PDF, DOC, DOCX. Max 5MB.</small>
                                            </div>
                                            <div class="col-md-12 mb-4">
                                                <label class="font-weight-bold mb-1">Notes</label>
                                                <textarea name="notes" class="form-control" rows="2"></textarea>
                                            </div>
                                        </div>

                                        <hr>
                                        <h6 class="mb-3"><i class="la la-calendar text-primary mr-1"></i>Schedule Interview</h6>
                                        <div class="row">
                                            <div class="col-md-4 mb-4">
                                                <label class="font-weight-bold mb-1">Interview Type <span class="text-danger">*</span></label>
                                                <select name="interview_type" class="form-control" required>
                                                    <option value="">--- Select ---</option>
                                                    @foreach(\App\Enums\InterviewType::cases() as $type)
                                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-4">
                                                <label class="font-weight-bold mb-1">Interviewer <span class="text-danger">*</span></label>
                                                <select name="interviewer_id" class="form-control select2" style="width:100%;" required>
                                                    <option value="">--- Select ---</option>
                                                    @foreach($interviewers as $interviewer)
                                                        <option value="{{ $interviewer->id }}">{{ $interviewer->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-4">
                                                <label class="font-weight-bold mb-1">Date & Time <span class="text-danger">*</span></label>
                                                <input type="datetime-local" name="interview_date" class="form-control" required>
                                            </div>
                                        </div>

                                        <hr>
                                        <div class="text-right">
                                            <button type="submit" class="btn btn-primary">Add Candidate & Schedule Interview</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

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

    @push('js')
    <script>
        var activeCandidateId = null;
        var searchTimer = null;
        var rcCurrentPage = 1;
        var rcTotalPages = 1;
        var rcPerPage = 30;

        $(function () {
            $('.select2').select2();

            // Load candidate list
            loadCandidates();

            // Search with debounce
            $('#search_name').on('keyup', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () { rcCurrentPage = 1; loadCandidates(); }, 300);
            });

            // Filter changes
            $('#search_status, #search_designation, #search_location').on('change', function () {
                rcCurrentPage = 1;
                loadCandidates();
            });

            // Reset filters
            $('#btn_reset_filters').on('click', function () {
                $('#search_name').val('');
                $('#search_status, #search_designation, #search_location').val('').trigger('change');
                rcCurrentPage = 1;
                loadCandidates();
            });

            // Load more
            $(document).on('click', '#btn_load_more', function () {
                rcCurrentPage++;
                loadCandidates(true);
            });

            // Click candidate in list
            $(document).on('click', '.candidate-item', function () {
                var id = $(this).data('id');
                activeCandidateId = id;
                $('.candidate-item').removeClass('bg-light-primary');
                $(this).addClass('bg-light-primary');
                loadCandidateDetail(id);
            });

            // Add Candidate button
            $('#btn_add_candidate').on('click', function () {
                showPanel('create');
                activeCandidateId = null;
                $('.candidate-item').removeClass('bg-light-primary');
            });

            // Cancel Add
            $('#btn_cancel_add').on('click', function () {
                showPanel('empty');
            });

            // Submit Add Candidate
            $('#form_add_candidate').on('submit', function (e) {
                e.preventDefault();
                var form = $(this), btn = form.find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');
                var formData = new FormData(this);
                $.ajax({
                    url: form.attr('action'), method: 'POST', data: formData, processData: false, contentType: false,
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) {
                        if (res.status) {
                            toastr.success(res.message);
                            form[0].reset();
                            form.find('.select2').val('').trigger('change');
                            showPanel('empty');
                            loadCandidates();
                        } else { toastr.error(res.message); }
                    },
                    error: function (xhr) {
                        if (xhr.status === 422) { Object.values(xhr.responseJSON.errors).forEach(function (m) { toastr.error(m[0]); }); }
                        else { toastr.error('Something went wrong.'); }
                    },
                    complete: function () { btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right'); }
                });
            });

            // --- Detail panel event delegation ---

            // Edit candidate
            $(document).on('click', '#btn_edit_candidate', function () {
                $('#edit_form_wrapper').slideToggle(200);
                $('#edit_form_wrapper .detail-select2').select2();
            });
            $(document).on('click', '#btn_cancel_edit', function () {
                $('#edit_form_wrapper').slideUp(200);
            });
            $(document).on('submit', '#form_edit_candidate', function (e) {
                e.preventDefault();
                var form = $(this), btn = form.find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');
                var formData = new FormData(this);
                formData.append('_method', 'PUT');
                $.ajax({
                    url: form.data('url'), method: 'POST', data: formData, processData: false, contentType: false,
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) {
                        if (res.status) { toastr.success(res.message); loadCandidateDetail(activeCandidateId); loadCandidates(); }
                        else { toastr.error(res.message); }
                    },
                    error: function (xhr) {
                        if (xhr.status === 422) { Object.values(xhr.responseJSON.errors).forEach(function (m) { toastr.error(m[0]); }); }
                        else { toastr.error('Something went wrong.'); }
                    },
                    complete: function () { btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right'); }
                });
            });

            // Status update
            $(document).on('click', '#btn_save_status', function () {
                var btn = $(this), url = btn.data('url'), status = $('#candidate_status').val();
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');
                $.ajax({
                    url: url, method: 'PUT', data: { status: status },
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) {
                        if (res.status) { toastr.success(res.message); loadCandidateDetail(activeCandidateId); loadCandidates(); }
                        else { toastr.error(res.message); }
                    },
                    error: function () { toastr.error('Something went wrong.'); },
                    complete: function () { btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right'); }
                });
            });

            // Delete candidate
            $(document).on('click', '#btn_delete_candidate', function () {
                if (!confirm('Delete this candidate? This cannot be undone.')) return;
                var id = activeCandidateId;
                var url = '{{ route("admin.hr.recruitment.destroy", ":id") }}'.replace(':id', id);
                $.ajax({
                    url: url, method: 'DELETE',
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) {
                        if (res.status) { toastr.success(res.message); activeCandidateId = null; showPanel('empty'); loadCandidates(); }
                        else { toastr.error(res.message); }
                    },
                    error: function () { toastr.error('Something went wrong.'); }
                });
            });

            // Toggle interview form
            $(document).on('click', '#btn_toggle_interview_form', function () {
                $('#interview_form_wrapper').slideToggle(200);
                $('#interview_form_wrapper .detail-select2').select2();
            });
            $(document).on('click', '#btn_cancel_interview', function () {
                $('#interview_form_wrapper').slideUp(200);
            });

            // Scorecard star ratings
            $(document).on('click', '.scorecard-stars i', function () {
                var val = $(this).data('value');
                var wrapper = $(this).closest('.scorecard-stars');
                wrapper.find('input[type="hidden"]').val(val);
                wrapper.find('i').each(function () {
                    $(this).toggleClass('la-star', $(this).data('value') <= val);
                    $(this).toggleClass('la-star-o', $(this).data('value') > val);
                });
            });

            // Submit interview
            $(document).on('submit', '#form_add_interview', function (e) {
                e.preventDefault();
                var form = $(this), btn = form.find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');
                $.ajax({
                    url: form.data('url'), method: 'POST', data: form.serialize(),
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) {
                        if (res.status) { toastr.success(res.message); loadCandidateDetail(activeCandidateId); loadCandidates(); }
                        else { toastr.error(res.message); }
                    },
                    error: function (xhr) {
                        if (xhr.status === 422) { Object.values(xhr.responseJSON.errors).forEach(function (m) { toastr.error(m[0]); }); }
                        else { toastr.error('Something went wrong.'); }
                    },
                    complete: function () { btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right'); }
                });
            });

            // Show convert form
            $(document).on('click', '#btn_show_convert', function () {
                $('#convert_form_wrapper').slideToggle(200);
                $('#convert_form_wrapper .detail-select2').select2();
            });
            $(document).on('click', '#btn_cancel_convert', function () {
                $('#convert_form_wrapper').slideUp(200);
            });

            // Submit convert
            $(document).on('submit', '#form_convert_candidate', function (e) {
                e.preventDefault();
                if (!confirm('Convert this candidate to an employee? This will create a new user account.')) return;
                var form = $(this), btn = form.find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');
                $.ajax({
                    url: form.data('url'), method: 'POST', data: form.serialize(),
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) {
                        if (res.status) { toastr.success(res.message); loadCandidateDetail(activeCandidateId); loadCandidates(); }
                        else { toastr.error(res.message); }
                    },
                    error: function (xhr) {
                        if (xhr.status === 422) { Object.values(xhr.responseJSON.errors).forEach(function (m) { toastr.error(m[0]); }); }
                        else { toastr.error('Something went wrong.'); }
                    },
                    complete: function () { btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right'); }
                });
            });

            // CV Preview
            $(document).on('click', '.btn-preview-cv', function () {
                $('#cv-preview-name').text($(this).data('name'));
                $('#cv-preview-download').attr('href', $(this).data('download'));
                $('#cv-preview-iframe').attr('src', $(this).data('preview'));
                $('#modal_cv_preview').modal('show');
            });
            $('#modal_cv_preview').on('hidden.bs.modal', function () {
                $('#cv-preview-iframe').attr('src', '');
            });

            // Auto-select candidate from URL param
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('candidate')) {
                activeCandidateId = parseInt(urlParams.get('candidate'));
            }
        });

        function showPanel(which) {
            $('#empty_state, #detail_content, #create_content').hide();
            if (which === 'empty') $('#empty_state').show();
            else if (which === 'detail') $('#detail_content').show();
            else if (which === 'create') $('#create_content').show();
        }

        function loadCandidates(append) {
            var searchData = {
                name: $('#search_name').val() || '',
                status: $('#search_status').val() || '',
                designation_id: $('#search_designation').val() || '',
                location_id: $('#search_location').val() || '',
                filter: 'filter'
            };

            if (!append) {
                $('#candidate_list').html('<div class="text-center text-muted py-5">Loading...</div>');
            } else {
                $('#btn_load_more').attr('disabled', true).text('Loading...');
            }

            $.ajax({
                url: '{{ route("admin.api.hr.recruitment.datatable") }}',
                method: 'POST',
                data: {
                    query: { search: searchData },
                    pagination: { page: rcCurrentPage, perpage: rcPerPage },
                    sort: { field: 'created_at', sort: 'desc' }
                },
                headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                success: function (res) {
                    var html = '';
                    var badges = { scheduled: 'info', in_review: 'primary', shortlisted: 'primary', on_hold: 'warning', offer: 'success', hired: 'success', offer_declined: 'danger', rejected: 'danger', blacklisted: 'dark' };
                    rcTotalPages = res.meta ? res.meta.pages : 1;
                    var total = res.meta ? res.meta.total : 0;

                    if (res.data && res.data.length) {
                        res.data.forEach(function (c) {
                            var active = (activeCandidateId && c.id == activeCandidateId) ? ' bg-light-primary' : '';
                            html += '<div class="candidate-item d-flex align-items-center justify-content-between p-3 mb-2 rounded cursor-pointer border' + active + '" data-id="' + c.id + '" style="cursor:pointer;">';
                            html += '<div class="min-w-0 flex-grow-1">';
                            html += '<div class="font-weight-bold text-truncate">' + c.name + '</div>';
                            html += '<div class="text-muted font-size-sm text-truncate">' + c.designation + ' &middot; ' + c.created_at + '</div>';
                            html += '</div>';
                            html += '<div class="ml-3 text-right flex-shrink-0">';
                            html += '<span class="label label-sm label-light-' + (badges[c.status] || 'primary') + ' label-inline">' + c.status.charAt(0).toUpperCase() + c.status.slice(1) + '</span>';
                            if (c.has_cv) html += '<i class="la la-file-pdf text-danger ml-2" title="Has CV"></i>';
                            html += '</div>';
                            html += '</div>';
                        });
                    } else if (!append) {
                        html = '<div class="text-center text-muted py-5">No candidates found.</div>';
                    }

                    if (append) {
                        $('#btn_load_more').remove();
                        $('#candidate_list').append(html);
                    } else {
                        $('#candidate_list').html(html);
                    }

                    // Show load more if there are more pages
                    var loaded = rcCurrentPage * rcPerPage;
                    if (loaded < total) {
                        $('#candidate_list').append('<div class="text-center py-3"><button id="btn_load_more" class="btn btn-sm btn-light-primary btn-block">Load more (' + (total - loaded) + ' remaining)</button></div>');
                    }

                    // Auto-load detail if candidate was pre-selected
                    if (activeCandidateId && !append) {
                        var item = $('.candidate-item[data-id="' + activeCandidateId + '"]');
                        if (item.length) {
                            item.addClass('bg-light-primary');
                            loadCandidateDetail(activeCandidateId);
                        }
                    }
                },
                error: function () {
                    $('#candidate_list').html('<div class="text-center text-danger py-5">Failed to load candidates.</div>');
                }
            });
        }

        function loadCandidateDetail(id) {
            var url = '{{ route("admin.hr.recruitment.show", ":id") }}'.replace(':id', id);
            showPanel('detail');
            $('#detail_content').html('<div class="text-center py-10"><div class="spinner spinner-primary spinner-lg"></div></div>');

            $.ajax({
                url: url, method: 'GET',
                success: function (html) {
                    $('#detail_content').html(html);
                    $('#detail_content .detail-select2').select2();
                },
                error: function () {
                    $('#detail_content').html('<div class="text-center text-danger py-10">Failed to load candidate details.</div>');
                }
            });
        }
    </script>
    @endpush

@endsection
