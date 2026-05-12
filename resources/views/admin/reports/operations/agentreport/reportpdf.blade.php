@inject('request', 'Illuminate\Http\Request')
        <!DOCTYPE html>
<html>
<head>
    <link href="{{ url('metronic/assets/global/css/generic-style.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/css/print-page.css') }}" rel="stylesheet" type="text/css"/>
</head>
<body>
<div class="invoice-pdf">
    <table>
        <tr>
            <td>
                <table>
                    <tr>
                        <td>
                            <img class="logo" src="{{ public_path('allura-logo2.jpeg') }}"
                                 class="img-responsive" alt="" style="width:235px"/>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="padding-left: 450px;">
                <table style="float: right;">
                    <tr>
                        <td style="width: 70px;">Name</td>
                        <td>Agent Report</td>
                    </tr>
                    <tr>
                        <td style="width: 70px;">Duration</td>
                        <td>From <strong>{{ $start_date }}</strong> to <strong>{{ $end_date }}</strong></td>
                    </tr>
                    <tr>
                        <td style="width: 70px;">Date</td>
                        <td><strong>{{ Carbon\Carbon::now()->format('Y-m-d') }}</strong></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <table class="table">
        <tr class="shdoc-header">
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
                    <td>{{$count++}}</td>
                    <td>{{$reportsingle['schedule_date']}}</td>
                    <td>{{$reportsingle['id']}}</td>
                    <td>{{$reportsingle['client_name']}}</td>
                    <td>{{$reportsingle['appointment_type']}}</td>
                    <td>{{$reportsingle['doctor_name']}}</td>
                    <td>{{$reportsingle['service']}}</td>
                    <td>{{$reportsingle['appointment_status_parent']}}</td>
                    <td>{{$reportsingle['appointment_status_child']}}</td>
                </tr>
            @endforeach


                <div class="pt-4 border-top  all-sections section-states" style="margin-top: 20px;">

                    @if(isset($locationData) && count($locationData) > 0)
                        @foreach($locationData as $key => $location)

                            <div class="col-md-6 mb-3">
                                <h3 class="">{{$key}}</h3>

                                <table class="table border">
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
</div>

</body>
</html>
