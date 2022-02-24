<a class="btn btn-xs btn-warning" href="{{ route('admin.appointments.detail',[$appointment['_id']]) }}"
   data-target="#ajax_detail_appointment" data-toggle="modal" title="View"><i class="fa fa-eye"></i></a>

@if(Gate::allows('appointments_edit'))
    @if($appointment['appointment_type_id'] == 1)
        <a class="btn btn-xs btn-info" href="{{ route('admin.appointments.edit',[$appointment['_id']]) }}"
           data-target="#ajax_appointments_edit" data-toggle="modal" title="Edit"><i class="fa fa-edit"></i></a>
    @elseif($appointment['appointment_type_id'] == 2)
        <a class="btn btn-xs btn-info" href="{{ route('admin.appointments.edit_service',[$appointment['_id']]) }}"
           data-target="#ajax_appointments_edit" data-toggle="modal" title="Edit"><i class="fa fa-edit"></i></a>
    @endif
@endif

<a href="{{ route('admin.appointments.sms_logs',[$appointment['_id']])  }}" class="btn btn-xs btn-success"
   data-target="#ajax_logs" data-toggle="modal"><i class="fa fa-send" data-toggle="tooltip" title="SMS Logs"></i></a>

@if($cancelled_appointment_status && ($cancelled_appointment_status->id != $appointment['appointment_status_id']))
    @if($appointment['appointment_type_id'] == 1)
        @if(Gate::allows('appointments_consultancy'))
            <a href="{{route("admin.appointments.create")}}?id={{$appointment['_id']}}&city_id={{$appointment['city_id']}}&location_id={{$appointment['location_id']}}&doctor_id={{$appointment['doctor_id']}}"
               class="btn btn-xs btn-success"><i class="fa fa-calendar" data-toggle="tooltip"
                                                 title="Doctor Calendar of this appointment"></i></a>
        @endif
    @elseif($appointment['appointment_type_id'] == 2)
        @if(Gate::allows('appointments_services'))
            <a href="{{route("admin.appointments.manage_services")}}?id={{$appointment['_id']}}&city_id={{$appointment['city_id']}}&location_id={{$appointment['location_id']}}&doctor_id={{$appointment['doctor_id']}}&machine_id={{$appointment['resource_id']}}"
               class="btn btn-xs btn-success"><i class="fa fa-calendar" data-toggle="tooltip"
                                                 title="Doctor Calendar of this appointment"></i></a>
        @endif
    @endif
@endif
@if(Gate::allows('appointments_invoice'))
    @if(!$invoice)
        @if($appointment['appointment_type_id'] == Config::get('constants.appointment_type_service'))
            <a class="btn btn-xs btn-info" href="{{ route('admin.appointments.invoicecreate',[$appointment['_id']]) }}"
               data-target="#ajax_appointment_invoice" data-toggle="modal">
                <i class="fa fa-file-o" title="Generate Invoice"></i>
            </a>
        @endif
        @if($appointment['appointment_type_id'] == Config::get('constants.appointment_type_consultancy'))
            <a class="btn btn-xs btn-info" href="{{ route('admin.appointments.invoice-create-consultancy',[$appointment['_id']]) }}"
               data-target="#ajax_appointment_consultancy_invoice" data-toggle="modal">
                <i class="fa fa-file-o" title="Generate Invoice"></i>
            </a>
        @endif
    @endif
@endif
@if(Gate::allows('appointments_invoice_display'))
    @if($invoice)
        <a class="btn btn-xs btn-info" href="{{ route('admin.appointments.InvoiceDisplay',[$invoiceid]) }}"
           data-target="#ajax_appointments_invoice_display" data-toggle="modal"><i class="fa fa-file-pdf-o"
                                                                                   title="Invoice Display"></i></a>
    @endif
@endif
@if($appointment['appointment_type_id'] == 2)
    @if(Gate::allows('appointments_image_manage'))
        <a class="btn btn-xs btn-info"
           href="{{ route('admin.appointmentsimage.imageindex',[$appointment['_id']]) }}" target="_blank"><i
                    class="fa fa-file-image-o" title="Images"></i></a>
    @endif
    @if(Gate::allows('appointments_measurement_manage'))
        <a class="btn btn-xs btn-info"
           href="{{ route('admin.appointmentsmeasurement.measurements',[$appointment['_id']]) }}" target="_blank"><i
                    class="fa fa-stethoscope" title="Measurement"></i></a>
    @endif
@endif
@if($appointment['appointment_type_id'] == 1)
    @if(Gate::allows('appointments_medical_form_manage'))
        <a class="btn btn-xs btn-info" href="{{ route('admin.appointmentsmedical.medicals',[$appointment['_id']]) }}"
           target="_blank"><i
                    class="fa fa-medkit" title="Medical History Form"></i></a>
    @endif
@endif
@if(Gate::allows('appointments_plans_create'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.appointmentplans.create',[$appointment['_id']]) }}"
       data-target="#ajax_packages" data-toggle="modal"><i class="fa fa-clipboard" title="Create Plan"></i></a>
@endif
@if(Gate::allows('appointments_destroy'))
    @if(
        ($unscheduled_appointment_status->id == $appointment['appointment_status_id']) &&
        (!$appointment['scheduled_date'] && !$appointment['scheduled_time'])
    )
        {!! Form::open(array(
            'style' => 'display: inline-block;',
            'method' => 'DELETE',
            'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
            'route' => ['admin.appointments.destroy', $appointment['_id']])) !!}
        {!! Form::button('<i class="fa fa-trash" title="' . trans('global.app_delete') . '"></i>', array('class' => 'btn btn-xs btn-danger', 'type' => 'submit')) !!}
        {!! Form::close() !!}
    @endif
@endif
@if(Gate::allows('appointments_patient_card'))
    <a class="btn btn-xs btn-info" target="_blank"
       href="{{ route('admin.patients.preview',[$appointment['patient_id']]) }}"><i class="icon-users"
                                                                                  title="Patient Card"></i></a>
@endif
@if (Gate::allows('appointments_log'))
    <a class="btn btn-xs btn-info" target="_blank"
       href="{{ route('admin.appointments.viewlog', [$appointment['_id'], 'web']) }}"><i class="fa fa-history" title="{{ trans('global.app_log') }}"></i>
    </a>
@endif
