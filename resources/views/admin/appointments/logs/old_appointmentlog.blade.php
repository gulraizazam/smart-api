@inject('request', 'Illuminate\Http\Request')
@inject('Auth', 'Auth')
@inject('filters', 'App\Helpers\Filters')
@extends('layouts.app')

@section('stylesheets')
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <link href="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css'}}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/datatables.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css') }}"
          rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}"
          rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/pages/css/invoice.min.css') }}" rel="stylesheet" type="text/css"/>
    <!-- END PAGE LEVEL PLUGINS -->
@stop

@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.invoices.title')</h1>
    <!-- END PAGE TITLE-->
@endsection

@section('content')
    <!-- Begin: Demo Datatable 1 -->
    <div class="portlet light portlet-fit portlet-datatable bordered">
        <div class="portlet-title">
            <div class="caption font-green-sharp">
                <i class="fa fa-history font-green-sharp"></i>
                <span class="caption-subject bold uppercase"> @lang('global.app_log')</span>
            </div>
            <div class="actions">
                <a href="{{ route('admin.appointments.index') }}" class="btn dark pull-right">@lang('global.app_back')</a>
            </div>
        </div>
        <div class="portlet-body">
            <div class="portlet light bordered">
                <div class="portlet-title">
                    <div class="caption">
                        <span class="caption-subject font-green-sharp bold uppercase">Appointment ID {{$id}}</span>
                    </div>
                    @if (Gate::allows('appointments_log_excel'))
                        <div class="actions">
                            <a href="{{ route('admin.appointments.viewlog', [ $id, 'excel'] ) }}" class="btn green pull-right"> @lang('global.app_excel') </a>
                        </div>
                    @endif
                </div>
                <div class="portlet-body table-wrapper" style="overflow: auto;">

                    @if(count($data))
                        @php $count = 1; @endphp
                        <table id="table" class="table">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Action</th>
                                <th>Patient Name</th>
                                <th>Phone</th>
                                <th>Scheduled At</th>
                                <th>Doctor</th>
                                @if ($appointment->appointment_type_id===config('constants.appointment_type_service'))
                                    <th>Resource</th>
                                @endif
                                <th>Region</th>
                                <th>City</th>
                                <th>Centre</th>
                                <th>Service</th>
                                <th>Parent Status</th>
                                <th>Child Status</th>
                                <th>Type</th>
                                <th>Created At</th>
                                <th>Created By</th>
                                <th>Updated By</th>
                                <th>Rescheduled By</th>
                                <th>Message</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($data as $log)
                                <tr>
                                    <td> {{ $count++ }}</td>
                                    <td> {{ $log['action'] }}</td>
                                    <td> {{ isset($log['name']) ? $log['name'] : '-' }}</td>
                                    <td> {{ isset( $log['phone']) ? \App\Helpers\GeneralFunctions::prepareNumber4Call($log['phone']) : '-' }}</td>
                                    @if (isset($log['scheduled_date']) && isset($log['scheduled_time']))
                                        <td> {{ \Carbon\Carbon::parse($log['scheduled_date'], null)->format('M j, Y') . ' at ' . \Carbon\Carbon::parse($log['scheduled_time'], null)->format('h:i A') }}</td>
                                    @elseif (isset($log['scheduled_time']))
                                        <td> {{ \Carbon\Carbon::parse($log['scheduled_time'], null)->format('h:i A') }}</td>
                                    @elseif (isset($log['scheduled_date']))
                                        <td> {{ \Carbon\Carbon::parse($log['scheduled_date'], null)->format('M j, Y') }}</td>
                                    @else
                                        <td> - </td>
                                    @endif
                                    <td> {{ isset($log['doctor_id']) ? $log['doctor_id'] : '-' }}</td>
                                    @if ($appointment->appointment_type_id===config('constants.appointment_type_service'))
                                        <td> {{ isset($log['resource_id']) ? $log['resource_id'] : '-' }} </td>
                                    @endif
                                    <td> {{ isset($log['region_id']) ? $log['region_id'] : '-' }}</td>
                                    <td> {{ isset($log['city_id']) ? $log['city_id'] : '-' }}</td>
                                    <td> {{ isset($log['location_id']) ? $log['location_id'] : '-' }}</td>
                                    <td> {{ isset($log['service_id']) ? $log['service_id'] :'-' }}</td>
                                    <td> {{ isset($log['base_appointment_status_id']) ? $log['base_appointment_status_id'] : '-' }}</td>
                                    <td> {{ isset($log['appointment_status_id']) ? $log['appointment_status_id'] : '-' }}</td>
                                    <td> {{ isset($log['appointment_type_id']) ? $log['appointment_type_id'] : '-' }}</td>
                                    <td> {{ isset($log['created_at']) ? \Carbon\Carbon::parse($log['created_at'])->format('F j,Y h:i A') : '-' }}</td>
                                    <td> {{ isset($log['created_by']) ? $log['created_by'] : '-' }}</td>
                                    <td> {{ isset($log['converted_by']) ? $log['converted_by'] : '-' }}</td>
                                    <td> {{ isset($log['updated_by']) ? $log['updated_by'] : '-' }}</td>
                                    <td> {{ isset($log['send_message']) ? ($log['send_message'] == 1 ) ? 'Sent' : 'Not Sent' : '-'  }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @else
                        <tr>
                            <td colspan="4">No Appointment log found.</td>
                        </tr>
                    @endif

                </div>
            </div>
        </div>
        <!-- End: Demo Datatable 1 -->
        @stop

        @section('javascript')
            <!-- BEGIN PAGE LEVEL PLUGINS -->
            <script src="{{ url('metronic/assets/global/plugins/datatables/datatables.min.js') }}"
                    type="text/javascript"></script>
            <script src="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js') }}"
                    type="text/javascript"></script>
            <script src="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"
                    type="text/javascript"></script>
            <!-- END PAGE LEVEL PLUGINS -->
            <!-- BEGIN PAGE LEVEL SCRIPTS -->
            <script src="{{ url('metronic/assets/global/plugins/select2/js/select2.full.min.js') }}"
                    type="text/javascript"></script>
            <script src="{{ url('metronic/assets/global/scripts/datatable.js') }}" type="text/javascript"></script>
            <script src="{{ url('js/admin/invoices/datatable.js') }}" type="text/javascript"></script>
            <!-- END PAGE LEVEL SCRIPTS -->
            <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}"
                    type="text/javascript"></script>
            <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}"
                    type="text/javascript"></script>
            <script src="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'}}"
                    type="text/javascript"></script>
            <script src="{{ url('js/admin/users/ajaxbaseselect2.js') }}" type="text/javascript"></script>

@endsection