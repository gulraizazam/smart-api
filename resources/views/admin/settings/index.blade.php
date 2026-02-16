@extends('admin.layouts.master')
@section('title', 'Global Settings')
@section('content')

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'Global Settings', 'title' => 'Global Settings'])

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
                            <h3 class="card-label">Global Settings</h3>
                        </div>

                        <div class="card-toolbar">
                            <a href="javascript:void(0);" class="btn btn-light-primary" onclick="openWorkingDaysModal();">
                                <i class="la la-calendar-check"></i>
                                Business Working Days
                            </a>
                        </div>

                    </div>

                    <div class="card-body">
                        <!--begin::Search Form-->
                    @include('admin.settings.filters')
                    <!--end::Search Form-->

                        <!--begin: Datatable-->
                        <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
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
    @include('admin.settings.edit')

    <!-- Working Days Modal -->
    <div class="modal fade" id="modal_working_days" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Business Working Days</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <i aria-hidden="true" class="ki ki-close"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-5">Select the days when your business is open. Shifts and time off cannot be scheduled on closed days.</p>
                    
                    <div class="working-days-list d-flex flex-wrap justify-content-between">
                        <div class="text-center px-2 mb-3">
                            <label class="d-block mb-2 font-weight-bold">Mon</label>
                            <label class="checkbox checkbox-lg checkbox-success">
                                <input type="checkbox" id="working_monday" name="working_days[monday]">
                                <span></span>
                            </label>
                        </div>
                        <div class="text-center px-2 mb-3">
                            <label class="d-block mb-2 font-weight-bold">Tue</label>
                            <label class="checkbox checkbox-lg checkbox-success">
                                <input type="checkbox" id="working_tuesday" name="working_days[tuesday]">
                                <span></span>
                            </label>
                        </div>
                        <div class="text-center px-2 mb-3">
                            <label class="d-block mb-2 font-weight-bold">Wed</label>
                            <label class="checkbox checkbox-lg checkbox-success">
                                <input type="checkbox" id="working_wednesday" name="working_days[wednesday]">
                                <span></span>
                            </label>
                        </div>
                        <div class="text-center px-2 mb-3">
                            <label class="d-block mb-2 font-weight-bold">Thu</label>
                            <label class="checkbox checkbox-lg checkbox-success">
                                <input type="checkbox" id="working_thursday" name="working_days[thursday]">
                                <span></span>
                            </label>
                        </div>
                        <div class="text-center px-2 mb-3">
                            <label class="d-block mb-2 font-weight-bold">Fri</label>
                            <label class="checkbox checkbox-lg checkbox-success">
                                <input type="checkbox" id="working_friday" name="working_days[friday]">
                                <span></span>
                            </label>
                        </div>
                        <div class="text-center px-2 mb-3">
                            <label class="d-block mb-2 font-weight-bold">Sat</label>
                            <label class="checkbox checkbox-lg checkbox-success">
                                <input type="checkbox" id="working_saturday" name="working_days[saturday]">
                                <span></span>
                            </label>
                        </div>
                        <div class="text-center px-2 mb-3">
                            <label class="d-block mb-2 font-weight-bold">Sun</label>
                            <label class="checkbox checkbox-lg checkbox-success">
                                <input type="checkbox" id="working_sunday" name="working_days[sunday]">
                                <span></span>
                            </label>
                        </div>
                    </div>

                    <hr class="my-5">

                    <h6 class="font-weight-bold mb-3">Date Exceptions</h6>
                    <p class="text-muted mb-4">Add specific dates that should be treated differently from the default schedule above.</p>

                    <div class="row mb-3">
                        <div class="col-md-5">
                            <input type="text" class="form-control" id="exception_date" placeholder="Select date" readonly>
                        </div>
                        <div class="col-md-4">
                            <select class="form-control" id="exception_type">
                                <option value="1">Make Working Day</option>
                                <option value="0">Make Non-Working Day</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-light-primary btn-block" id="btn_add_exception">
                                <i class="la la-plus"></i> Add
                            </button>
                        </div>
                    </div>

                    <div id="exceptions_list" class="mt-4">
                        <!-- Exceptions will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btn_save_working_days">Save</button>
                </div>
            </div>
        </div>
    </div>

    @push('datatable-js')
        <script src="{{asset('assets/js/pages/admin_settings/settings.js')}}"></script>
    @endpush

    @push('js')
        <script src="{{asset('assets/js/pages/crud/forms/validation/settings/settings_validate.js')}}"></script>
    @endpush

@endsection
