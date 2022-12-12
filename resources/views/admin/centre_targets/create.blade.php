<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Add Centre Targets</h2>
        <!--end::Modal title-->
        <!--begin::Close-->
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" data-kt-users-modal-action="close">
            <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
            <span class="svg-icon svg-icon-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                    <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                </svg>
            </span>
            <!--end::Svg Icon-->
        </div>
        <!--end::Close-->
    </div>
    <!--end::Modal header-->
    <!--begin::Modal body-->
    <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
        <!--begin::Form-->
        <form id="modal_add_centre_targets_form" method="post" action="{{route('admin.centre_targets.store')}}">
            <!--begin::Scroll-->

            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_centre_targets_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div id="add_centre_require_field" class="alert alert-warning d-none" role="alert">
                    <i class="fa fa-exclamation-circle"></i>
                    Please select all options to continue.
                </div>

                <div id="add_centre_edit_perform" class="alert alert-info d-none" role="alert">
                    <i class="fa fa-exclamation-circle"></i>
                    You are going to update existing record.
                </div>

                <div class="form-group">
                    <div class="row">

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Year <span class="text text-danger">*</span></label>
                            <select onchange="loadActiveLocation();" id="add_year" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="year">
                            </select>
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Month <span class="text text-danger">*</span></label>
                            <select onchange="loadActiveLocation();" id="add_month" class="form-control form-control-solid mb-3 mb-lg-0 select2" name="month">
                            </select>
                        </div>

                        <div class="fv-row col-md-12 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">Working Days <span class="text text-danger">*</span></label>
                            <input min="0" id="add_working_days" value="0" oninput="phoneField(this);" class="form-control" type="number" name="working_days">
                        </div>
                    </div>

                </div>

            </div>
            <!--end::Scroll-->
            <!--begin::Actions-->

            <div class="table-responsive add_center_target_table d-none">
                <table id="add_centre_target_location" class="table table-striped table-bordered table-advance table-hover">

                    <thead>
                    <tr>
                        <th>Location Name</th>
                        <th>Target Amount</th>
                    </tr>
                    </thead>

                </table>
            </div>

            <hr>
            <div class="text-center">
                <button type="reset" class="btn btn-light me-3 popup-close" data-kt-users-modal-action="cancel">Cancel</button>
                <button type="submit" class="btn btn-primary spinner-button">
                    <span class="indicator-label">Submit</span>
                </button>
            </div>
            <!--end::Actions-->
        </form>

        <!--end::Form-->
    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->



