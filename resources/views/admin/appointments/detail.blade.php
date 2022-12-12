@extends('layouts.app')

@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.appointments.title')</h1>
    <!-- END PAGE TITLE-->
@endsection

@section('content')
    <div class="portlet light bordered">
        <div class="portlet-title">
            <div class="caption font-green-sharp">
                <i class="icon-eye font-green-sharp"></i>
                <span class="caption-subject bold uppercase"> @lang('global.app_detail')</span>
            </div>
            <div class="actions">
                <a href="{{ route('admin.appointments.index') }}" class="btn dark pull-right">@lang('global.app_back')</a>
            </div>
        </div>
        <div class="portlet-body">
            <div class="box-body no-padding">
                <table class="table table-striped">
                    <tbody>
                    <tr>
                        <th>Patient Name</th>
                        <td>{{ $appointment->patient->name }}</td>
                        <th>Patient Phone</th>
                        <td>@if($appointment->patient->phone){{ \App\Helpers\GeneralFunctions::prepareNumber4Call($appointment->patient->phone) }}@else{{'N/A'}}@endif</td>
                    </tr>
                    <tr>
                        <th>Appointment Time</th>
                        <td>@if($appointment->scheduled_date){{ \Carbon\Carbon::parse($appointment->scheduled_date, null)->format('M j, y') . ' at ' . \Carbon\Carbon::parse($appointment->scheduled_time, null)->format('h:i A') }}@else{{'-'}}@endif</td>
                        <th>Doctor</th>
                        <td>@if($appointment->doctor_id){{ $appointment->doctor->name }}@else{{'N/A'}}@endif</td>
                    </tr>
                    <tr>
                        <th>City</th>
                        <td>@if($appointment->city_id){{ $appointment->city->name }}@else{{'N/A'}}@endif</td>
                        <th>Centre</th>
                        <td>@if($appointment->location_id){{ $appointment->location->name }}@else{{'N/A'}}@endif</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@stop

