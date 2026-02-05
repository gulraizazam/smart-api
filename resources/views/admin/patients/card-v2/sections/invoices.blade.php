{{-- Invoices Section --}}
<div class="section-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Invoices</h4>
    </div>
    
    {{-- Datatable --}}
    <div class="page-invoice-form">
        <div class="datatable datatable-bordered datatable-head-custom invoice-form"></div>
    </div>
</div>

{{-- SMS Logs Modal --}}
<div class="modal fade" id="modal_invoice_sms_logs" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered form-popup" id="sms_invoice_form">
        @include('admin.patients.card.invoices.sms_logs')
    </div>
</div>

{{-- Display Invoice Modal --}}
<div class="modal fade" id="modal_invoice_display" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered big-modal" id="invoices_display">
        @include('admin.invoices.displayInvoice')
    </div>
</div>
