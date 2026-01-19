<div class="card-body page-plan-form">
    <!--begin: Datatable-->
    <div class="datatable datatable-bordered datatable-head-custom plan-form"></div>
    <!--end: Datatable-->

    <div class="modal fade" id="modal_add_plan_form" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered very-big-modal" id="add_plan_form">

            @include('admin.patients.card.plans.create')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_edit_plan" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered very-big-modal" id="edit_plan_form">

            @include('admin.patients.card.plans.edit')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_sms_logs" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="edit_documents_form">

            @include('admin.patients.card.plans.sms_logs')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_display" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered big-modal" id="edit_documents_form">

            @include('admin.patients.card.plans.display')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="plan_edit_cash" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered very-big-modal" id="plan_edit">

            @include('admin.packages.plane-edit')

        </div>
        <!--end::Modal dialog-->
    </div>

</div>
