<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_add_location_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Add Membership Type</h2>
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
        <form id="modal_add_membership_type_form" method="post" action="{{route('admin.membershiptypes.store')}}">
            <!--begin::Scroll-->
            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_add_location_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

                <div class="form-group">
                    <div class="row">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0"> Name <span class="text text-danger">*</span></label>
                            <input type="text" id="add_membership_name" name="name" class="form-control form-control-lg form-control-solid mb-2">
                        </div>
                        <div class="fv-row col-md-6">
                            <label class="fw-bold fs-6 mb-2 pl-0"> Parent Membership (for Renewals)</label>
                            <select id="add_parent_id" name="parent_id" class="form-control form-control-lg form-control-solid mb-2 select2">
                                <option value="">None (Main Membership)</option>
                                @php
                                    $parentMemberships = \App\Models\MembershipType::whereNull('parent_id')->where('active', 1)->get();
                                @endphp
                                @foreach($parentMemberships as $membership)
                                    <option value="{{ $membership->id }}">{{ $membership->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Leave empty for main membership, select parent for renewal</small>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0"> Period (Days) <span class="text text-danger">*</span></label>
                            <input type="number" id="add_membership_period" name="period" class="form-control form-control-lg form-control-solid mb-2">
                        </div>
                        <div class="fv-row col-md-6">
                            <label class="required fw-bold fs-6 mb-2 pl-0"> Price <span class="text text-danger">*</span></label>
                            <input type="number" id="add_membership_amount" name="amount" class="form-control form-control-lg form-control-solid mb-2">
                        </div>
                    </div>
                </div>

            </div>
            <!--end::Scroll-->
            <!--begin::Actions-->
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