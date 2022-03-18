{{--change status--}}
<div class="modal fade" id="modal_change_appointment_status" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered form-popup" id="appointment_status_change">

        @include('admin.appointments.consultancy-forms.change-status')

    </div>
</div>

{{--Edit appoitment--}}
<div class="modal fade" id="modal_edit_appointment" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered form-popup" id="appointment_edit">

        @include('admin.appointments.consultancy-forms.edit')

    </div>
</div>

<div class="modal fade" id="modal_create_consultancy" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered form-popup" id="consultancy_edit">

        @include('admin.appointments.consultancy-forms.create')

    </div>
</div>


<div class="modal fade" id="modal_sms_log" tabindex="-1" aria-hidden="true" style="z-index: 9999">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered mediam-modal" id="appointment_status_change">

        @include('admin.appointments.consultancy-forms.sms-log')

    </div>
</div>

<div class="modal fade" id="modal_consultancy_detail" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered mediam-modal" id="appointment_consultancy_detail">

        @include('admin.appointments.consultancy-forms.consultancy-detail')

    </div>
</div>

<div class="popup">
    @include('admin.appointments.consultancy-forms.popup')
</div>
