<div class="card-body page-plan-form">
    <!--begin: Datatable-->
    <div class="datatable datatable-bordered datatable-head-custom plan-form"></div>
    <!--end: Datatable-->

    <div class="modal fade" id="modal_add_plan" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered very-big-modal" id="packages_add">

            @include('admin.packages.create', ['isPatientCard' => true])

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_add_bundle" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered very-big-modal" id="bundles_add">

            @include('admin.packages.create-bundle', ['isPatientCard' => true])

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_edit_plan" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered very-big-modal" id="packages_edit">

            @include('admin.packages.edit', ['isPatientCard' => true])

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_edit_bundle" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered very-big-modal" id="bundles_edit">
            @include('admin.packages.edit-bundle', ['isPatientCard' => true])
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
        <div class="modal-dialog modal-dialog-centered very-big-modal" id="invoices_display">

            @include('admin.packages.display')

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

    <div class="modal fade" id="modal_add_membership" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered very-big-modal" id="memberships_add">

            @include('admin.packages.create-membership', ['isPatientCard' => true])

        </div>
        <!--end::Modal dialog-->
    </div>

</div>
