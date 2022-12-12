@if(Gate::allows('discounts_allocate'))
    <a class="btn btn-xs btn-success" href="{{ route('admin.discounts.location_manage',[$discount->id]) }}"
       data-target="#ajax_discounts" data-toggle="modal">@lang('global.doctors.fields.location')</a>
@endif
@if(Gate::allows('discounts_edit'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.discounts.edit',[$discount->id]) }}"
       data-target="#ajax_discounts"
       data-toggle="modal">@lang('global.app_edit')</a>
@endif
@if(Gate::allows('discounts_destroy'))
    {!! Form::open(array(
        'style' => 'display: inline-block;',
        'method' => 'DELETE',
        'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
        'route' => ['admin.discounts.destroy', $discount->id])) !!}
    {!! Form::submit(trans('global.app_delete'), array('class' => 'btn btn-xs btn-danger')) !!}
    {!! Form::close() !!}
@endif