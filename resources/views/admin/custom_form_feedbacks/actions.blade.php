@if(Gate::allows('custom_form_feedbacks_edit'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.custom_form_feedbacks.edit',[$custom_form_feedback->internal_id]) }}">@lang('global.app_edit')</a>
@endif
@if(Gate::allows('custom_form_feedbacks_preview'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.custom_form_feedbacks.filled_preview',[$custom_form_feedback->internal_id]) }}">@lang('global.app_preview')</a>
@endif