@inject('request', 'Illuminate\Http\Request')
        <!DOCTYPE html>
<html>
<head>
    <link href="{{ url('metronic/assets/global/css/generic-style.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/css/print-page.css') }}" rel="stylesheet" type="text/css"/>
    <style>
        .logo {
            background-color: #000000;
            padding: 12px 22px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<div class="invoice-pdf">
    <table>
        <tr>
            <td>
                <table>
                    <tr>
                        <td>
                            <img  style="width:235px" class="logo" src="{{ public_path('allura-logo1.jpeg') }}"
                                 class="img-responsive" alt=""/>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="padding-left: 450px;">
                <table style="float: right;">
                    <tr>
                        <td style="width: 70px;">Name</td>
                        <td>Walkin Report</td>
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

            @if(isset($locationData) && count($locationData) > 0)
                    @foreach($locationData as $key => $location)

                        <div class="col-md-6 mb-3">
                            <h3 class="">{{$key}}</h3>

                            <table class="table border">
                                <thead>
                                <tr class="">
                                    <td class="bg-light">Total Walkin</td>
                                    <td class="bg-light" style="text-align:right;">{{$location['walkin'] ?? 0}}</td>
                                </tr>

                                </thead>
                            </table>

                        </div>

                    @endforeach
                @endif

        @else
            <tr>
                <td colspan="12" align="center">No record round.</td>
            </tr>
        @endif
    </table>
</div>

</body>
</html>
