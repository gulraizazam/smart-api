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
                            @if(Gate::allows('scheduling_shifts.create'))
                                <div class="dropdown">
                                    <button class="btn btn-dark dropdown-toggle" type="button" id="addDropdownBtn" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        Add
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="addDropdownBtn">
                                        <a class="dropdown-item" href="javascript:void(0);" id="btn_add_time_off_global">
                                            <i class="la la-clock mr-2"></i>Time off
                                        </a>
                                        <a class="dropdown-item" href="javascript:void(0);" id="btn_add_business_closed">
                                            <i class="la la-ban mr-2"></i>Business closed period
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger" href="javascript:void(0);" id="btn_bulk_delete_shifts">
                                            <i class="la la-trash mr-2"></i>Delete Shifts
                                        </a>
                                    </div>
                                </div>
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
                                {{-- Resource Type field commented out
                                <div class="col-md-3">
                                    <label class="mb-2">Resource Type <span class="text-danger">*</span></label>
                                    <select class="form-control" id="filter_resource_type">
                                        <option value="2" selected>Doctor</option>
                                        <option value="1">Machine</option>
                                    </select>
                                </div>
                                --}}
                                <input type="hidden" id="filter_resource_type" value="2">
                                <div class="col-md-5">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <button type="button" class="btn btn-icon btn-light mr-2" id="btn_prev_week">
                                            <i class="la la-angle-left"></i>
                                        </button>
                                        <span class="font-weight-bold font-size-lg" id="week_range_display">This week</span>
                                        <button type="button" class="btn btn-icon btn-light ml-2" id="btn_next_week">
                                            <i class="la la-angle-right"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4 text-right">
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

    <!-- Bulk Delete Shifts Modal -->
    <div class="modal fade" id="modal_bulk_delete_shifts" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title font-weight-bold">Delete Shifts</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <i class="la la-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-4">Select a date range and resource to delete all shifts within that period.</p>
                    
                    <form id="form_bulk_delete_shifts">
                        <div class="form-group">
                            <label>Resource <span class="text-danger">*</span></label>
                            <select class="form-control" id="bulk_delete_resource_id" name="resource_id" required>
                                <option value="">Select Resource</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Start Date <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="bulk_delete_start_date" name="start_date" placeholder="Select start date" required readonly>
                        </div>
                        <div class="form-group">
                            <label>End Date <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="bulk_delete_end_date" name="end_date" placeholder="Select end date" required readonly>
                        </div>
                        <div class="alert alert-warning mb-0" id="bulk_delete_warning" style="display: none;">
                            <i class="la la-exclamation-triangle mr-2"></i>
                            <span id="bulk_delete_warning_text"></span>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="btn_confirm_bulk_delete">
                        <i class="la la-trash"></i> Delete Shifts
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Shift Modal -->
    <div class="modal fade" id="modal_add_shift" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h4 class="modal-title font-weight-bold" id="add_shift_title">Add Shift</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pt-2">
                    <p class="text-muted mb-4">You are editing this day's shifts only. To set repeating shifts, go to <a href="javascript:void(0);" class="text-primary" onclick="$('#modal_add_shift').modal('hide'); handleShiftAction('repeating-shifts');">scheduled shifts</a>.</p>
                    
                    <form id="form_add_shift">
                        <input type="hidden" id="shift_resource_id" name="resource_id">
                        <input type="hidden" id="shift_date" name="date">
                        <input type="hidden" id="shift_location_id" name="location_id">
                        
                        <div id="shift_rows_container">
                            <div class="shift-row mb-3" data-row="0">
                                <div class="row align-items-end">
                                    <div class="col-md-5">
                                        <label class="mb-2">Start time</label>
                                        <select class="form-control shift-start-time" name="shifts[0][start_time]">
                                            <!-- Time options will be populated by JS -->
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="mb-2">End time</label>
                                        <select class="form-control shift-end-time" name="shifts[0][end_time]">
                                            <!-- Time options will be populated by JS -->
                                        </select>
                                    </div>
                                    <div class="col-md-2 text-center">
                                        <button type="button" class="btn btn-icon btn-light-danger btn-sm remove-shift-row" style="display: none;">
                                            <i class="la la-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_add_shift_row">
                                <i class="la la-plus-circle mr-1"></i> Add shift
                            </button>
                            <span class="text-muted" id="total_shift_duration">Total shift duration: 0h</span>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <div class="d-flex justify-content-between w-100">
                        <button type="button" class="btn btn-icon btn-outline-danger rounded-circle" id="btn_delete_all_shifts" title="Delete all shifts" style="display: none;" onclick="deleteAllShifts()">
                            <i class="la la-trash"></i>
                        </button>
                        <div class="ml-auto">
                            <button type="button" class="btn btn-light mr-2" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-dark" id="btn_save_shift">Save</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Time Off Modal -->
    <div class="modal fade" id="modal_add_time_off" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0 d-flex justify-content-between align-items-start">
                    <h4 class="modal-title font-weight-bold">Add time off</h4>
                    <button type="button" class="btn btn-icon btn-hover-light-primary" data-dismiss="modal" aria-label="Close" style="width: 35px; height: 35px; background-color: #F3F6F9; border-radius: 6px;">
                        <i class="la la-times" style="font-size: 20px; color: #7E8299;"></i>
                    </button>
                </div>
                <div class="modal-body pt-3">
                    <form id="form_add_time_off">
                        <input type="hidden" id="time_off_location_id" name="location_id">
                        
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <label class="mb-2 font-weight-bold">Team member</label>
                                <select class="form-control" id="time_off_resource_id" name="resource_id">
                                    <!-- Options populated by JS -->
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-5">
                                <label class="mb-2 font-weight-bold">Start date</label>
                                <input type="text" class="form-control datepicker" id="time_off_start_date" name="start_date" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="mb-2 font-weight-bold">Start time</label>
                                <select class="form-control" id="time_off_start_time" name="start_time">
                                    <!-- Options populated by JS -->
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="mb-2 font-weight-bold">End time</label>
                                <select class="form-control" id="time_off_end_time" name="end_time">
                                    <!-- Options populated by JS -->
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="d-flex align-items-center cursor-pointer">
                                <span class="time-off-checkbox mr-3">
                                    <input type="checkbox" id="time_off_repeat" name="repeat">
                                    <span class="checkbox-box"></span>
                                </span>
                                <span class="font-weight-bold" style="font-size: 15px;">Repeat</span>
                            </label>
                        </div>
                        
                        <div class="row mb-4" id="repeat_until_row" style="display: none;">
                            <div class="col-md-5">
                                <label class="mb-2 font-weight-bold">Repeat until</label>
                                <input type="text" class="form-control datepicker" id="time_off_repeat_until" name="repeat_until" readonly>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="font-weight-bold mb-0">Description</label>
                                <span class="text-muted" id="description_counter">0/100</span>
                            </div>
                            <textarea class="form-control" id="time_off_description" name="description" rows="3" maxlength="100" placeholder="Add description or note (optional)"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light px-6" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-dark px-6" id="btn_save_time_off">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!--begin::Edit Business Closure Modal-->
    <div class="modal fade" id="modal_edit_closure" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title font-weight-bold" id="edit_closure_modal_title">Edit closed period</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <i class="ki ki-close"></i>
                    </button>
                </div>
                <div class="modal-body pt-4">
                    <input type="hidden" id="edit_closure_id" value="">
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="mb-2 font-weight-bold">Start date</label>
                            <input type="text" class="form-control" id="edit_closure_start_date" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="mb-2 font-weight-bold">End date</label>
                            <input type="text" class="form-control" id="edit_closure_end_date" readonly>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <label class="mb-2 font-weight-bold">Title</label>
                            <input type="text" class="form-control" id="edit_closure_title" placeholder="Enter title">
                        </div>
                    </div>
                    
                    
                </div>
                <div class="modal-footer border-0 pt-0">
                    <div class="d-flex justify-content-between w-100 align-items-center">
                        <span class="text-muted" id="closure_duration"></span>
                        <div>
                            <button type="button" class="btn btn-icon btn-light-danger mr-2" onclick="deleteClosure()" title="Delete">
                                <i class="la la-trash" style="font-size: 25px;"></i>
                            </button>
                            <button type="button" class="btn btn-dark px-6" onclick="saveClosureEdit()">Save</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Edit Business Closure Modal-->

    <!--begin::Delete Confirmation Modal-->
    <div class="modal fade" id="modal_delete_closure_confirm" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title font-weight-bold">Delete Closed Period</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <i class="ki ki-close"></i>
                    </button>
                </div>
                <div class="modal-body pt-4">
                    <p class="mb-3">Are you sure you want to delete this closed period?</p>
                    <div class="bg-light-danger rounded p-3">
                        <div class="d-flex align-items-center mb-2">
                            <i class="la la-calendar text-danger mr-2" style="font-size: 18px;"></i>
                            <span class="font-weight-bold" id="delete_closure_dates"></span>
                        </div>
                        <div class="text-muted" style="font-size: 13px;" id="delete_closure_duration"></div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light px-6" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger px-6" id="btn_confirm_delete_closure">Delete</button>
                </div>
            </div>
        </div>
    </div>
    <!--end::Delete Confirmation Modal-->

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
            min-height: 80px;
            vertical-align: middle;
        }
        .shift-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            min-height: 60px;
        }
        .shift-badge-wrapper {
            position: relative;
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
        .shift-badge.clickable {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .shift-badge.clickable:hover {
            box-shadow: 0 2px 8px rgba(54, 153, 255, 0.3);
            transform: translateY(-1px);
        }
        .shift-badge.not-working {
            background-color: #F3F6F9;
            color: #B5B5C3;
        }
        .shift-badge.weekend {
            background-color: #E1F0FF;
            color: #3699FF;
        }
        .shift-badge.weekend.clickable:hover {
            box-shadow: 0 2px 8px rgba(54, 153, 255, 0.3);
        }
        .shift-badge.business-closed {
            background-color: #FFE2E5;
            color: #F64E60;
        }
        .shift-cell.business-closed-day {
            background-color: #F8F9FC;
            pointer-events: none;
        }
        .shift-cell.business-closed-day .shift-badge.not-working {
            background-color: #E4E6EF;
            color: #7E8299;
        }
        .shift-badge.time-off {
            background-color: #E4E6EF;
            color: #5E6278;
            text-align: center;
            line-height: 1.3;
            padding: 8px 12px;
        }
        .shift-badge.time-off strong {
            font-size: 11px;
            display: block;
            margin-bottom: 2px;
            color: #3F4254;
        }
        /* Spanning time off styles - horizontal bar across day columns */
        .spanning-time-off-row {
            background-color: transparent;
        }
        .spanning-time-off-row .team-member-cell {
            vertical-align: middle;
            border-bottom: none !important;
        }
        .spanning-time-off-cell {
            padding: 8px 0 4px 0 !important;
            position: relative;
            background-color: transparent;
            border-bottom: none !important;
        }
        .spanning-time-offs-container {
            position: relative;
            height: 40px;
            width: 100%;
        }
        .spanning-time-off-bar {
            position: absolute;
            top: 0;
            height: 40px;
            background-color: #E4E6EF;
            border-radius: 6px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid #D1D3E0;
        }
        .spanning-time-off-bar:hover {
            background-color: #D1D3E0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .spanning-time-off-label {
            font-weight: 600;
            font-size: 12px;
            color: #3F4254;
        }
        .spanning-time-off-time {
            font-size: 11px;
            color: #7E8299;
        }
        .resource-row.has-spanning-time-off td {
            border-top: none !important;
        }
        .spanning-time-off-wrapper {
            position: absolute;
            top: 0;
            height: 40px;
        }
        .spanning-time-off-wrapper .spanning-time-off-bar {
            position: relative;
            width: 100%;
            height: 100%;
        }
        .spanning-time-off-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 8px 0;
            z-index: 1000;
            min-width: 160px;
            margin-top: 4px;
        }
        .spanning-time-off-dropdown.show {
            display: block;
        }
        .spanning-time-off-dropdown .shift-dropdown-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            width: 100%;
            border: none;
            background: none;
            text-align: left;
            cursor: pointer;
            font-size: 13px;
            color: #3F4254;
            transition: background-color 0.15s ease;
        }
        .spanning-time-off-dropdown .shift-dropdown-item:hover {
            background-color: #F3F6F9;
        }
        .spanning-time-off-dropdown .shift-dropdown-item i {
            font-size: 16px;
        }
        .shift-edit-dropdown {
            min-width: 180px;
        }
        .shift-dropdown-item.text-danger {
            color: #F64E60 !important;
        }
        .shift-dropdown-item.text-danger:hover {
            background-color: #FFF5F8 !important;
        }
        .time-off-checkbox {
            position: relative;
            display: inline-block;
        }
        .time-off-checkbox input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        .time-off-checkbox .checkbox-box {
            display: inline-block;
            width: 22px;
            height: 22px;
            background-color: #fff;
            border: 2px solid #E4E6EF;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .time-off-checkbox input:checked ~ .checkbox-box {
            background-color: #3699FF;
            border-color: #3699FF;
        }
        .time-off-checkbox input:checked ~ .checkbox-box:after {
            content: '';
            position: absolute;
            left: 7px;
            top: 3px;
            width: 6px;
            height: 12px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        .cursor-pointer {
            cursor: pointer;
        }
        .shift-add-btn {
            visibility: hidden;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: #E1E9FF;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .shift-add-btn i {
            color: #3699FF;
            font-size: 14px;
        }
        .shift-add-btn:hover {
            background-color: #3699FF;
        }
        .shift-add-btn:hover i {
            color: #fff;
        }
        .shift-cell:hover .shift-add-btn {
            visibility: visible;
        }
        .shift-dropdown {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            min-width: 200px;
            z-index: 1000;
            display: none;
            padding: 8px 0;
            margin-top: 5px;
            white-space: nowrap;
        }
        .shift-dropdown.show {
            display: block;
        }
        .shift-dropdown-item {
            display: block;
            width: 100%;
            padding: 10px 16px;
            text-align: left;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 13px;
            color: #3F4254;
            transition: background-color 0.2s;
        }
        .shift-dropdown-item:hover {
            background-color: #F3F6F9;
        }
        .shift-dropdown-item i {
            margin-right: 8px;
            color: #7E8299;
            width: 16px;
        }
        .shift-container {
            position: relative;
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
        <script src="{{asset('assets/js/pages/admin_settings/schedule-calendar.js')}}?v={{ time() }}"></script>
    @endpush

@endsection
