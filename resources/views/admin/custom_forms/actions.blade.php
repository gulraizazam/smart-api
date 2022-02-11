@if(Gate::allows('custom_forms_edit'))
    <a class="btn btn-xs btn-info"
       href="{{ route('admin.custom_forms.edit',[$custom_form->id]) }}">@lang('global.app_edit')</a>
@endif
@if(Gate::allows('custom_forms_preview'))
    <a class="btn btn-xs btn-info"
       href="{{ route('admin.custom_form_feedbacks.preview_form',["form_id"=>$custom_form->id]) }}">@lang('global.app_preview')</a>
@endif
@if(Gate::allows('custom_forms_destroy'))
    {!! Form::open(array(
    'style' => 'display: inline-block;',
    'method' => 'DELETE',
    'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
    'route' => ['admin.custom_forms.destroy', $custom_form->id])) !!}
    {!! Form::submit(trans('global.app_delete'), array('class' => 'btn btn-xs btn-danger')) !!}
    {!! Form::close() !!}
@endif

@if($custom_form->custom_form_type == 0)
    @if(Gate::allows('custom_forms_submit'))
        <a class="btn btn-xs btn-info" href="{{ route('admin.custom_form_feedbacks.fill_form',["form_id"=>$custom_form->id]) }}">@lang('global.app_submit')</a>
    @endif
@endif
