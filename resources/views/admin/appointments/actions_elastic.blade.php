<a class="btn btn-xs btn-warning" href="{{ route('admin.appointments.detail',[$appointment['_id']]) }}"
   data-target="#ajax_detail_appointment" data-toggle="modal" title="View"><i class="fa fa-eye"></i></a>

{{-- Check permissions based on appointment type --}}
@if($appointment['appointment_type_id'] == 1)
    @if(Gate::allows('appointments_edit'))
        <a class="btn btn-xs btn-info" href="{{ route('admin.appointments.edit',[$appointment['_id']]) }}"
           data-target="#ajax_appointments_edit" data-toggle="modal" title="Edit"><i class="fa fa-edit"></i></a>
    @endif
@elseif($appointment['appointment_type_id'] == 2)
    @if(Gate::allows('treatments_edit'))
        <a class="btn btn-xs btn-info" href="{{ route('admin.appointments.edit_service',[$appointment['_id']]) }}"
           data-target="#ajax_appointments_edit" data-toggle="modal" title="Edit"><i class="fa fa-edit"></i></a>
    @endif
@endif

<a href="{{ route('admin.appointments.sms_logs',[$appointment['_id']])  }}" class="btn btn-xs btn-success"
   data-target="#ajax_logs" data-toggle="modal"><i class="fa fa-send" data-toggle="tooltip" title="SMS Logs"></i></a>

{{-- Invoice permissions based on appointment type --}}
@if(!$invoice)
    @if($appointment['appointment_type_id'] == Config::get('constants.appointment_type_service'))
        @if(Gate::allows('treatments_invoice'))
            <a class="btn btn-xs btn-info" href="{{ route('admin.appointments.invoicecreate',[$appointment['_id']]) }}"
               data-target="#ajax_appointment_invoice" data-toggle="modal">
                <i class="fa fa-file-o" title="Generate Invoice"></i>
            </a>
        @endif
    @endif
    @if($appointment['appointment_type_id'] == Config::get('constants.appointment_type_consultancy'))
        @if(Gate::allows('appointments_invoice'))
            <a class="btn btn-xs btn-info" href="{{ route('admin.appointments.invoice-create-consultancy',[$appointment['_id']]) }}"
               data-target="#ajax_appointment_consultancy_invoice" data-toggle="modal">
                <i class="fa fa-file-o" title="Generate Invoice"></i>
            </a>
        @endif
    @endif
@endif

{{-- Invoice display permissions based on appointment type --}}
@if($invoice)
    @if($appointment['appointment_type_id'] == Config::get('constants.appointment_type_service'))
        @if(Gate::allows('treatments_invoice_display'))
            <a class="btn btn-xs btn-info" href="{{ route('admin.appointments.InvoiceDisplay',[$invoiceid]) }}"
               data-target="#ajax_appointments_invoice_display" data-toggle="modal"><i class="fa fa-file-pdf-o"
                                                                                       title="Invoice Display"></i></a>
        @endif
    @else
        @if(Gate::allows('appointments_invoice_display'))
            <a class="btn btn-xs btn-info" href="{{ route('admin.appointments.InvoiceDisplay',[$invoiceid]) }}"
               data-target="#ajax_appointments_invoice_display" data-toggle="modal"><i class="fa fa-file-pdf-o"
                                                                                       title="Invoice Display"></i></a>
        @endif
    @endif
@endif
{{-- Treatment-specific permissions --}}
@if($appointment['appointment_type_id'] == 2)
    
@endif

{{-- Delete permission based on appointment type --}}
@if(
    ($unscheduled_appointment_status->id == $appointment['appointment_status_id']) &&
    (!$appointment['scheduled_date'] && !$appointment['scheduled_time'])
)
    @if($appointment['appointment_type_id'] == 2)
        @if(Gate::allows('treatments_destroy'))
            {!! Form::open(array(
                'style' => 'display: inline-block;',
                'method' => 'DELETE',
                'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
                'route' => ['admin.appointments.destroy', $appointment['_id']])) !!}
            {!! Form::button('<i class="fa fa-trash" title="' . trans('global.app_delete') . '"></i>', array('class' => 'btn btn-xs btn-danger', 'type' => 'submit')) !!}
            {!! Form::close() !!}
        @endif
    @else
        @if(Gate::allows('appointments_destroy'))
            {!! Form::open(array(
                'style' => 'display: inline-block;',
                'method' => 'DELETE',
                'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
                'route' => ['admin.appointments.destroy', $appointment['_id']])) !!}
            {!! Form::button('<i class="fa fa-trash" title="' . trans('global.app_delete') . '"></i>', array('class' => 'btn btn-xs btn-danger', 'type' => 'submit')) !!}
            {!! Form::close() !!}
        @endif
    @endif
@endif

{{-- Patient card permission based on appointment type --}}
@if($appointment['appointment_type_id'] == 2)
    @if(Gate::allows('treatments_patient_card'))
        <a class="btn btn-xs btn-info" target="_blank"
           href="{{ route('admin.patients.preview',[$appointment['patient_id']]) }}"><i class="icon-users"
                                                                                      title="Patient Card"></i></a>
    @endif
@else
    @if(Gate::allows('appointments_patient_card'))
        <a class="btn btn-xs btn-info" target="_blank"
           href="{{ route('admin.patients.preview',[$appointment['patient_id']]) }}"><i class="icon-users"
                                                                                      title="Patient Card"></i></a>
    @endif
@endif
