<div class="card-body page-invoice-form">
    <!--begin::Search Form-->
@include('admin.patients.card.invoices.filters')
<!--end::Search Form-->

    <!--begin: Datatable-->
    <div class="datatable datatable-bordered datatable-head-custom invoice-form"></div>
    <!--end: Datatable-->


    <div class="modal fade" id="modal_invoice_sms_logs" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="sms_invoice_form">

            @include('admin.patients.card.invoices.sms_logs')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_invoice_display" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered big-modal" id="invoices_display">

            @include('admin.invoices.displayInvoice')

        </div>
        <!--end::Modal dialog-->
    </div>

</div>
