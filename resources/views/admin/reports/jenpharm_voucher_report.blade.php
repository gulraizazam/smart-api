@extends('admin.layouts.master')
@section('title', 'Jenpharm Gift Voucher Report')
@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
<style>
    div#voucher_table_length {
        margin-left: 15px;
    }
    .bottom {
        margin-top: 3px;
        margin-left: 15px;
        text-align: right;
    }
</style>
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Jenpharm Gift Voucher Report'])
    <div class="d-flex flex-column-fluid">
        <div class="container">
            <div class="card card-custom">
                <div class="card-header py-3">
                    <div class="card-title">
                        <span class="card-icon">
                            <span class="svg-icon svg-icon-md svg-icon-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <rect x="0" y="0" width="24" height="24" />
                                        <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13" rx="1.5" />
                                        <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8" rx="1.5" />
                                        <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero" />
                                        <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5" />
                                    </g>
                                </svg>
                            </span>
                        </span>
                        <h3 class="card-label">Jenpharm Gift Voucher Report</h3>
                    </div>
                </div>
                <div class="card-body">
                    @if(!$discount)
                        <div class="alert alert-warning">
                            Discount "Jenpharm Gift Voucher" not found in the system.
                        </div>
                    @else
                        <div class="row mb-5">
                            <div class="col-md-4">
                                <div class="card card-custom bg-light-primary">
                                    <div class="card-body p-4">
                                        <h6 class="text-muted mb-1">Voucher Name</h6>
                                        <h4>{{ $discount->name }}</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card card-custom bg-light-success">
                                    <div class="card-body p-4">
                                        <h6 class="text-muted mb-1">Discount Type</h6>
                                        <h4>{{ $discount->type }} - {{ $discount->amount }}</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card card-custom bg-light-info">
                                    <div class="card-body p-4">
                                        <h6 class="text-muted mb-1">Total Plans Using This Voucher</h6>
                                        <h4>{{ $totalPlans }}</h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="voucher_table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Branch / Location</th>
                                        <th>Plans with Voucher Applied</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($locationData as $index => $row)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $row->location_name }}</td>
                                            <td>{{ $row->total_plans }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="font-weight-bold">
                                        <td colspan="2" class="text-right">Total</td>
                                        <td>{{ $totalPlans }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script>
    $(document).ready(function() {
        $('#voucher_table').DataTable({
            dom: 'Bfrtip',
            buttons: ['copy', 'csv', 'excel', 'print'],
            order: [[2, 'desc']],
            pageLength: 50,
        });
    });
</script>
@endsection
