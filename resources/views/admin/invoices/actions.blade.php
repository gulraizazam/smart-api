@if(Gate::allows('invoices_manage'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.invoices.displayInvoice',[$invoice->id]) }}"
       data-target="#ajax_invoice_display" data-toggle="modal">@lang('global.app_display')</a>
@endif
@if($invoice->invoice_status_id != $cancel->id)
    @if(Gate::allows('invoices_cancel'))
        {!! Form::open(array(
            'style' => 'display: inline-block;',
            'method' => 'CANCEL',
            'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
            'route' => ['admin.invoices.cancel', $invoice->id])) !!}
        {!! Form::submit(trans('global.app_cancel'), array('class' => 'btn btn-xs btn-danger')) !!}
        {!! Form::close() !!}
    @endif
@endif
@if(Gate::allows('invoices_log'))
    <a class="btn btn-xs btn-success" href="{{ route('admin.invoices.invoice_log',[$invoice->id, 'web']) }}">@lang('global.app_log')</a>
@endif
@if(Gate::allows('invoices_sms_log'))
    <a href="{{ route('admin.invoices.sms_logs',[$invoice->id])  }}" class="btn btn-xs btn-success"
       data-target="#invoice_sms_logs" data-toggle="modal">@lang('global.app_sms')</a>
@endif

