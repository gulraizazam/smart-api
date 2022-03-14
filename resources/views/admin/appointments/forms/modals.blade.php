{{--change status--}}
<div class="modal fade" id="modal_change_appointment_status" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered form-popup" id="appointment_status_change">

        @include('admin.appointments.forms.change-status')

    </div>
</div>

{{--Edit appoitment--}}
<div class="modal fade" id="modal_edit_appointment" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered form-popup" id="appointment_edit">

        @include('admin.appointments.forms.edit')

    </div>
</div>


<div class="modal fade" id="modal_sms_log" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered mediam-modal" id="appointment_status_change">

        @include('admin.appointments.forms.sms-log')

    </div>
</div>
