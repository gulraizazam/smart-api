<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder rota-title" id="service_consultancy_name_title"></h2>
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

                <div class="row" style="margin-right: 0;">


                    <div class="table-responsive">

                        <div class="row" style="margin-right: 0;">
                            <div class="col-md-3">
                                <ul class="calendar-left-menu list-unstyled detail-actions"></ul>
                            </div>

                            <div class="col-md-9">

                                <table class="table">
                                    <tbody>
                                    <tr>
                                        <th>Patient Name</th>
                                        <td id="patient_name"></td>
                                        <th>Patient Phone</th>
                                        <td id="patient_phone"></td>
                                    </tr>
                                    <tr>
                                        <th>Patient ID</th>
                                        <td id="patient_c_id"></td>
                                        <th>Gender</th>
                                        <td id="patient_gender"></td>
                                    </tr>
                                    <tr>
                                        <th>Appointment Time</th>
                                        <td id="patient_scheduled_time"></td>
                                        <th>Doctor</th>
                                        <td id="doctor_name"></td>
                                    </tr>
                                    <tr>
                                        <th>City</th>
                                        <td id="city_name"></td>
                                        <th>Centre</th>
                                        <td id="center_name"></td>
                                    </tr>
                                    <tr>
                                        <th>Appointment Status</th>
                                        <td id="appointment_status"></td>
                                        <th>Treatment</th>
                                        <td id="service_consultancy_name"></td>
                                    </tr>
                                    <tr>
                                    </tr>
                                    </tbody>
                                </table>

                                <form id="cment" style="margin-top: 35px;">

                                    <h3 class="box-title">Comment</h3>

                                    <div class="row">
                                        <div class="col-md-11">
                                            <div class="col-md-12">
                                                <div class="portlet-body" id="commentsection">
                                                </div>
                                            </div>
                                        </div>
                                    </div>


                                    <div class="form-group">
                                        <div class="row">

                                            <div class="col-md-12">
                                                <label>Comment</label>
                                                <input type="text" name="comment" id="consultancy_comment" class="form-control" required="">
                                            </div>
                                            <input type="hidden" name="appointment_id" id="comment_appointment_id" class="form-control" value=""><br>
                                            <div class="col-md-12 mt-5">
                                                <button type="button" name="Add_comment" id="Add_comment" class="btn btn-success">Comment</button>
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



