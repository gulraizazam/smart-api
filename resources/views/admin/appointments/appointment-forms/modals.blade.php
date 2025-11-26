{{--change status--}}
<div class="modal fade" id="modal_change_appointment_status" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered form-popup" id="appointment_status_change">

        @include('admin.appointments.appointment-forms.change-status')

    </div>
</div>

<div class="modal fade" id="modal_change_appointment_schedule" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered form-popup" id="appointment_schedule_change">

        @include('admin.appointments.appointment-forms.schedule')

    </div>
</div>

{{--Edit appoitment--}}
<div class="modal fade" id="modal_edit_appointment" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered form-popup" id="appointment_edit">

        @include('admin.appointments.appointment-forms.edit')

    </div>
</div>

<div class="modal fade" id="modal_create_consultancy"  aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered form-popup" id="consultancy_edit">

        @include('admin.appointments.appointment-forms.create')

    </div>
</div>


<div class="modal fade" id="modal_sms_log" tabindex="-1" aria-hidden="true" style="z-index: 9999">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered mediam-modal" id="appointment_status_change">

        @include('admin.appointments.appointment-forms.sms-log')

    </div>
</div>

<div class="modal fade" id="modal_consultancy_detail" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered big-modal" id="appointment_consultancy_detail">

        @include('admin.appointments.appointment-forms.consultancy-detail')

    </div>
</div>


<div class="modal fade" id="modal_create_consultancy_invoice" tabindex="-1" aria-hidden="true" style="z-index: 9999">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered mediam-modal" id="create_consultancy_invoice">
        {{--consultancy invoice create here--}}
    </div>
</div>

<div class="modal fade" id="modal_create_treatment_invoice" tabindex="-1" aria-hidden="true" style="z-index: 9999">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered big-modal" id="create_treatment_invoice">
        {{--treatment invoice create here--}}
    </div>
</div>

<div class="modal fade" id="modal_display_invoice" tabindex="-1" aria-hidden="true" style="z-index: 9999">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered big-modal" id="display_invoice">
        {{--treatment invoice create here--}}
    </div>
</div>

<div class="popup">
    @include('admin.appointments.appointment-forms.popup')
</div>

{{--treatment forms--}}

<div class="modal fade" id="modal_create_treatment"  aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered mediam-modal" id="treatment_edit">

        @include('admin.appointments.appointment-forms.treatment.create')

    </div>
</div>

<div class="modal fade" id="modal_treatment_detail" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered big-modal" id="appointment_treatment_detail">

        @include('admin.appointments.appointment-forms.treatment.detail')

    </div>
</div>

<div class="modal fade" id="modal_treatment_edit" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered mediam-modal" id="appointment_treatmenty_detail">

        @include('admin.appointments.appointment-forms.treatment.edit')

    </div>
</div>

{{--end treatment--}}

{{--Create plan--}}

<div class="modal fade" id="modal_appointment_plan" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered very-big-modal" id="appointment_plan">

        @include('admin.appointments.plans.create')

    </div>
</div>

<div class="modal fade" id="modal_appointment_detail" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered mediam-modal" id="appointment_detail">

        @include('admin.appointments.appointment-forms.detail')

    </div>
</div>
<div class="modal fade" id="modal_appointment_feedback" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered mediam-modal" id="appointment_detail">

        @include('admin.appointments.appointment-forms.feedback')

    </div>
</div>
