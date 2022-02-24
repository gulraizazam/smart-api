<div class="modal-header">
    <button type="button" id="closeBtn" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title">Appointment SMS Logs</h4>
</div>
<div class="modal-body">
    <div class="table-responsive">
        <table id="table" class="table table-striped table-bordered table-advance table-hover">
            {{ csrf_field() }}
            <thead>
            <tr>
                <th>Phone</th>
                <th>Text</th>
                <th>Sent</th>
                <th>Is_Refund</th>
                <th>Type</th>
                <th width="20%">Created at</th>
            </tr>
            </thead>
            @if(count($SMSLogs))
                @foreach($SMSLogs as $SMSLog)
                    @if(is_null($SMSLog->invoice_id))
                        <tr>
                            <td>{{ \App\Helpers\GeneralFunctions::prepareNumber4Call(\App\Helpers\GeneralFunctions::cleanNumber($SMSLog->to)) }}</td>
                            <td><a href="javascript:void(0);"
                                   onclick="toggle({{$SMSLog->id}})">{{ substr($SMSLog->text, 0, 50) . '...' }}</a>
                            </td>
                            <td id="smsRow{{ $SMSLog->id }}">
                                @if($SMSLog->status)
                                    {{ 'Yes' }}
                                @else
                                    <span id="spanRow{{ $SMSLog->id }}">{{ 'No' }}</span>
                                    <br/><a id="clickRow{{ $SMSLog->id }}" href="javascript:void(0)"
                                            onclick="resendSMS('{{ $SMSLog->id }}');" class="btn btn-xs btn-success"
                                            data-toggle="tooltip" title="Resend SMS"><i class="fa fa-send"></i></a>
                                @endif
                            </td>
                            @if($SMSLog->is_refund == "Yes")
                                <td>{{$SMSLog->is_refund}}</td>
                                <td></td>
                            @else
                                <td></td>
                                <td>@if(array_key_exists($SMSLog->log_type, Config::get('constants.sms_array'))){{ Config::get('constants.sms_array')[$SMSLog->log_type] }}@else{{ 'N/A' }}@endif</td>
                            @endif
                            <td>{{ \Carbon\Carbon::parse($SMSLog->created_at)->format('M d,Y h:i A') }}</td>
                        </tr>
                        @foreach ($SMSLogs as $smslogsdetail)
                            @if($smslogsdetail->id == $SMSLog->id )
                                <tr class="{{$SMSLog->id}}" style="display: none">
                                    <td colspan="5">
                                        <pre>{{$SMSLog->text}}</pre>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            @else
                <tr>
                    <td colspan="4">No SMS log found.</td>
                </tr>
            @endif
        </table>
    </div>
</div>
<div class="modal-footer" id="modal-footer">
    <button type="button" class="btn default" data-dismiss="modal">Close</button>
</div>
<script>
    function toggle(id) {
        $("." + id).toggle();
    }
</script>
<script type="text/javascript">
    function resendSMS(smsId) {
        $('#clickRow' + smsId).hide();
        $('#spanRow' + smsId).text('Wait');
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: route('admin.appointments.resend_sms'),
            type: "PUT",
            data: {
                id: smsId
            },
            cache: false,
            success: function (response) {
                if (response.status == '1') {
                    $('#smsRow' + smsId).text('Yes');
                } else {
                    $('#clickRow' + smsId).show();
                    $('#spanRow' + smsId).text('No');
                }
            }
        });
    }
</script>