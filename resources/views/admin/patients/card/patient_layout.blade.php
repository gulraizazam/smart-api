@extends('layouts.app')

@section('stylesheets')
    <link href="{{ url("metronic/assets/pages/css/profile.min.css") }}" rel="stylesheet" type="text/css" />
    @yield("patient_stylesheets")
@endsection

@section('content')
    <section class="content-header">
        <h1 class="page-title col-md-10">@lang('global.patients.title')</h1>
        <p class="col-md-2">
            <a href="{{ route('admin.patients.index') }}" class="btn btn-success pull-right">@lang('global.app_back')</a>
        </p>
    </section>

    <div class="clearfix"></div>

    <div class="row">
        <div class="col-md-12">
            <!-- BEGIN PROFILE SIDEBAR -->
            <div class="profile-sidebar">
                <!-- PORTLET MAIN -->
                <div class="portlet light profile-sidebar-portlet ">
                    <!-- SIDEBAR USERPIC -->
                    <div class="profile-userpic">
                        @if($patient->image_src)
                            <img src="{{asset('patient_image/')}}/{{$patient->image_src}}" class="img-responsive" alt="">
                        @else
                            <img src="{{asset('patient_image/imgenotfound.jpg')}}" class="img-responsive" alt="">
                        @endif
                    </div>
                    <!-- END SIDEBAR USERPIC -->
                    <!-- SIDEBAR USER TITLE -->
                    <div class="profile-usertitle">
                        <div class="profile-usertitle-name"> {{ $patient->name }} </div>
                    </div>
                    <!-- END SIDEBAR USER TITLE -->
                    <!-- SIDEBAR BUTTONS -->
                    {{--<div class="profile-userbuttons">--}}
                        {{--<button type="button" class="btn btn-circle green btn-sm">Follow</button>--}}
                        {{--<button type="button" class="btn btn-circle red btn-sm">Message</button>--}}
                    {{--</div>--}}
                    <!-- END SIDEBAR BUTTONS -->
                    <!-- SIDEBAR MENU -->
                    <div class="profile-usermenu">
                        @include('admin.patients.card.nav')
                    </div>
                    <!-- END MENU -->
                </div>
                <!-- END PORTLET MAIN -->
            </div>
            <!-- END BEGIN PROFILE SIDEBAR -->
            <!-- BEGIN PROFILE CONTENT -->
            <div class="profile-content">
                <div class="row">
                    <div class="col-md-12">
                        <div class="portlet light ">
                            @yield("patient_content")
                        </div>
                    </div>
                </div>
            </div>
            <!-- END PROFILE CONTENT -->
        </div>
    </div>
@stop
@section('javascript')
    @yield("patient_javascript")
@stop