<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder rota-title">Details</h2>
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
    <div class="modal-body scroll-y mx-5 mx-xl-15">
        <!--begin::Form-->
        <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_resourcerotas_scroll">

            <div class="form-group">

                <div class="row">


                    <div class="table-responsive">

                        <div class="row" style="margin-right: 0;">
                            <div class="col-md-3">
                                <ul class="calendar-left-menu list-unstyled treatment-detail-actions"></ul>
                            </div>

                            <div class="col-md-9">

                                <table class="table">
                                    <tbody>

                                    <tr>
                                        <th>Patient Name</th>
                                        <td id="treatment_patient_name"></td>
                                        <th>Patient Phone</th>
                                        <td id="treatment_patient_phone"></td>
                                    </tr>
                                    <tr>
                                        <th>Patient ID</th>
                                        <td id="treatment_customer_id"></td>
                                        <th>Gender</th>
                                        <td id="treatment_patient_gender"></td>
                                    </tr>
                                    <tr>
                                        <th>Appointment Time</th>
                                        <td id="treatment_patient_scheduled_time"></td>
                                        <th>Doctor</th>
                                        <td id="treatment_doctor_name"></td>
                                    </tr>
                                    <tr>
                                        <th>City</th>
                                        <td id="treatment_city_name"></td>
                                        <th>Centre</th>
                                        <td id="treatment_center_name"></td>
                                    </tr>
                                    <tr>
                                        <th>Appointment Status</th>
                                        <td id="treatment_appointment_status"></td>
                                        <th>Service/Consultancy</th>
                                        <td id="treatment_service_consultancy_name"></td>
                                    </tr>
                                    </tbody>
                                </table>

                                <form id="treatment_cment" style="margin-top: 35px;max-width: 95%;">


                            <h3 class="box-title">Comment</h3>

                            <div class="row">
                                <div class="col-md-11">
                                    <div class="col-md-12">
                                        <div class="portlet-body" id="treatment_commentsection">
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <div class="form-group">
                                <div class="row">

                                <div class="col-md-12">
                                    <label>Comment</label>
                                    <input type="text" id="treatment_comment" name="comment" class="form-control" required="">
                                </div>
                                <input type="hidden" name="appointment_id" id="treatment_comment_appointment_id" class="form-control" value=""><br>
                                <div class="col-md-12 mt-5">
                                    <button type="button" name="Add_treatment_comment" id="Add_treatment_comment" class="btn btn-success">Comment</button>
                                </div>

                            </div>
                            </div>

                        </form>

                            </div>
                        </div>

                    </div>

                </div>

            </div>

        </div>
        <!--end::Scroll-->
    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->



<?php /**PATH /var/www/html/resources/views/admin/appointments/appointment-forms/treatment/detail.blade.php ENDPATH**/ ?>