
<div class="modal fade" id="modal_change_appointment_status" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered form-popup" id="appointment_status_change">

        <?php echo $__env->make('admin.appointments.appointment-forms.change-status', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    </div>
</div>

<div class="modal fade" id="modal_change_appointment_schedule" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered form-popup" id="appointment_schedule_change">

        <?php echo $__env->make('admin.appointments.appointment-forms.schedule', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    </div>
</div>


<div class="modal fade" id="modal_edit_appointment" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered form-popup" id="appointment_edit">

        <?php echo $__env->make('admin.appointments.appointment-forms.edit', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    </div>
</div>

<div class="modal fade" id="modal_create_consultancy" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered form-popup" id="consultancy_edit">

        <?php echo $__env->make('admin.appointments.appointment-forms.create', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    </div>
</div>


<div class="modal fade" id="modal_sms_log" tabindex="-1" aria-hidden="true" style="z-index: 9999">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered mediam-modal" id="appointment_status_change">

        <?php echo $__env->make('admin.appointments.appointment-forms.sms-log', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    </div>
</div>

<div class="modal fade" id="modal_consultancy_detail" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered big-modal" id="appointment_consultancy_detail">

        <?php echo $__env->make('admin.appointments.appointment-forms.consultancy-detail', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    </div>
</div>


<div class="modal fade" id="modal_create_consultancy_invoice" tabindex="-1" aria-hidden="true" style="z-index: 9999">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered mediam-modal" id="create_consultancy_invoice">
        
    </div>
</div>

<div class="modal fade" id="modal_create_treatment_invoice" tabindex="-1" aria-hidden="true" style="z-index: 9999">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered big-modal" id="create_treatment_invoice">
        
    </div>
</div>

<div class="modal fade" id="modal_display_invoice" tabindex="-1" aria-hidden="true" style="z-index: 9999">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered big-modal" id="display_invoice">
        
    </div>
</div>

<div class="popup">
    <?php echo $__env->make('admin.appointments.appointment-forms.popup', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</div>



<div class="modal fade" id="modal_create_treatment" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered form-popup" id="treatment_edit">

        <?php echo $__env->make('admin.appointments.appointment-forms.treatment.create', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    </div>
</div>

<div class="modal fade" id="modal_treatment_detail" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered big-modal" id="appointment_treatment_detail">

        <?php echo $__env->make('admin.appointments.appointment-forms.treatment.detail', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    </div>
</div>

<div class="modal fade" id="modal_treatment_edit" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered mediam-modal" id="appointment_treatmenty_detail">

        <?php echo $__env->make('admin.appointments.appointment-forms.treatment.edit', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    </div>
</div>





<div class="modal fade" id="modal_appointment_plan" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered very-big-modal" id="appointment_plan">

        <?php echo $__env->make('admin.appointments.plans.create', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    </div>
</div>

<div class="modal fade" id="modal_appointment_detail" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered mediam-modal" id="appointment_detail">

        <?php echo $__env->make('admin.appointments.appointment-forms.detail', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    </div>
</div>

<?php /**PATH /var/www/html/resources/views/admin/appointments/appointment-forms/modals.blade.php ENDPATH**/ ?>