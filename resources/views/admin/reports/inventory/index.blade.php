@extends('admin.layouts.master')
@section('title', 'Inventory Report')
@section('content')
    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        @include('admin.partials.breadcrumb', [
            'module' => 'Inventory Report',
            'title' => 'Inventory Report',
        ])
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
                                    <!--begin::Svg Icon | path:assets/media/svg/icons/Shopping/Chart-bar1.svg-->
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                        width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <rect x="0" y="0" width="24" height="24" />
                                            <rect fill="#000000" opacity="0.3" x="12" y="4" width="3"
                                                height="13" rx="1.5" />
                                            <rect fill="#000000" opacity="0.3" x="7" y="9" width="3"
                                                height="8" rx="1.5" />
                                            <path
                                                d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z"
                                                fill="#000000" fill-rule="nonzero" />
                                            <rect fill="#000000" opacity="0.3" x="17" y="11" width="3"
                                                height="6" rx="1.5" />
                                        </g>
                                    </svg>
                                    <!--end::Svg Icon-->
                                </span>
                            </span>
                            <h3 class="card-label">Inventory Report</h3>
                        </div>
                    </div>

                    <div class="card-body">
                        <!--begin::Search Form-->
                        <form action="" id="search_inventory_report_form">
                            <div class="mt-2 mb-7">
                                <input type="hidden" class="form-control filter-field" id="search_location_type" name="location_type">

                                <div class="row mb-6">
                                    <div class="form-group col-md-3 " id="report_type_div">
                                        {!! Form::label('report_type', 'Report Type:', ['class' => 'control-label']) !!}
                                        <select class="form-control" id="report_types" name="report_type">
                                            <option value="">Select Report</option>
                                            <option value="stock_report">Stock Report</option>
                                            <option value="sales_report">Sales Report</option>
                                            <option value="doctor_sales_report">Doctor Wise Sales Report</option>
                                        </select>
                                        @error('report_type')
                                        <div class="alert alert-danger">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-lg-3 mb-lg-0 mb-6">
                                        <label>Product Name:</label>
                                        <input type="text" class="form-control" name="name" id="search_product_name" placeholder="Product Name">
                                    </div>
                                    <div class="col-lg-3 mb-lg-0 mb-6">
                                        <label>Location:</label>
                                        <select class="form-control filter-field select2" name="location" id="search_location">
                                        </select>
                                    </div>
                                    <div class="col-lg-3 mb-lg-0 mb-6 @if ($errors->has('date_range')) has-error @endif">
                                        {!! Form::label('date_range', 'Created at:', ['class' => 'control-label']) !!}
                                        <div class="input-group">
                                            {!! Form::text('date_range', null, [
                                                'id' => 'date_range',
                                                'class' => 'form-control',
                                                'autocomplete' => 'off',
                                                'placeholder' => 'Select Date Range',
                                            ]) !!}
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-10">
                                    <div class="form-group col-md-2 sn-select @if($errors->has('group_id')) has-error @endif">
                                            {!! Form::label('load_report', '&nbsp;', ['class' => 'control-label']) !!}<br/>
                                            <a href="javascript:void(0);" onclick="loadInventoryReport($(this));" id="load_inv_report"
                                               class="btn btn-success spinner-button">Load Report</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <!--end::Search Form-->

                        <!--begin: Datatable-->
                        <div id="datatable_stock_report">
                            <table class="table table-bordered" style="width:100%">
                                <thead>
                                    <th>id</th>
                                    <th>Product</th>
                                    <th>Location</th>
                                    <th>Purchase Quantity</th>
                                    <th>Sale Quantity</th>
                                    <th>Transfer Product Quantity</th>
                                    <th>Current Stock Qty</th>
                                    <th>Purchase Values</th>
                                    <th>Current Stock Sell Value</th>
                                </thead>
                                <tbody id="stock_table_body"></tbody>
                            </table>
                            <table class="table table-bordered" style="width:30%; float: right">
                                <tbody id="stock_table_total"></tbody>
                            </table>
                        </div>
                        <!--end: Datatable-->
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->

    @push('datatable-js')
        <script src="{{ asset('assets/js/pages/inventory_report.js') }}"></script>
    @endpush

@endsection
