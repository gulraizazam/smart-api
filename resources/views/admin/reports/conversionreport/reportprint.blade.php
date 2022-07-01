@inject('request', 'Illuminate\Http\Request')
        <!DOCTYPE html>
<html>
<head>
    <link href="{{ url('metronic/assets/global/css/generic-style.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('assets/css/print-page.css') }}" rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/css/override.css') }}" rel="stylesheet" type="text/css"/>
</head>
<body>
<div class="sn-table-holder">
    <div class="sn-report-head">
        <div class="sn-title">
            <h1>{{ 'Conversion Report' }}</h1>
        </div>
    </div>
</div>
<div class="invoice-pdf">
    <div class="sn-table-head">
        <div class="print-logo">
            <img src="{{ asset('assets/media/logos/logo.svg') }}" height="80" alt=""/>
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
            <th>ID</th>
            <th>Doctor</th>
            <th>Date of Inquiry</th>
            <th>Client</th>
            <th>Appointment Type</th>
            <th>Service</th>
            <th>Converted</th>
            <th>Conversion Spend</th>
            <th>Conversion Date</th>
            <th>Region</th>
            <th>city</th>
            <th>Location</th>
        </tr>
        @php
            $total = 0 ;
            $count = 0;
        @endphp
        @if(count($report_data))
            @foreach($report_data as $appointment)
                @if($appointment['converted'] != '')
                    <tr>
                        <td>{{ $appointment['patient_id'] }}</td>
                        <td>{{$appointment['doctor']}}</td>
                        <td>{{ $appointment['doi']  }}</td>
                        <td>{{$appointment['client']}}</td>
                        <td>{{'Consultancy'}}</td>
                        <td>{{$appointment['service']}}</td>
                        <td>{{$appointment['converted']}}</td>
                        <td style="text-align: right">{{$appointment['conversion_spend']}}</td>
                        <td>{{ \Carbon\Carbon::parse($appointment['conversion_date'])->format('F j,Y')}}</td>
                        <td>{{$appointment['region']}}</td>
                        <td>{{$appointment['city']}}</td>
                        <td>{{$appointment['centre']}}</td>
                    </tr>
                    @php
                        $total += $appointment['conversion_spend']?$appointment['conversion_spend']:0 ;
                        $count++;
                    @endphp
                @endif
            @endforeach
        @else
            <tr>
                <td colspan="12" align="center">No Record Found.</td>
            </tr>
        @endif

        <tr class="shdoc-header">
            <td style="color: #fff">Total</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td style="text-align: right; color: #fff;">{{ number_format($total,2) }}</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr class="shdoc-header">
            <td style="color: #fff">Count</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td style="text-align: right ; color: #fff;">{{ $count }}</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </table>
</div>

<script>

    window.print();
    setTimeout(function () { window.close(); }, 800);

</script>

</body>
</html>
