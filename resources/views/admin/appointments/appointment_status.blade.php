<div class="modal-header">
    <button type="button" id="closeBtn" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title">Update Appointment Status</h4>
</div>

{!! Form::model($appointment, ['method' => 'PUT', 'id' => 'status-validation', 'route' => ['admin.appointments.update', $appointment->app_id]]) !!}

<div class="modal-body">
    <div class="form-body">
        <div class="alert alert-danger display-hide">
            <button class="close" data-close="alert"></button>
            Please check below.
        </div>
        <div class="alert alert-success display-hide">
            <button class="close" data-close="alert"></button>
            Form is being submit!
        </div>
        {!! Form::hidden('id', old('id'), ['id' => 'appointment']) !!}

        {!! Form::hidden('appointment_status_not_show', Config::get('constants.appointment_status_not_show'), ['id' => 'appointment_status_not_show']) !!}

        {!! Form::hidden('cancellation_reason_other_reason', Config::get('constants.cancellation_reason_other_reason'), ['id' => 'cancellation_reason_other_reason']) !!}

        <div class="form-group">
            @if($appointment->appointment_status->parent_id != 0)
                {!! Form::select('base_appointment_status_id', $base_appointment_statuses, $appointment->appointment_status->parent_id, ['onchange' => 'FormValidation.loadChildStatuses($(this).val())', 'id' => 'base_appointment_status_id', 'class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
            @else
                {!! Form::select('base_appointment_status_id', $base_appointment_statuses, $appointment->appointment_status_id, ['onchange' => 'FormValidation.loadChildStatuses($(this).val())', 'id' => 'base_appointment_status_id', 'class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
            @endif
        </div>

        <div class="form-group appointment_status_id" @if($appointment->appointment_status->parent_id == 0) style="display: none;" @endif>
            {!! Form::select('appointment_status_id', $appointment_statuses, $appointment->appointment_status_id, ['onchange' => 'FormValidation.statusListener($(this).val())', 'id' => 'appointment_status_id', 'class' => 'form-control', 'placeholder' => '', 'required' => '']) !!}
        </div>

        @if($appointment->appointment_status->parent_id == 0)
            <div class="form-group reason" @if($appointment->appointment_status->is_comment == 0) style="display: none;" @endif>
                {!! Form::textarea('reason', old('reason'), ['id' => 'reason', 'rows' => 3, 'class' => 'form-control', 'placeholder' => 'Type your comment..', 'required' => '']) !!}
            </div>
        @else
            <div class="form-group reason" @if($base_appointments[$appointment->appointment_status->parent_id]->is_comment == 0 && $appointment->appointment_status->is_comment == 0) style="display: none;" @endif>
                {!! Form::textarea('reason', old('reason'), ['id' => 'reason', 'rows' => 3, 'class' => 'form-control', 'placeholder' => 'Type your comment..', 'required' => '']) !!}
            </div>
        @endif

    </div>

</div>

<div class="modal-footer" id="modal-footer">
    <button type="button" class="btn default" data-dismiss="modal">Close</button>
    {!! Form::submit(trans('global.app_save'), ['' => 'appointment_status_btn', 'class' => 'btn btn-success']) !!}
</div>

{!! Form::close() !!}
{{--<script src="{{ url('js/admin/appointments/appointment_status.js') }}" type="text/javascript"></script>--}}
<script type="text/javascript">
    var FormValidation = function () {
        var e = function () {
            var e = $("#status-validation"), r = $(".alert-danger", e), i = $(".alert-success", e);
            e.validate({
                errorElement: "span",
                errorClass: "help-block help-block-error",
                focusInvalid: !1,
                ignore:":not(:visible)",
                messages: {
                },
                rules: {
                    base_appointment_status_id: {required: !0},
                    appointment_status_id: {required: '#appointment_status_id:visible'},
                    reason: {required: '#reason:visible'},
                },
                invalidHandler: function (e, t) {
                    i.hide(), r.show()
                },
                errorPlacement: function (e, r) {
                    var i = $(r).parent(".input-group");
                    i.size() > 0 ? i.after(e) : r.after(e)
                },
                highlight: function (e) {
                    $(e).closest(".form-group").addClass("has-error")
                },
                unhighlight: function (e) {
                    $(e).closest(".form-group").removeClass("has-error")
                },
                success: function (e) {
                    e.closest(".form-group").removeClass("has-error")
                },
                submitHandler: function (e) {
                    i.hide(), r.hide();
                    $('#appointment_status_btn').attr('disabled',true);
                    $.ajax({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        url: route('admin.appointments.storeappointmentstatus'),
                        type: "PUT",
                        data: $("#status-validation").serialize(),
                        cache: false,
                        success: function(response) {
                            if(response.status == '1') {
                                $('.alert-success').html("Form is submitted successfully!").show();
                                $('#modal-footer').remove();
                                $('#appointment' + $('#appointment').val()).html(response.base_appointment_status_name);
                                setTimeout(function() {
                                    $('#closeBtn').click();
                                }, 1000);
                            } else if(response.status == '2') {
                                $('.alert-danger').html("Invoice paid, you not able to change status!").show();
                                $('#appointment_status_btn').removeAttr('disabled');
                            } else {
                                $('.alert-danger').html("Kindly pay invoice first!").show();
                                $('#appointment_status_btn').removeAttr('disabled');
                            }
                        }
                    });
                    return false;
                }
            })
        }

        var loadChildStatuses = function (appointmentStatusId) {
            if(appointmentStatusId != '') {
                resetDropdowns();
                $("input[type=submit]", e).attr('disabled', true);
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: route('admin.appointments.load_child_appointment_statuses'),
                    type: 'POST',
                    data: {
                        appointment_status_id: appointmentStatusId
                    },
                    cache: false,
                    success: function(response) {
                        if(response.status == '1') {
                            $('.appointment_status_id').html(response.dropdown);
                        } else {
                            resetDropdowns();
                        }
                        if(parseInt(response.count) > 1) {
                            $('.appointment_status_id').show();
                        }
                        if(response.status == '1' && response.appointment_status.is_comment == '1') {
                            $('.reason').show();
                        } else {
                            resetReason();
                        }
                        $("input[type=submit]", e).removeAttr('disabled');
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        $("input[type=submit]", e).removeAttr('disabled');
                        resetDropdowns();
                    }
                });
            } else {
                resetDropdowns();
            }
        }

        var statusListener = function (appointmentStatusId) {
            if(appointmentStatusId != '') {
                $("input[type=submit]", e).attr('disabled', true);
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: route('admin.appointments.load_child_appointment_status_data'),
                    type: 'POST',
                    data: {
                        appointment_status_id: appointmentStatusId,
                        base_appointment_status_id: $('#base_appointment_status_id').val()
                    },
                    cache: false,
                    success: function(response) {
                        console.log(response);
                        if(response.status == '1' && (response.appointment_status.is_comment == '1' || response.base_appointment_status.is_comment == '1')) {
                            $('.reason').show();
                        } else {
                            resetReason();
                        }
                        $("input[type=submit]", e).removeAttr('disabled');
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        resetReason();
                        $("input[type=submit]", e).removeAttr('disabled');
                    }
                });
            } else {
                resetReason();
            }
        }

        var resetDropdowns = function() {
            resetReason();
            resetChildStatuses();
        }

        var resetReason = function () {
            $('.reason').hide();
            $('#reason').val('');
        }

        var resetChildStatuses = function () {
            $('.appointment_status_id').hide();
            $('.appointment_status_id').html(appointmentStatusDropdown);
        }

        var appointmentStatusDropdown = '<select onchange="FormValidation.statusListener($(this).val())" id="appointment_status_id" class="form-control" name="appointment_status_id"><option value=""></option><option value="">Select a Child Status</option></select>';

        return {
            init: function () {
                e()
            },
            loadChildStatuses: loadChildStatuses,
            statusListener: statusListener
        }
    }();
    
    jQuery(document).ready(function () {
        FormValidation.init()
    });
</script>