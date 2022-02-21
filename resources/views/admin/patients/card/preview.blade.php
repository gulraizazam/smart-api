@extends('admin.patients.card.patient_layout')

@section('patient_stylesheets')

@endsection

@section('patient_content')
    <div class="portlet-title tabbable-line">
        <div class="caption caption-md">
            <i class="icon-globe theme-font hide"></i>
            <span class="caption-subject font-blue-madison bold uppercase">Profile</span>
        </div>
        <ul class="nav nav-tabs">
            <li class="@if(request()->segment(4) == 'preview') {{ 'active' }} @endif active">
                <a href="{{ route('admin.patients.preview',[$patient->id]) }}">Personal Info
                </a>
            </li>
            @if(Gate::allows('users_manage'))
                <li class="@if(request()->segment(4) == 'image') {{ 'active' }} @endif">
                    <a href="{{ route('admin.patients.imageurl',[$patient->id]) }}">Change Profile Picture
                    </a>
                </li>
            @endif
        </ul>
    </div>
    <div class="portlet-body">
        <div class="table-scrollable table-scrollable-borderless">
            <table class="table table-hover table-light">
                <tbody>
                <tr>
                    <th width="5%"> Name </th>
                    <td> {{ $patient->name }} </td>
                    <th width="5%"> Patient ID </th>
                    <td> {{ 'C-'.$patient->id }} </td>
                </tr>
                <tr>
                    <th> Phone </th>
                    <td> {{ \App\Helpers\GeneralFunctions::prepareNumber4Call($patient->phone) }} </td>
                    <th> Email </th>
                    <td> {{ $patient->email }} </td>
                </tr>
                <tr>
                    <th> Gender </th>
                    <td> {{ ($patient->gender) ? Config::get("constants.gender_array")[$patient->gender] : '' }} </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
@stop

@section('patient_javascript')

@stop