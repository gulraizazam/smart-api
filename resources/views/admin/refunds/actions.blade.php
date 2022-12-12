{{--
@if($balance != '0')
    <a class="btn btn-xs btn-info" href="{{ route('admin.refunds.refund_create',['patient_id' => $refunds->patient_id, 'balance' => $balance]) }}" data-target="#ajax_refunds_create" data-toggle="modal">@lang('global.app_refund')</a>
@endif
<a class="btn btn-xs btn-success" href="{{ route('admin.refunds.detail',[$refunds->patient_id]) }}" data-target="#ajax_refunds_detail" data-toggle="modal">@lang('global.app_detail')</a>
--}}
@if(Gate::allows('refunds_refund'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.refunds.refund_create',[$package->id]) }}" data-target="#ajax_refunds_create" data-toggle="modal">@lang('global.app_refund')</a>
@endif