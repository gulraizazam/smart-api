@extends('admin.layouts.master')
@section('title', 'Scheduling Shifts')
@section('content')

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

        @include('admin.partials.breadcrumb', ['module' => 'Scheduling Shifts', 'title' => 'Schedule'])

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
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <rect x="0" y="0" width="24" height="24" />
                                            <path d="M12,22 C7.02943725,22 3,17.9705627 3,13 C3,8.02943725 7.02943725,4 12,4 C16.9705627,4 21,8.02943725 21,13 C21,17.9705627 16.9705627,22 12,22 Z" fill="#000000" opacity="0.3"/>
                                            <path d="M11.9630156,7.5 L12.0475062,7.5 C12.3043819,7.5 12.5194647,7.69464724 12.5450248,7.95024814 L13,12.5 L16.2480695,14.3560397 C16.403857,14.4450611 16.5,14.6107328 16.5,14.7901613 L16.5,15 C16.5,15.2761424 16.2761424,15.5 16,15.5 L12,15.5 L11.5,15.5 L11.5,15 L11.5,7.5 L11.9630156,7.5 Z" fill="#000000"/>
                                        </g>
                                    </svg>
                                </span>
                            </span>
                            <h3 class="card-label">Scheduling Shifts</h3>
                        </div>

                        <div class="card-toolbar">
                            @if(Gate::allows('resourcerotas_create'))
                                <a href="javascript:void(0);" onclick="createRota('{{ route('admin.resourcerotas.create') }}');" class="btn btn-primary" data-toggle="modal" data-target="#modal_add_resourcerotas">
                                    <i class="la la-plus"></i>
                                    Add Shift
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="card-body">
                        <!--begin::Filters-->
                        <div class="mb-7">
                            <div class="row align-items-end">
                                <div class="col-md-3">
                                    <label class="mb-2">Location <span class="text-danger">*</span></label>
                                    <select class="form-control select2" id="filter_location_id">
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="mb-2">Resource Type <span class="text-danger">*</span></label>
                                    <select class="form-control" id="filter_resource_type">
                                        <option value="2" selected>Doctor</option>
                                        <option value="1">Machine</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <button type="button" class="btn btn-icon btn-light mr-2" id="btn_prev_week">
                                            <i class="la la-angle-left"></i>
                                        </button>
                                        <span class="font-weight-bold font-size-lg" id="week_range_display">This week</span>
                                        <button type="button" class="btn btn-icon btn-light ml-2" id="btn_next_week">
                                            <i class="la la-angle-right"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-2 text-right">
                                    <button type="button" class="btn btn-light-primary" id="btn_today">Today</button>
                                </div>
                            </div>
                        </div>
                        <!--end::Filters-->

                        <!--begin::Schedule Calendar-->
                        <div class="schedule-calendar-wrapper">
                            <div class="table-responsive">
                                <table class="table table-bordered schedule-calendar" id="schedule_calendar">
                                    <thead>
                                        <tr class="bg-light">
                                            <th class="team-member-header" style="min-width: 180px;">
                                                <div class="font-weight-bold">Team member</div>
                                            </th>
                                            <th class="day-header" data-day="0"></th>
                                            <th class="day-header" data-day="1"></th>
                                            <th class="day-header" data-day="2"></th>
                                            <th class="day-header" data-day="3"></th>
                                            <th class="day-header" data-day="4"></th>
                                            <th class="day-header" data-day="5"></th>
                                            <th class="day-header" data-day="6"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="schedule_body">
                                        <tr>
                                            <td colspan="8" class="text-center py-10">
                                                <div class="spinner spinner-primary spinner-lg"></div>
                                                <div class="mt-3">Loading schedule...</div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!--end::Schedule Calendar-->
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->

    <div class="modal fade" id="modal_add_resourcerotas" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered rota-form-to" id="resourcerotas_add">
            @include('admin.resourcerotas.create')
        </div>
    </div>

    <div class="modal fade" id="modal_edit_resourcerotas" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered rota-form-to" id="resourcerotas_edit">
            @include('admin.resourcerotas.edit')
        </div>
    </div>

    @push('css')
    <style>
        .schedule-calendar {
            border-collapse: collapse;
        }
        .schedule-calendar th,
        .schedule-calendar td {
            vertical-align: middle;
            text-align: center;
            padding: 12px 8px;
            min-width: 120px;
        }
        .schedule-calendar .team-member-header {
            text-align: left;
        }
        .schedule-calendar .day-header {
            font-size: 13px;
        }
        .schedule-calendar .day-header .day-name {
            font-weight: 600;
            color: #3F4254;
        }
        .schedule-calendar .day-header .day-date {
            font-size: 12px;
            color: #7E8299;
        }
        .schedule-calendar .day-header .day-hours {
            font-size: 11px;
            color: #B5B5C3;
        }
        .team-member-cell {
            text-align: left !important;
        }
        .team-member-info {
            display: flex;
            align-items: center;
        }
        .team-member-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            color: #fff;
            margin-right: 10px;
        }
        .team-member-details {
            flex: 1;
        }
        .team-member-name {
            font-weight: 600;
            color: #3F4254;
            font-size: 13px;
        }
        .team-member-hours {
            font-size: 11px;
            color: #B5B5C3;
        }
        .shift-cell {
            padding: 8px 4px !important;
        }
        .shift-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            background-color: #E1F0FF;
            color: #3699FF;
        }
        .shift-badge.not-working {
            background-color: #F3F6F9;
            color: #B5B5C3;
        }
        .shift-badge.weekend {
            background-color: #FFF4DE;
            color: #FFA800;
        }
        .avatar-color-1 { background-color: #F64E60; }
        .avatar-color-2 { background-color: #3699FF; }
        .avatar-color-3 { background-color: #1BC5BD; }
        .avatar-color-4 { background-color: #8950FC; }
        .avatar-color-5 { background-color: #FFA800; }
        .avatar-color-6 { background-color: #6993FF; }
    </style>
    @endpush

    @push('js')
        <script src="{{asset('assets/js/pages/admin_settings/resourcerotas.js')}}"></script>
        <script src="{{asset('assets/js/pages/crud/forms/validation/admin_settings/resourcerotas.js')}}"></script>
        <script src="{{asset('assets/js/pages/admin_settings/schedule-calendar.js')}}?v={{ time() }}"></script>
    @endpush

@endsection
