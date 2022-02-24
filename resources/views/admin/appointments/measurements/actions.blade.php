{{--
<a class="btn btn-xs btn-info"
   href="{{ route('admin.customformfeedbackspatient.edit',[$custom_form_feedback->internal_id]) }}">@lang('global.app_edit')</a>
<a class="btn btn-xs btn-info"
   href="{{ route('admin.customformfeedbackspatient.previewform',[$custom_form_feedback->internal_id]) }}">@lang('global.app_preview')</a>
   --}}
@if(Gate::allows('appointments_measurement_edit'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.appointmentmeasurement.edit',[$appointmentmeasurement->id]) }}">@lang('global.app_edit')</a>
@endif
<a class="btn btn-xs btn-info" href="{{ route('admin.appointmentmeasurement.previewform',[$appointmentmeasurement->id]) }}">@lang('global.app_preview')</a>
