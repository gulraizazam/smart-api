@if(Gate::allows('vouchers_allocate'))
    <a class="btn btn-xs btn-success" href="{{ route('admin.voucherTypes.location_manage',[$voucher->id]) }}"
       data-target="#ajax_vouchers" data-toggle="modal">@lang('global.voucherTypes.fields.location')</a>
@endif
@if(Gate::allows('vouchers_edit'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.voucherTypes.edit',[$voucher->id]) }}"
       data-target="#ajax_vouchers"
       data-toggle="modal">@lang('global.app_edit')</a>
@endif
@if(Gate::allows('vouchers_destroy'))
    {!! Form::open(array(
        'style' => 'display: inline-block;',
        'method' => 'DELETE',
        'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
        'route' => ['admin.voucherTypes.destroy', $voucher->id])) !!}
    {!! Form::submit(trans('global.app_delete'), array('class' => 'btn btn-xs btn-danger')) !!}
    {!! Form::close() !!}
@endif