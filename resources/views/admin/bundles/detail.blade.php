<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_edit_user_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Package Details</h2>
        <!--end::Modal title-->
        <!--begin::Close-->
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" data-kt-users-modal-action="close">
            <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
            <span class="svg-icon svg-icon-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)"
                          fill="black"/>
                    <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/>
                </svg>
            </span>
            <!--end::Svg Icon-->
        </div>
        <!--end::Close-->
    </div>
    <!--end::Modal header-->
    <!--begin::Modal body-->
    <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
        <!--begin::Scroll-->
        <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_add_user_scroll" data-kt-scroll="true"
             data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto"
             data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll"
             data-kt-scroll-offset="300px">
            <div class="row">
                <div class="fv-row col-md-12">
                    <table class="table table-bordered">
                        <tbody>
                        <tr>
                            <th>Name</th>
                            <td id="detail_name"></td>
                            <th>Offered Price</th>
                            <td id="detail_price"></td>
                        </tr>
                        <tr>
                            <th>Services Price</th>
                            <td id="detail_services_price"></td>
                            <th>Total Services</th>
                            <td id="detail_total_services"></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="fv-row col-md-12">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>Service</th>
                            <th>Price</th>
                        </tr>
                        </thead>
                        <tbody id="detail-service-body">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!--begin::Actions-->
        <hr>
        <div class="text-center">
            <button type="reset" class="btn btn-light me-3 popup-close" data-kt-users-modal-action="cancel">Close
            </button>
        </div>
        <!--end::Actions-->
    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->
