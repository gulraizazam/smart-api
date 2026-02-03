<div class="card-body page-appointment-form">
    <!--begin::Search Form-->
@include('admin.patients.card.appointments.filters')
<!--end::Search Form-->

    <!--begin: Datatable-->
    <div class="datatable datatable-bordered datatable-head-custom appointment-form"></div>
    <!--end: Datatable-->

    <!--begin::Appointment Detail Modal-->
    <div class="modal fade" id="modal_appointment_detail" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mediam-modal" id="appointment_detail">
            @include('admin.appointments.appointment-forms.detail')
        </div>
    </div>
    <!--end::Appointment Detail Modal-->

    <!--begin::Edit Appointment Modal-->
    <div class="modal fade" id="modal_edit_appointment" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered form-popup" id="appointment_edit">
            @include('admin.appointments.appointment-forms.edit')
        </div>
    </div>
    <!--end::Edit Appointment Modal-->

    <!--begin::Edit Treatment Modal-->
    <div class="modal fade" id="modal_treatment_edit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mediam-modal" id="appointment_treatmenty_detail">
            @include('admin.appointments.appointment-forms.treatment.edit')
        </div>
    </div>
    <!--end::Edit Treatment Modal-->

</div>
