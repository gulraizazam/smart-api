@inject('request', 'Illuminate\Http\Request')
        <!DOCTYPE html>
<html>
<head>
    <style>
        .invoice-pdf {
            width: 100%;
        }

        .date {
            text-align: right;
        }

        .logo {
            width: 200px;
            text-align: left;
            background-color: #000000;
            padding: 12px 22px;
            border-radius: 4px;
        }

        table {
            font-family: arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            margin-top: 30px;
        }

        .table {
            width: 100%;
        }

        .table th {
            border: 1px solid #dddddd;
            text-align: left;
            padding: 8px;
        }

        td, th {
            text-align: left;
            font-size: 12px;
        }

        .table td, .table th {
            text-align: left;
            padding: 5px;
            font-size: 12px;
        }

        table.table tr td {
            padding: 12px 5px;
        }

        table.table tr:first-child {
            background-color: #fff;
        }

        .table tr:nth-child(odd) {
            background-color: #dddddd;
        }

        .shdoc-header {
            background: #364150;
            color: #fff;
        }
    </style>
{{--    <link href="{{ url('metronic/assets/global/css/override.css') }}" rel="stylesheet" type="text/css"/>--}}
</head>
<body>
<div class="invoice-pdf">
    <table>
        <tr>
            <td>
                <table>
                    <tr>
                        <td>
                            <img class="logo" src="{{ public_path('allura-logo1.jpeg') }}"
                                 class="img-responsive" alt="" style="width: 235px;"/>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="padding-left: 450px;">
                <table style="float: right;">
                    <tr>
                        <td style="width: 70px;">Name</td>
                        <td>Conversion Report</td>
                    </tr>
                    <tr>
                        <td style="width: 70px;">Duration</td>
                        <td>From:&nbsp;<strong>{{ $start_date }}</strong>&nbsp;To:&nbsp;<strong>{{ $end_date }}</strong>
                        </td>
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
            <th>City</th>
            <th>Location</th>
        </tr>
        @php
            $total = 0;
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
        <tr class="shdoc-header" style="background-color: rgba(54, 65, 80, 1); padding: 0.75rem; vertical-align: top; border-top: 1px solid #EBEDF3;">
            <td style="color: #fff;">Total</td>
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
        <tr class="shdoc-header" style="background-color: rgba(54, 65, 80, 1); padding: 0.75rem; vertical-align: top; border-top: 1px solid #EBEDF3;">
            <td style="color: #fff;">Total Count</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td style="text-align: right; color: #fff;">{{ count($report_data) }}</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr class="shdoc-header" style="background-color: rgba(54, 65, 80, 1); padding: 0.75rem; vertical-align: top; border-top: 1px solid #EBEDF3;">
            <td style="color: #fff;">Converted Count</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td style="text-align: right; color: #fff;">{{ $count }}</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr class="shdoc-header" style="background-color: rgba(54, 65, 80, 1); padding: 0.75rem; vertical-align: top; border-top: 1px solid #EBEDF3;">
            <td style="color: #fff;">Converted Ration</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td style="text-align: right; color: #fff;">{{ $count > 0 ? number_format($count / count($report_data) * 100, 2) : 0}} %</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr class="shdoc-header" style="background-color: rgba(54, 65, 80, 1); padding: 0.75rem; vertical-align: top; border-top: 1px solid #EBEDF3;">
            <td style="color: #fff;">Conversion Average</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td style="text-align: right; color: #fff;">{{ $total > 0 ? number_format($total / $count, 2) : 0}}</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </table>
</div>

</body>
</html>
