@if(Gate::allows('appointments_medical_edit'))
<a class="btn btn-xs btn-info" href="{{ route('admin.medicalhistoryform.edit',[$appointmentmedicals->id]) }}">@lang('global.app_edit')</a>
@endif
@if(Gate::allows('appointments_medical_form_manage'))
<a class="btn btn-xs btn-info" href="{{ route('admin.medicalhistoryform.previewform',[$appointmentmedicals->id]) }}">@lang('global.app_preview')</a>
@endif