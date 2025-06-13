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
            <h1>{{ 'Agent Report' }}</h1>
        </div>
    </div>
</div>
<div class="invoice-pdf">
    <div class="sn-table-head">
        <div class="print-logo">
            <img style="width:235px" src="https://crm2.cutera.pk/public/assets/media/new_logo.png" alt=""/>
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
            <th>Sr#</th>
            <th>Scheduled Date</th>
            <th>Client id</th>
            <th>Client Name</th>
            <th>Appointment Type</th>
            <th>Practitioner</th>
            <th>Service</th>
            <th>Appointment Status</th>
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
                </tr>
            @endforeach


                <div class="pt-4 border-top" style="margin-top: 20px;">

                    @if(isset($locationData) && count($locationData) > 0)
                        @foreach($locationData as $key => $location)

                            <div class="col-md-6 mb-3" style="margin-top: 20px;">


                                <table class="table border">
                                    <thead>
                                    <tr>
                                        <h3 class="">{{$key}}</h3>
                                    </tr>
                                    </thead>
                                    <thead>
                                    <tr class="">
                                        <td class="bg-light">Consultation Booked</td>
                                        <td class="bg-light" style="text-align:right;">{{$location['consultantbooked'] ?? 0}}</td>
                                    </tr>
                                    <tr class="">
                                        <td class="border-top bg-light" style="">Consultation Arrived</td>
                                        <td class="border-top bg-light" style="text-align:right;">{{$location['consultantarrived'] ?? 0}}</td>
                                    </tr>

                                    @if(isset($location['consultantbooked']) && $location['consultantbooked'] > 0)
                                        <tr class="">
                                            <td class="border-top bg-light" style="">Consultation Arrival Ratio</td>
                                            <td class="border-top bg-light" style="text-align:right;">
                                                <?php
                                                if (isset($location['consultantarrived']) && isset($location['consultantbooked'])) {
                                                    echo number_format(($location['consultantarrived'] / $location['consultantbooked']) * 100, 2) . '%';
                                                } else {
                                                    echo '00.00 %';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    @endif
                                    </thead>
                                </table>

                            </div>

                        @endforeach
                    @endif
                </div>

        @else
            <tr>
                <td colspan="12" align="center">No record round.</td>
            </tr>
        @endif
    </table>
</div>

<script>

    window.print();
    setTimeout(function () { window.close(); }, 700);

</script>

</body>
</html>
