@inject('request', 'Illuminate\Http\Request')
        <!DOCTYPE html>
<html>
<head>
    <link href="{{ url('metronic/assets/global/css/generic-style.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/css/print-page.css') }}" rel="stylesheet" type="text/css"/>
</head>
<body>
<div class="sn-table-holder">
    <div class="sn-report-head">
        <div class="sn-title">
            <h1>{{ 'DAR Report' }}</h1>
        </div>
    </div>
</div>
<div class="invoice-pdf">
    <div class="sn-table-head">
        <div class="print-logo">
            <img style="width:235px" src="https://crm2.cutera.pk/assets/media/new_logo.png" alt=""/>
        </div>
        <div class="print-time">
            <table class="dark-th-table table table-bordered">
                <tr>
                    <th width="25%">Duration</th>
                    <td>From {{ $start_date }} to {{ $end_date }}</td>
                </tr>
                <tr>
                    <th>Date</th>
                    <td>{{ Carbon\Carbon::now()->format('Y-m-d') }}</td>
                </tr>
            </table>
        </div>
    </div>
    <table class="table">
        <tr>
            <th colspan="10">Today's Appointments</th>
            <th colspan="6">Treatment Booked</th>
        </tr>

        <tr>
            <th>Sr#</th>
            <th>Scheduled Date</th>
            <th>Client id</th>
            <th>Client Name</th>
            <th>Appointment Type</th>
            <th>Practitioner</th>
            <th>Service</th>
            <th>Appointment Status Parent</th>
            <th>Appointment Status Child</th>
            {{--<th>--</th>
            <th>Scheduled Date</th>
            <th>Practitioner</th>
            <th>Appointment Type</th>
            <th>Service</th>
            <th>Appointment Status Parent</th>
            <th>Appointment Status Child</th>--}}
        </tr>
        @php $count = 1;$consultantbooked = 0;$treatmentbooked = 0;$consultantarrived = 0;$treatmentarrived = 0; @endphp
        @if(count($reportData))
            @foreach($reportData as $reportsingle)
                <tr>
                    @if($reportsingle['appointment_slug'] == 'consultancy')
                        <?php $consultantbooked++; ?>
                    @elseif($reportsingle['appointment_slug'] == 'treatment')
                        <?php $treatmentbooked++; ?>
                    @endif
                    @if($reportsingle['appointment_slug'] == 'consultancy' && $reportsingle['appointment_status_isarrived'] == '1')
                        <?php $consultantarrived++; ?>
                    @elseif($reportsingle['appointment_slug'] == 'treatment' && $reportsingle['appointment_status_isarrived'] == '1')
                        <?php $treatmentarrived++; ?>
                    @endif
                    <td>{{$count++}}</td>
                    <td>{{$reportsingle['schedule_date']}}</td>
                    <td>{{$reportsingle['id']}}</td>
                    <td>{{$reportsingle['client_name']}}</td>
                    <td>{{$reportsingle['appointment_type']}}</td>
                    <td>{{$reportsingle['doctor_name']}}</td>
                    <td>{{$reportsingle['service']}}</td>
                    <td>{{$reportsingle['appointment_status_parent']}}</td>
                    <td>{{$reportsingle['appointment_status_child']}}</td>
                    {{--<td>{{'-'}}</td>
                    @foreach($reportsingle['next_appointment_info'] as $next_appointment_info)
                        <td>{{$next_appointment_info['schedule_date']}}</td>
                        <td>{{$next_appointment_info['doctor_name']}}</td>
                        <td>{{$next_appointment_info['appointment_type']}}</td>
                        <td>{{$next_appointment_info['service']}}</td>
                        <td>{{$next_appointment_info['appointment_status_child']}}</td>
                        <td>{{$next_appointment_info['appointment_status_parent']}}</td>
                    @endforeach--}}
                </tr>
            @endforeach
            <tr class="shdoc-header">
                <th style="color: #fff">Consultation Booked</th>
                <th style="text-align:right;color: #fff">{{$consultantbooked}}</th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            <tr class="shdoc-header">
                <th style="color: #fff">Consultation Arrived</th>
                <th style="text-align:right;color: #fff">{{$consultantarrived}}</th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            <tr class="shdoc-header">
                <th style="color: #fff">New Consultation Converted</th>
                <th style="text-align:right;color: #fff">{{$newconsultant}}</th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            @if($consultantbooked>0)
                <tr class="shdoc-header">
                    <th style="color: #fff">Consultation Arrival Ratio</th>
                    <th style="text-align:right;color: #fff"><?php echo number_format(($consultantarrived / $consultantbooked) * 100, 2) . '%'?></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
            @endif
            @if($consultantarrived>0)
                <tr class="shdoc-header">
                    <th style="color: #fff">Consultation Conversion Ratio</th>
                    <th style="text-align:right;color: #fff"><?php echo number_format(($newconsultant / $consultantarrived) * 100, 2) . '%'?></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
            @endif
            <tr class="shdoc-header">
                <th style="color: #fff">Treatment Booked</th>
                <th style="text-align:right;color: #fff">{{$treatmentbooked}}</th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            <tr class="shdoc-header">
                <th style="color: #fff">Treatment Arrived</th>
                <th style="text-align:right;color: #fff">{{$treatmentarrived}}</th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            <tr class="shdoc-header">
                <th style="color: #fff">New Treatment Converted</th>
                <th style="text-align:right;color: #fff">{{$newtreatment}}</th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            @if($treatmentbooked>0)
                <tr class="shdoc-header">
                    <th style="color: #fff">Treatment Arrival Ratio</th>
                    <th style="text-align:right;color: #fff"><?php echo number_format(($treatmentarrived / $treatmentbooked) * 100, 2) . '%'?></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
            @endif
            @if($treatmentarrived>0)
                <tr class="shdoc-header">
                    <th style="color: #fff">Treatment Conversion Ratio</th>
                    <th style="text-align:right;color: #fff"><?php echo number_format(($newtreatment / $treatmentarrived) * 100, 2) . '%'?></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
            @endif
        @else
            <tr>
                <td colspan="12" align="center">No record round.</td>
            </tr>
        @endif
    </table>
</div>

<script>

    window.print();
    setTimeout(function () { window.close(); }, 100);

</script>

</body>
</html>
