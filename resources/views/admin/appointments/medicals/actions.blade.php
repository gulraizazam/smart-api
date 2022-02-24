@if(Gate::allows('appointments_medical_edit'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.appointmentsmedical.edit',[$appointmentmedicals->id]) }}">@lang('global.app_edit')</a>
@endif
<a class="btn btn-xs btn-info" href="{{ route('admin.appointmentsmedical.previewform',[$appointmentmedicals->id]) }}">@lang('global.app_preview')</a>
