@if(Gate::allows('appointments_measurement_edit'))
<a class="btn btn-xs btn-info" href="{{ route('admin.measurementhistoryform.edit',[$appointmentmeasurements->id]) }}">@lang('global.app_edit')</a>
@endif
@if(Gate::allows('appointments_measurement_manage'))
<a class="btn btn-xs btn-info" href="{{ route('admin.measurementhistoryform.previewform',[$appointmentmeasurements->id]) }}">@lang('global.app_preview')</a>
@endif