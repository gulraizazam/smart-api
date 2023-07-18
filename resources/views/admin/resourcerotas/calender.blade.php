@extends('admin.layouts.master')
@section('title', 'Rota Calendar')
@section('content')

    @push('css')
        <link href="{{asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.css')}}" rel="stylesheet" type="text/css" />
    @endpush

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

        @include('admin.partials.breadcrumb', ['module' => 'Calender List', 'title' => 'Calender'])

        <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container full-page-view">
                <!--begin::Example-->
                <!--begin::Card-->
                <div class="card card-custom">
                    <div class="card-header">
                        <div class="card-title">
                            <h3 class="card-label">Calendar</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="kt_calendar"></div>
                    </div>
                </div>
                <!--end::Card-->

            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->


    <div class="modal fade" id="ajax_resourcerotas_calenderedit" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="resourcerotas_calenderedit">

            @include('admin.resourcerotas.calender-modal')

        </div>
        <!--end::Modal dialog-->
    </div>


    @push('js')

        <script>
            let calender_id = '{{request('id')}}';
        </script>

        <script src="{{asset('assets/js/pages/admin_settings/rota-calender.js?v=1')}}"></script>

        <script src="{{asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.js')}}"></script>

        <script src="{{asset('assets/js/pages/features/calendar/basic.js')}}"></script>

        <script src="{{asset('assets/js/pages/crud/forms/validation/admin_settings/rota-calender-validation.js?v=1.0')}}"></script>

    @endpush

@endsection
