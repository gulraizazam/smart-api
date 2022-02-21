@if(Gate::allows('patients_refund_refund'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.nonplansrefundpatient.refund_create',[$nonplansrefunds['id']]) }}" data-target="#ajax_refunds_create" data-toggle="modal">@lang('global.app_refund')</a>
@endif
