@extends('admin.layouts.master')
@section('title', 'Future Treatments Report')
@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Future Treatments Report'])
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
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <rect x="0" y="0" width="24" height="24" />
                                            <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13" rx="1.5" />
                                            <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8" rx="1.5" />
                                            <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero" />
                                            <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5" />
                                        </g>
                                    </svg>
                                    <!--end::Svg Icon-->
                                </span>
                            </span>
                            <h3 class="card-label">Future Treatments Report</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mt-2 mb-7">
                            <div class="row align-items-center">
                                <div class="col-lg-12 col-xl-12">
                                    <div class="row align-items-center">
                                        <div class="form-group col-md-4 sn-select @if($errors->has('centre_id')) has-error @endif" id="locations">
                                            {!! Form::label('centre_id', 'Centre:', ['class' => 'control-label']) !!}
                                            <select class="form-control select2" id="centre_id" name="centre_id">
                                                <option value="">All Centres</option>
                                                @foreach($locations as $location)
                                                <option value="{{$location->id}}">{{$location->name}}</option>
                                                @endforeach
                                            </select>
                                            <span id="centre_id_handler"></span>
                                        </div>

                                        <div class="form-group col-md-4 sn-select @if($errors->has('service_id')) has-error @endif" id="services">
                                            {!! Form::label('service_id', 'Service:', ['class' => 'control-label']) !!}
                                            <select class="form-control select2" id="service_id" name="service_id">
                                                <option value="">All Services</option>
                                                @foreach($services as $service)
                                                <option value="{{$service->id}}">{{$service->name}}</option>
                                                @endforeach
                                            </select>
                                            <span id="service_id_handler"></span>
                                        </div>

                                        <div class="form-group col-md-4 sn-select">
                                            {!! Form::label('load_report', '&nbsp;', ['class' => 'control-label']) !!}<br/>
                                            <a href="javascript:void(0);" onclick="loadFutureTreatmentsReport();" id="load_future_treatments_report"
                                               class="btn btn-success spinner-button">Load Report</a>
                                        </div>

                                        <div class="clear clearfix"></div>
                                        <div style="overflow: hidden; width: 100%;" id="future_treatments_content">

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->
    @include('admin.settings.edit')
    @push('datatable-js')
        <script src="{{asset('assets/js/pages/admin_settings/settings.js')}}"></script>
        <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>

        <script>
            function loadFutureTreatmentsReport() {
                var centreId = $('#centre_id').val();
                var serviceId = $('#service_id').val();

                $.ajax({
                    url: "{{ route('admin.reports.load_future_treatments_report') }}",
                    type: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        centre_id: centreId,
                        service_id: serviceId
                    },
                    beforeSend: function() {
                        $('#load_future_treatments_report').html('<i class="fa fa-spinner fa-spin"></i> Loading...');
                    },
                    success: function(response) {
                        $('#future_treatments_content').html(response);

                        // Initialize DataTable if not already initialized
                        if (!$.fn.DataTable.isDataTable('#future_treatments_table')) {
                            $('#future_treatments_table').DataTable({
                                dom: 'Bfrtip',
                                buttons: [
                                    'copy', 'csv', 'excel', 'pdf', 'print'
                                ],
                                pageLength: 50,
                                order: [[2, 'asc']]
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading report:', error);
                        alert('Error loading report. Please try again.');
                    },
                    complete: function() {
                        $('#load_future_treatments_report').html('Load Report');
                    }
                });
            }
        </script>
    @endpush
@endsection
