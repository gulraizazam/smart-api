<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title">@lang('global.app_display')</h4>
</div>
<div class="modal-body">
    <div class="portlet-body form">
        <div class="form-group">
            <div class="form-body">
                @include('admin.patients.card.invoices.invoice_fields')
            </div>
        </div>
    </div>
</div>
