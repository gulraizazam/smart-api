@extends('admin.layouts.master')
@section('title', 'Leave Balances')
@section('content')

    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', ['module' => 'HRM', 'title' => 'Leave Balances'])

        <div class="d-flex flex-column-fluid">
            <div class="container">

                {{-- Allocate Balance Card --}}
                <div class="card card-custom mb-5">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-plus-circle text-primary mr-2"></i>Allocate Leave Balance</h3>
                        </div>
                        <div class="card-toolbar">
                            <a href="{{ route('admin.hr.leave-balances.bulk-allocate') }}" class="btn btn-info btn-sm mr-2"><i class="la la-users"></i> Bulk Allocate</a>
                            <a href="{{ route('admin.hr.leave-balances.export', ['fiscal_year' => $fiscalYear]) }}" class="btn btn-success btn-sm"><i class="la la-download"></i> Export CSV</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="form_allocate_balance" method="post" action="{{ route('admin.hr.leave-balances.allocate') }}">
                            @csrf
                            <input type="hidden" name="fiscal_year" value="{{ $fiscalYear }}">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="required fw-bold fs-6 mb-2">Employee</label>
                                    <select name="user_id" class="form-control select2" style="width:100%;" required>
                                        <option value="">— Select Employee —</option>
                                        @foreach($employees as $emp)
                                            <option value="{{ $emp->id }}">{{ $emp->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="required fw-bold fs-6 mb-2">Leave Type</label>
                                    <select name="leave_type_id" class="form-control select2" style="width:100%;" required>
                                        <option value="">— Select Type —</option>
                                        @foreach($leaveTypes as $lt)
                                            <option value="{{ $lt->id }}">{{ $lt->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="required fw-bold fs-6 mb-2">Days</label>
                                    <input type="number" name="allocated" step="0.5" min="0" max="365" class="form-control" required>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="fw-bold fs-6 mb-2">Fiscal Year</label>
                                    <input type="text" class="form-control" value="{{ $fiscalYear }}" disabled>
                                </div>
                                <div class="col-md-2 mb-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary btn-block">Allocate</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Balance Summary Table --}}
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <h3 class="card-label"><i class="la la-chart-bar text-primary mr-2"></i>Balance Summary — {{ $fiscalYear }}</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" id="balance-summary-table">
                            <p class="text-muted text-center py-5">Loading...</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    @push('js')
    <script>
        $(function () {
            $('.select2').select2();

            // Allocate
            $('#form_allocate_balance').on('submit', function (e) {
                e.preventDefault();
                let form = $(this), btn = form.find('button[type="submit"]');
                btn.attr('disabled', true).addClass('spinner spinner-white spinner-right');
                $.ajax({
                    url: form.attr('action'), method: 'POST', data: form.serialize(),
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) { if (res.status) { toastr.success(res.message); loadBalanceTable(); form[0].reset(); $('.select2').val('').trigger('change'); } else { toastr.error(res.message); } },
                    error: function (xhr) { if (xhr.status === 422) { Object.values(xhr.responseJSON.errors).forEach(function (m) { toastr.error(m[0]); }); } else { toastr.error('Something went wrong.'); } },
                    complete: function () { btn.attr('disabled', false).removeClass('spinner spinner-white spinner-right'); }
                });
            });

            function esc(str) {
                return $('<span>').text(str).html();
            }

            // Load balance table
            function loadBalanceTable() {
                $.ajax({
                    url: '{{ route("admin.api.hr.leave-balances.datatable") }}',
                    method: 'POST',
                    data: { fiscal_year: '{{ $fiscalYear }}' },
                    headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    success: function (res) {
                        if (!res.data) return;
                        let types = res.data.leave_types;
                        let employees = res.data.employees;

                        let html = '<table class="table table-head-custom table-vertical-center table-head-bg table-sm">';
                        html += '<thead><tr><th>Employee</th>';
                        types.forEach(function (t) {
                            html += '<th colspan="3" class="text-center">' + esc(t.name) + '</th>';
                        });
                        html += '</tr><tr><th></th>';
                        types.forEach(function () {
                            html += '<th class="text-center">Alloc</th><th class="text-center">Used</th><th class="text-center">Rem</th>';
                        });
                        html += '</tr></thead><tbody>';

                        employees.forEach(function (emp) {
                            html += '<tr><td>' + esc(emp.name) + '</td>';
                            emp.balances.forEach(function (b) {
                                let remClass = b.remaining < 0 ? 'text-danger font-weight-bold' : '';
                                html += '<td class="text-center">' + b.allocated + '</td>';
                                html += '<td class="text-center">' + b.used + '</td>';
                                html += '<td class="text-center ' + remClass + '">' + b.remaining + '</td>';
                            });
                            html += '</tr>';
                        });

                        if (employees.length === 0) {
                            html += '<tr><td colspan="' + (1 + types.length * 3) + '" class="text-center text-muted">No employees found.</td></tr>';
                        }

                        html += '</tbody></table>';
                        $('#balance-summary-table').html(html);
                    }
                });
            }

            loadBalanceTable();
        });
    </script>
    @endpush

@endsection
