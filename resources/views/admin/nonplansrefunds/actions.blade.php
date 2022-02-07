@if(Gate::allows('refunds_refund'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.nonprefunds.refund_create',[$nonplansrefunds['id']]) }}" data-target="#ajax_refunds_create" data-toggle="modal">@lang('global.app_refund')</a>
@endif