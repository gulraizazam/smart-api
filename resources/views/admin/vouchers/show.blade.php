@extends('admin.layouts.master')
@section('title', 'View Voucher Usage')
@section('content')

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

        @include('admin.partials.breadcrumb', ['module' => 'Vouchers', 'title' => 'View Patient Voucher Usage'])

        <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">

                <!--begin::Card-->
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon">
                                <span class="svg-icon svg-icon-md svg-icon-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
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
                            <h3 class="card-label">Voucher Usage Details: {{ $user->name }}</h3>
                        </div>

                        <div class="card-toolbar">
                            <a href="{{ route('admin.vouchers.index') }}" class="btn btn-secondary">
                                <i class="la la-arrow-left"></i>
                                Back to List
                            </a>
                        </div>
                    </div>

                    <div class="card-body">

                        <!-- Voucher & Patient Information -->
                        <div class="row mb-5">
                            <div class="col-md-12">
                                <div class="card card-custom bg-light">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <strong>Patient Name:</strong><br>
                                                {{ $user->name }}
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Voucher Type:</strong><br>
                                                {{ $voucher->name }}
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Total Amount:</strong><br>
                                                {{ number_format($userVoucher->total_amount, 2) }}
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Remaining Amount:</strong><br>
                                                {{ number_format($userVoucher->amount, 2) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h4 class="mb-4">Services & Packages Where This Voucher Was Used</h4>

                        @if(count($voucherUsageData) > 0)
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Package ID</th>
                                            
                                           
                                            <th>Service Name</th>
                                           
                                            <th>Voucher Amount Used</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($voucherUsageData as $index => $usage)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $usage['package_id'] ?? 'N/A' }}</td>
                                          
                                            <td>{{ $usage['service_name'] }}</td>
                                           
                                            <td>
                                                @if($usage['discount_type'] == 'Percentage')
                                                    {{ number_format($usage['discount_price'], 2) }}%
                                                @else
                                                    {{ number_format($usage['discount_price'], 2) }}
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-5">
                                <div class="alert alert-info">
                                    <strong>Total Records:</strong> {{ count($voucherUsageData) }} service(s) using this voucher
                                </div>
                            </div>
                        @else
                            <div class="alert alert-warning">
                                <i class="la la-info-circle"></i>
                                This voucher has not been used in any packages or services yet.
                            </div>
                        @endif

                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->

@endsection
