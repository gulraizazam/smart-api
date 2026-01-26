@inject('request', 'Illuminate\Http\Request')
@if($request->header('referer'))
    @php($endelement = explode('/', strtok($request->header('referer'), '?')))
@else
    @php($endelement = array())
@endif
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title">@lang('global.app_detail')</h4>
</div>
<div class="modal-body">
    <table class="table table-striped">
        <tbody>
        @if(in_array('create', $endelement) || in_array('manage-services', $endelement))
            <tr>
                <td colspan="4" style="text-align: right;">
                    @if(Gate::allows('appointments_edit'))
                        @if($appointment->appointment_type_id==1)
                            <a class="btn btn-xs btn-info"
                               href="{{ route('admin.appointments.edit',[$appointment->id]) }}"
                               data-target="#ajax_appointments_edit" data-toggle="modal" title="Edit"><i
                                        class="fa fa-edit"></i></a>
                        @elseif($appointment->appointment_type_id==2)
                            <a class="btn btn-xs btn-info"
                               href="{{ route('admin.appointments.edit_service',[$appointment->id]) }}"
                               data-target="#ajax_appointments_edit" data-toggle="modal" title="Edit"><i
                                        class="fa fa-edit"></i></a>
                        @endif
                    @endif

                    <a href="{{ route('admin.appointments.sms_logs',[$appointment->id])  }}"
                       class="btn btn-xs btn-success" data-target="#ajax_logs" data-toggle="modal"><i class="fa fa-send"
                                                                                                      data-toggle="tooltip"
                                                                                                      title="SMS Logs"></i></a>
                    @if(Gate::allows('appointments_invoice'))
                        @if(!$invoice)
                            @if($appointment->appointment_type_id == Config::get('constants.appointment_type_service'))
                                <a class="btn btn-xs btn-info"
                                   href="{{ route('admin.appointments.invoicecreate',[$appointment->id]) }}"
                                   data-target="#ajax_appointment_invoice" data-toggle="modal">
                                    <i class="fa fa-file-o" title="Generate Invoice"></i>
                                </a>
                            @endif
                            @if($appointment->appointment_type_id == Config::get('constants.appointment_type_consultancy'))
                                <a class="btn btn-xs btn-info"
                                   href="{{ route('admin.appointments.invoice-create-consultancy',[$appointment->id]) }}"
                                   data-target="#ajax_appointment_consultancy_invoice" data-toggle="modal">
                                    <i class="fa fa-file-o" title="Generate Invoice"></i>
                                </a>
                            @endif
                        @endif
                    @endif
                    @if(Gate::allows('appointments_invoice_display'))
                        @if($invoice)
                            <a class="btn btn-xs btn-info"
                               href="{{ route('admin.appointments.InvoiceDisplay',[$invoiceid]) }}"
                               data-target="#ajax_appointments_invoice_display" data-toggle="modal"><i
                                        class="fa fa-file-pdf-o"
                                        title="Invoice Display"></i></a>
                        @endif
                    @endif
                    
                    @if(Gate::allows('appointments_patient_card'))
                        <a class="btn btn-xs btn-info" target="_blank"
                           href="{{ route('admin.patients.preview',[$appointment->patient_id]) }}"><i class="icon-users"
                                                                                                      title="Patient Card"></i></a>
                    @endif
                </td>
            </tr>
        @endif
        <tr>
            <th>Patient Name</th>
            <td>{{ ($appointment->name) ? $appointment->name : $appointment->patient->name }}</td>
            <th>Patient Phone</th>
            <td>@if($appointment->patient->phone){{ \App\Helpers\GeneralFunctions::prepareNumber4Call($appointment->patient->phone) }}@else{{'N/A'}}@endif</td>
        </tr>
        <tr>
            <th>Email</th>
            <td>@if($appointment->patient->email){{ $appointment->patient->email }}@else{{'N/A'}}@endif</td>
            <th>Gender</th>
            <td>@if($appointment->patient->gender){{ Config::get('constants.gender_array')[$appointment->patient->gender] }}@else{{'N/A'}}@endif</td>
        </tr>
        <tr>
            <th>Appointment Time</th>
            <td>@if($appointment->scheduled_date){{ \Carbon\Carbon::parse($appointment->scheduled_date, null)->format('M j, y') . ' at ' . \Carbon\Carbon::parse($appointment->scheduled_time, null)->format('h:i A') }}@else{{'-'}}@endif</td>
            <th>Doctor</th>
            <td>@if($appointment->doctor_id){{ $appointment->doctor->name }}@else{{'N/A'}}@endif</td>
        </tr>
        <tr>
            <th>City</th>
            <td>@if($appointment->city_id){{ $appointment->city->name }}@else{{'N/A'}}@endif</td>
            <th>Centre</th>
            <td>@if($appointment->location_id){{ $appointment->location->name }}@else{{'N/A'}}@endif</td>
        </tr>
        <tr>
            <th>Appointment Status</th>
            <td @if($appointment->appointment_status_id != Config::get('constants.appointment_status_not_show'))
                @endif>@if($appointment->appointment_status_id){{ $appointment->appointment_status->name }}
                @else{{'N/A'}}@endif</td>
            <th>Treatment</th>
            <td>{{$appointment->service->name}}</td>
        </tr>
        <tr>
            @if($appointment->appointment_status_id == Config::get('constants.appointment_status_not_show'))
                <th>{{ trans('global.cancellation_reasons.word') }}</th>
                <td>@if($appointment->cancellation_reason_id && isset($appointment->cancellation_reason->name)){{ $appointment->cancellation_reason->name }}@else{{ 'N/A' }}@endif</td>
            @endif
        </tr>
        @if(
            ($appointment->appointment_status_id == Config::get('constants.appointment_status_not_show')) &&
            ($appointment->cancellation_reason_id == Config::get('constants.cancellation_reason_other_reason'))
        )
            <tr>
                <th>Reason</th>
                <td colspan="3">@if($appointment->reason){{ $appointment->reason }}@else{{ 'N/A' }}@endif</td>
            </tr>
        @endif
        </tbody>
    </table>
</div>
<div class="col-md-11">
    <div class="col-md-12">
        <div class="box-header ui-sortable-handle" style="cursor: move;">
            <h3 class="box-title">Comments</h3>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-11">
        <div class="col-md-12">
            <div class="portlet-body" id="commentsection">
                @if(count($appointment->appointment_comments))
                    @foreach($appointment->appointment_comments as $comment)
                        <div class="tab-content" id="itemComment">
                            <div class="tab-pane active" id="portlet_comments_1">
                                <!-- BEGIN: Comments -->
                                <div class="mt-comments">
                                    <div class="mt-comment">
                                        <div class="mt-comment-img" id="imgContainer">
                                            <img src="{{ url('img/avatar.jpg') }}" alt="Avatar">
                                        </div>
                                        <div class="mt-comment-body">
                                            <div class="mt-comment-info">
                                                <span class="mt-comment-author"
                                                      id="creat_by">@if($comment->created_by){{ $comment->user->name }}@else{{'N/A'}}@endif</span>
                                                <span class="mt-comment-date"
                                                      id="datetime">{{ \Carbon\Carbon::parse($comment->created_at)->format('D M d, Y g:i A') }}</span>
                                            </div>
                                            <div class="mt-comment-text"
                                                 id="message">@if($comment->comment){{ $comment->comment }}@else{{'N/A'}}@endif</div>
                                            <div class="mt-comment-details">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
@if(Gate::allows('appointments_manage'))
    <div class="container" style="width:100%;padding-bottom:5%; ">
        <div class="box-footer">
            <form id="cment">
                <div class="col-md-12">
                    <label>Comment</label>
                    <input type="text" name="comment" class="form-control" required/>
                    <br/>
                </div>
                <input type="hidden" name="appointment_id" id="appointment_id" class="form-control"
                       value="{{$appointment->id}}"/><br/>
                <div class="col-md-12">
                    <button type="button" name="Add_comment" id="Add_comment" class="btn btn-success">Comment</button>
                </div>
            </form>
        </div>
    </div>
@endif



<script>
    $("#Add_comment").click(function () {
        $.ajax({
            type: 'get',
            url: route('admin.appointments.storecomment'),
            data: {
                '_token': $('input[name=_token]').val(),
                'comment': $('input[name=comment]').val(),
                'appointment_id': $('input[name=appointment_id]').val(),
            },
            success: function (myarray) {
             
                $('#commentsection').prepend('<div class="tab-content" id="itemComment"><div class="tab-pane active" id="portlet_comments_1"><div class="mt-comments"><div class="mt-comment"><div class="mt-comment-img"><img src="{{ url('img/avatar.jpg') }}" alt="Avatar"></div><div class="mt-comment-body"><div class="mt-comment-info"><span class="mt-comment-author">' + myarray.username + '</span><span class="mt-comment-date">' + myarray.appointmentCommentDate + '</span></div><div class="mt-comment-text">' + myarray.appointment.comment + '</div></div></div></div></div></div>')
            },

        });
        $('#cment')[0].reset();
    });
</script>
