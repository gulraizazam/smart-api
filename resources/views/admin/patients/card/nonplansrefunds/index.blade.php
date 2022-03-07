<div class="card-body page-no-plan-refund-form">
    <!--begin::Search Form-->
{{--@include('admin.patients.card.refunds.filters')--}}
<!--end::Search Form-->

    <!--begin: Datatable-->
    <div class="datatable datatable-bordered datatable-head-custom no-plan-refund-form"></div>
    <!--end: Datatable-->


    <div class="modal fade" id="modal_no-plan-refund_refund" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="no-plan-refund_refund_form">

            @include('admin.patients.card.nonplansrefunds.refund')

        </div>
        <!--end::Modal dialog-->
    </div>

</div>
