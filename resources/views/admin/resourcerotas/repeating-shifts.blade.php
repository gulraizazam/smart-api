@extends('admin.layouts.master')
@section('title', 'Set Repeating Shifts')
@section('content')

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

        <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container-fluid py-6">
                
                <!--begin::Header-->
                <div class="mb-8">
                    <h1 class="page-header-title" id="page_title">Set Repeating Shifts</h1>
                    <p class="page-header-subtitle">Set weekly, biweekly or custom shifts. Changes saved will apply to all upcoming shifts for the selected period. <a href="javascript:void(0);">Learn more</a></p>
                </div>
                <!--end::Header-->

                <div class="row">
                    <!--begin::Left Column - Settings-->
                    <div class="col-lg-4 col-md-5">
                        <!--begin::Location Card-->
                        <div class="card location-card mb-5">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="location-icon mr-4">
                                        <i class="la la-map-marker-alt"></i>
                                    </div>
                                    <div>
                                        <div class="location-name" id="location_name">Location</div>
                                        <div class="location-address" id="location_address">Address</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--end::Location Card-->

                        <!--begin::Settings Card-->
                        <div class="card settings-card mb-5">
                            <div class="card-body">
                                <div class="form-group mb-5">
                                    <label class="form-label-custom">Schedule type</label>
                                    <select class="form-control form-control-custom" id="schedule_type">
                                        <option value="every_week">Every week</option>
                                        <option value="every_2_weeks">Every 2 weeks</option>
                                        <option value="every_3_weeks">Every 3 weeks</option>
                                        <option value="every_4_weeks">Every 4 weeks</option>
                                    </select>
                                </div>

                                <div class="form-group mb-5">
                                    <label class="form-label-custom">Start date</label>
                                    <input type="text" class="form-control form-control-custom" id="schedule_start_date" name="start_date" readonly>
                                </div>

                                <div class="form-group mb-0">
                                    <label class="form-label-custom">End date</label>
                                    <input type="text" class="form-control form-control-custom" id="schedule_end_date" name="end_date" readonly>
                                </div>
                            </div>
                        </div>
                        <!--end::Settings Card-->

                        <!--begin::Info Card-->
                        <div class="card info-card">
                            <div class="card-body py-4">
                                <div class="d-flex align-items-start">
                                    <i class="la la-info-circle info-icon mr-3" style="margin-top: 2px;"></i>
                                    <span class="info-text">Team members will not be scheduled on business closed periods.</span>
                                </div>
                            </div>
                        </div>
                        <!--end::Info Card-->
                    </div>
                    <!--end::Left Column-->

                    <!--begin::Right Column - Weekly Schedule-->
                    <div class="col-lg-8 col-md-7">
                        <div class="card weekly-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-5">
                                    <div>
                                        <h5 class="weekly-title mb-1">Weekly</h5>
                                        <span class="weekly-hours" id="total_hours_display">0 hours total</span>
                                    </div>
                                </div>

                                <!--begin::Days Schedule-->
                                <div id="days_schedule_container">
                                    @foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $index => $day)
                                    <div class="day-schedule-row {{ $index < 6 ? 'border-bottom' : '' }}" data-day="{{ strtolower($day) }}">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <label class="d-flex align-items-center cursor-pointer mb-0">
                                                    <span class="day-checkbox mr-3">
                                                        <input type="checkbox" class="day-enabled" {{ $index < 6 ? 'checked' : '' }}>
                                                        <span class="checkbox-box"></span>
                                                    </span>
                                                    <span class="day-name">{{ $day }}</span>
                                                </label>
                                                <div class="day-hours-label day-hours">{{ $index < 6 ? '9h' : '' }}</div>
                                            </div>
                                            <div class="col-md-10">
                                                <div class="day-shifts-container {{ $index >= 6 ? 'd-none' : '' }}">
                                                    <div class="shift-time-row d-flex align-items-center mb-2">
                                                        <select class="form-control time-select shift-start mr-2">
                                                            <!-- Options populated by JS -->
                                                        </select>
                                                        <span class="time-separator mx-2">to</span>
                                                        <select class="form-control time-select shift-end mr-3">
                                                            <!-- Options populated by JS -->
                                                        </select>
                                                        <button type="button" class="btn-add-time add-shift-time mr-2" title="Add shift">
                                                            <i class="la la-plus"></i>
                                                        </button>
                                                        <button type="button" class="btn-remove-time remove-shift-time" title="Remove" style="display: none;">
                                                            <i class="la la-trash"></i>
                                                        </button>
                                                        <button type="button" class="btn-copy-all copy-to-all-days ml-2" title="Copy to all days">
                                                            <i class="la la-copy"></i> Copy to all
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="not-working-label {{ $index < 6 ? 'd-none' : '' }}">Not working</div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                <!--end::Days Schedule-->
                            </div>
                        </div>
                    </div>
                    <!--end::Right Column-->
                </div>

                <!--begin::Action Buttons-->
                <div class="d-flex justify-content-end mt-8 mb-4">
                    <a href="{{ route('admin.resourcerotas.schedule') }}" class="btn btn-light btn-lg mr-3 px-6">Cancel</a>
                    <button type="button" class="btn btn-primary btn-lg px-6" id="btn_save_repeating_shifts">Save</button>
                </div>
                <!--end::Action Buttons-->

            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->

    @push('css')
    <style>
        /* Page Header */
        .page-header-title {
            font-size: 24px;
            font-weight: 600;
            color: #181C32;
            font-family: 'Poppins', sans-serif;
            margin-bottom: 8px;
        }
        .page-header-subtitle {
            font-size: 13px;
            color: #7E8299;
            font-family: 'Poppins', sans-serif;
        }
        .page-header-subtitle a {
            color: #3699FF;
            text-decoration: none;
        }
        .page-header-subtitle a:hover {
            text-decoration: underline;
        }

        /* Left Column Cards */
        .settings-card {
            background: #F8F9FC;
            border: none;
            border-radius: 12px;
            box-shadow: none;
        }
        .settings-card .card-body {
            padding: 20px;
        }
        .location-card {
            background: #F8F9FC;
            border: none;
            border-radius: 12px;
        }
        .location-icon {
            width: 45px;
            height: 45px;
            background: #EEE5FF;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .location-icon i {
            color: #8950FC;
            font-size: 22px;
        }
        .location-name {
            font-size: 14px;
            font-weight: 600;
            color: #181C32;
            font-family: 'Poppins', sans-serif;
        }
        .location-address {
            font-size: 12px;
            color: #B5B5C3;
            font-family: 'Poppins', sans-serif;
        }

        /* Form Labels */
        .form-label-custom {
            font-size: 13px;
            font-weight: 500;
            color: #3F4254;
            font-family: 'Poppins', sans-serif;
            margin-bottom: 8px;
        }

        /* Form Controls */
        .form-control-custom {
            border: 1px solid #E4E6EF;
            border-radius: 8px;
            font-size: 13px;
            color: #3F4254;
            padding: 10px 14px;
            background-color: #ffffff;
            font-family: 'Poppins', sans-serif;
            height: 44px;
        }
        .form-control-custom:focus {
            border-color: #3699FF;
            box-shadow: none;
        }
        .settings-card .datepicker {
            height: 44px !important;
        }

        /* Info Card */
        .info-card {
            background: #FFF8DD;
            border: none;
            border-radius: 12px;
        }
        .info-card .info-icon {
            color: #FFA800;
            font-size: 18px;
        }
        .info-card .info-text {
            font-size: 12px;
            color: #7E8299;
            font-family: 'Poppins', sans-serif;
        }

        /* Right Column - Weekly Card */
        .weekly-card {
            background: #ffffff;
            border: 1px solid #EFF2F5;
            border-radius: 12px;
            box-shadow: 0 0 20px 0 rgba(76, 87, 125, 0.02);
        }
        .weekly-card .card-body {
            padding: 24px;
        }
        .weekly-title {
            font-size: 16px;
            font-weight: 600;
            color: #181C32;
            font-family: 'Poppins', sans-serif;
        }
        .weekly-hours {
            font-size: 13px;
            color: #B5B5C3;
            font-family: 'Poppins', sans-serif;
        }

        /* Day Schedule Row */
        .day-schedule-row {
            transition: background-color 0.2s;
            padding: 16px 0;
        }
        .day-schedule-row:hover {
            background-color: #FAFBFC;
        }
        .day-name {
            font-size: 14px;
            font-weight: 500;
            color: #181C32;
            font-family: 'Poppins', sans-serif;
        }
        .day-hours-label {
            font-size: 12px;
            color: #B5B5C3;
            font-family: 'Poppins', sans-serif;
            margin-left: 35px;
        }
        .not-working-label {
            font-size: 13px;
            color: #B5B5C3;
            font-family: 'Poppins', sans-serif;
        }

        /* Checkbox Styling */
        .day-checkbox {
            position: relative;
            display: inline-block;
        }
        .day-checkbox input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        .day-checkbox .checkbox-box {
            display: inline-block;
            width: 20px;
            height: 20px;
            background-color: #fff;
            border: 2px solid #E4E6EF;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .day-checkbox input:checked ~ .checkbox-box {
            background-color: #3699FF;
            border-color: #3699FF;
        }
        .day-checkbox input:checked ~ .checkbox-box:after {
            content: '';
            position: absolute;
            left: 6px;
            top: 2px;
            width: 6px;
            height: 11px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        /* Time Dropdowns */
        .time-select {
            border: 1px solid #E4E6EF;
            border-radius: 6px;
            font-size: 13px;
            color: #3F4254;
            padding: 8px 12px;
            background-color: #ffffff;
            font-family: 'Poppins', sans-serif;
            min-width: 110px;
        }
        .time-select:focus {
            border-color: #3699FF;
            box-shadow: none;
        }
        .time-separator {
            font-size: 13px;
            color: #B5B5C3;
            font-family: 'Poppins', sans-serif;
        }

        /* Action Buttons */
        .btn-add-time {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            background-color: #E1F0FF;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-add-time i {
            color: #3699FF;
            font-size: 14px;
        }
        .btn-add-time:hover {
            background-color: #3699FF;
        }
        .btn-add-time:hover i {
            color: #ffffff;
        }
        .btn-remove-time {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            background-color: #FFE2E5;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-remove-time i {
            color: #F64E60;
            font-size: 14px;
        }
        .btn-remove-time:hover {
            background-color: #F64E60;
        }
        .btn-remove-time:hover i {
            color: #ffffff;
        }

        .ml-9 {
            margin-left: 35px;
        }
        .cursor-pointer {
            cursor: pointer;
        }

        /* Copy to All Button */
        .btn-copy-all {
            height: 32px;
            border-radius: 6px;
            background-color: #E8FFF3;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            padding: 0 12px;
            font-size: 12px;
            font-weight: 500;
            color: #50CD89;
            font-family: 'Poppins', sans-serif;
        }
        .btn-copy-all i {
            color: #50CD89;
            font-size: 14px;
            margin-right: 4px;
        }
        .btn-copy-all:hover {
            background-color: #50CD89;
            color: #ffffff;
        }
        .btn-copy-all:hover i {
            color: #ffffff;
        }
    </style>
    @endpush

    @push('js')
    <script>
        var resourceId = {{ request()->get('resource_id', 0) }};
        var resourceName = '{{ request()->get('resource_name', 'Resource') }}';
        var locationId = {{ request()->get('location_id', 0) }};
        var locationName = '{{ request()->get('location_name', 'Location') }}';
        var selectedDate = '{{ request()->get('date', date('Y-m-d')) }}';
    </script>
    <script src="{{asset('assets/js/pages/admin_settings/repeating-shifts.js')}}?v={{ time() }}"></script>
    @endpush

@endsection
