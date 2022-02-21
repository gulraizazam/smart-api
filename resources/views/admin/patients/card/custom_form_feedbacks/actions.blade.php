@if(Gate::allows('patients_customform_edit'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.customformfeedbackspatient.edit',[$custom_form_feedback->internal_id]) }}">@lang('global.app_edit')</a>
@endif
@if(Gate::allows('patients_customform_manage'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.customformfeedbackspatient.previewform',[$custom_form_feedback->internal_id]) }}">@lang('global.app_preview')</a>
@endif