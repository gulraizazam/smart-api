<style>
    #modal_appointment_detail .modal-header {
        align-items: flex-start;
        padding: 12px 16px;
        border-bottom: 1px solid #eef0f8;
    }
    #modal_appointment_detail #appointment_patient_name_title {
        font-size: 16px;
        font-weight: 700;
        color: #181c32;
        margin: 0;
        line-height: 1.25;
        word-break: break-word;
    }
    #modal_appointment_detail .modal-body {
        background: #fafbfc;
        padding: 12px 14px !important;
    }
    .appointment-detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px;
    }
    .appointment-detail-item {
        background: #fff;
        border: 1px solid #eef0f8;
        border-radius: 6px;
        padding: 6px 10px;
        display: flex;
        flex-direction: column;
        gap: 1px;
        min-width: 0;
    }
    .appointment-detail-item--full { grid-column: 1 / -1; }
    .appointment-detail-label {
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #a1a5b7;
        line-height: 1.2;
    }
    .appointment-detail-value {
        font-size: 13px;
        font-weight: 500;
        color: #3f4254;
        word-break: break-word;
        line-height: 1.3;
    }
    .appointment-notes-form {
        background: #fff;
        border: 1px solid #eef0f8;
        border-radius: 6px;
        padding: 10px 12px;
        margin-top: 10px !important;
    }
    .appointment-notes-title {
        font-size: 13px;
        font-weight: 600;
        color: #181c32;
        margin: 0 0 6px;
    }
    .appointment-notes-list:empty { display: none; }
    .appointment-detail-actions:empty { display: none; }
    @media (max-width: 767.98px) {
        #modal_appointment_detail .modal-dialog {
            margin: 0;
            max-width: 100%;
            height: 100%;
            width: 100%;
        }
        #modal_appointment_detail .modal-content {
            height: 100%;
            border-radius: 0;
            border: none;
        }
        #modal_appointment_detail .modal-header {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #fff;
        }
        #modal_appointment_detail .modal-body {
            max-height: none !important;
            flex: 1 1 auto;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
    }
    @media (max-width: 575.98px) {
        .appointment-detail-grid { grid-template-columns: 1fr; }
    }
</style>
<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder rota-title" id="appointment_patient_name_title"></h2>
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
    <div class="modal-body scroll-y px-4 px-md-8 py-6">
        <div class="appointment-detail-actions mb-4"></div>

        <div class="appointment-detail-grid">
            <div class="appointment-detail-item">
                <span class="appointment-detail-label">Patient ID</span>
                <span class="appointment-detail-value" id="appointment_patient_c_id"></span>
            </div>
            <div class="appointment-detail-item" id="appointment_patient_phone_row">
                <span class="appointment-detail-label">Phone</span>
                <span class="appointment-detail-value" id="appointment_patient_phone"></span>
            </div>
            <div class="appointment-detail-item">
                <span class="appointment-detail-label">Gender</span>
                <span class="appointment-detail-value" id="appointment_patient_gender"></span>
            </div>
            <div class="appointment-detail-item">
                <span class="appointment-detail-label">Status</span>
                <span class="appointment-detail-value" id="appointment_appointment_status"></span>
            </div>
            <div class="appointment-detail-item">
                <span class="appointment-detail-label">Appointment Time</span>
                <span class="appointment-detail-value" id="appointment_patient_scheduled_time"></span>
            </div>
            <div class="appointment-detail-item">
                <span class="appointment-detail-label">Doctor</span>
                <span class="appointment-detail-value" id="appointment_doctor_name"></span>
            </div>
            <div class="appointment-detail-item">
                <span class="appointment-detail-label">Centre</span>
                <span class="appointment-detail-value" id="appointment_center_name"></span>
            </div>
            <div class="appointment-detail-item appointment-detail-item--full">
                <span class="appointment-detail-label">Service</span>
                <span class="appointment-detail-value" id="appointment_service_consultancy_name"></span>
            </div>
        </div>

        <form id="appointment_cment" class="appointment-notes-form mt-6">
            <h3 class="appointment-notes-title">Notes</h3>
            <div id="appointment_commentsection" class="appointment-notes-list mb-4"></div>
            <div id="appointment_comment_editor">
                <textarea id="appointment_comment" name="comment" class="form-control" rows="3" required=""></textarea>
                <input type="hidden" name="appointment_id" id="appointment_comment_appointment_id" class="form-control" value="">
                <div class="mt-3 text-end">
                    <button type="button" name="Add_appointment_comment" id="Add_appointment_comment" class="btn btn-success btn-sm">Add Comment</button>
                </div>
            </div>
        </form>
    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->



