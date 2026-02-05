{{-- Refunds Section --}}
<div class="section-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Refunds</h4>
    </div>
    
    {{-- Datatable --}}
    <div class="page-refund-form">
        <div class="datatable datatable-bordered datatable-head-custom refund-form"></div>
    </div>
</div>

{{-- Refund Modal --}}
<div class="modal fade" id="modal_refund_refund" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered form-popup" id="refund_refund_form">
        @include('admin.patients.card.refunds.refund')
    </div>
</div>
