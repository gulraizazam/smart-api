@extends('admin.layouts.master')
@section('title', 'Bulk Allocate Leave Balances')
@section('content')

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'HRM', 'title' => 'Bulk Allocate'])

        <div class="d-flex flex-column-fluid">
            <div class="container">
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-users text-primary mr-2"></i>Bulk Allocate Leave Balances</h3>
                        </div>
                        <div class="card-toolbar">
                            <a href="{{ route('admin.hr.leave-balances.index') }}" class="btn btn-light btn-sm"><i class="la la-arrow-left"></i> Back</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="form_bulk_allocate">
                            @csrf
                            <input type="hidden" name="fiscal_year" value="{{ $fiscalYear }}">

                            <div class="row mb-5">
                                <div class="col-md-4 mb-3">
                                    <label class="required fw-bold fs-6 mb-2">Leave Type</label>
                                    <select name="leave_type_id" class="form-control select2" style="width:100%;" required>
                                        <option value="">— Select Type —</option>
                                        @foreach($leaveTypes as $lt)
                                            <option value="{{ $lt->id }}">{{ $lt->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="required fw-bold fs-6 mb-2">Days to Allocate</label>
                                    <input type="number" name="allocated" step="0.5" min="0" max="365" class="form-control" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="fw-bold fs-6 mb-2">Fiscal Year</label>
                                    <input type="text" class="form-control" value="{{ $fiscalYear }}" disabled>
                                </div>
                            </div>

                            <div class="row mb-5">
                                <div class="col-md-12">
                                    <label class="required fw-bold fs-6 mb-2">Select Employees</label>
                                    <div class="mb-2">
                                        <a href="javascript:void(0);" id="select-all-employees" class="text-primary font-size-sm mr-3">Select All</a>
                                        <a href="javascript:void(0);" id="deselect-all-employees" class="text-danger font-size-sm">Deselect All</a>
                                    </div>
                                    <select name="user_ids[]" class="form-control select2" multiple style="width:100%;" required>
                                        @foreach($employees as $emp)
                                            <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <p class="text-warning font-size-sm"><i class="la la-exclamation-triangle"></i> This will <strong>overwrite</strong> existing allocations for selected employees.</p>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary"><i class="la la-save"></i> Allocate to Selected Employees</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('js')
    <script>
        $(function () {
            $('.select2').select2();

            $('#select-all-employees').on('click', function () {
                $('select[name="user_ids[]"] option').prop('selected', true);
                $('select[name="user_ids[]"]').trigger('change');
            });

            $('#deselect-all-employees').on('click', function () {
                $('select[name="user_ids[]"]').val([]).trigger('change');
            });

            $('#form_bulk_allocate').on('submit', function (e) {
                e.preventDefault();
                if (!confirm('This will overwrite existing allocations. Continue?')) return;
                let form = $(this), btn = form.find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');
                $.ajax({
                    url: '{{ route("admin.hr.leave-balances.bulk-allocate.store") }}',
                    method: 'POST', data: form.serialize(),
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) { if (res.status) { toastr.success(res.message); } else { toastr.error(res.message); } },
                    error: function (xhr) { if (xhr.status === 422) { Object.values(xhr.responseJSON.errors).forEach(function (m) { toastr.error(m[0]); }); } else { toastr.error('Something went wrong.'); } },
                    complete: function () { btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right'); }
                });
            });
        });
    </script>
    @endpush

@endsection
