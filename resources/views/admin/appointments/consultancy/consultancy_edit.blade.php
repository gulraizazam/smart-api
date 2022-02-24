<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title">@lang('global.app_edit')</h4>
</div>
<div class="modal-body">
    <div class="form-group">
        {!! Form::model($appointment, ['method' => 'PUT', 'id' => 'edit-validation', 'route' => ['admin.appointments.update', $appointment->id]]) !!}
        <div class="form-body">
            <!-- Starts Form Validation Messages -->
        @include('partials.messages')
        <!-- Ends Form Validation Messages -->

            @include('admin.appointments.consultancy.consultancy_fields')
        </div>
        <div>
            {!! Form::submit(trans('global.app_update'), ['class' => 'btn btn-danger']) !!}
        </div>
        {!! Form::close() !!}
    </div>
</div>
<script src="{{ url('js/admin/appointments/consultancy/edit.js') }}" type="text/javascript"></script>
<script type="text/javascript">
    $(document).ready(function () {
        /*you can directly call listenerDoctorMachine but that is already define*/
        EditFormValidation.doctorListener($('#consultancty_doctor_id').val());
        /*end*/
        @if($resourceHadRotaDay->start_time && $resourceHadRotaDay->start_off)
        EditFormValidation.loadScheduledTime(
            '{{ \Carbon\Carbon::parse($resourceHadRotaDay->start_time)->format('h:ia') }}',
            '{{ \Carbon\Carbon::parse($resourceHadRotaDay->end_time)->subMinutes($appointment->service->duration_in_minutes)->format('h:ia') }}',
            '{{\Carbon\Carbon::parse($resourceHadRotaDay->start_off)->subMinutes($appointment->service->duration_in_minutes)->addMinute('5')->format('h:ia')}}',
            '{{\Carbon\Carbon::parse($resourceHadRotaDay->end_off)->format('h:ia')}}'
        )
        @elseif($resourceHadRotaDay->start_time && !$resourceHadRotaDay->start_off)
        EditFormValidation.loadScheduledTime(
            '{{ \Carbon\Carbon::parse($resourceHadRotaDay->start_time)->format('h:ia') }}',
            '{{ \Carbon\Carbon::parse($resourceHadRotaDay->end_time)->subMinutes($appointment->service->duration_in_minutes)->format('h:ia') }}',
            false, false
        )
        @endif
    });
</script>