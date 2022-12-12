<div class="card-body page-refund-form">
    <!--begin::Search Form-->
@include('admin.patients.card.refunds.filters')
<!--end::Search Form-->

    <!--begin: Datatable-->
    <div class="datatable datatable-bordered datatable-head-custom refund-form"></div>
    <!--end: Datatable-->


    <div class="modal fade" id="modal_refund_refund" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="refund_refund_form">

            @include('admin.patients.card.refunds.refund')

        </div>
        <!--end::Modal dialog-->
    </div>

</div>
