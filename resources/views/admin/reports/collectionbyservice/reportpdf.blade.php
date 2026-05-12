<!DOCTYPE html>
<html>
<head>
    <link href="{{ url('metronic/assets/global/css/generic-style.css') }}" rel="stylesheet" type="text/css" />
    <style>
        .date {
            text-align: right;
        }

        .logo {
            width: 200px;
            text-align: left;
        }

        table {
            font-family: arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            margin-top: 30px;
        }

        .table th {
            /*border: 1px solid #dddddd;*/
            text-align: left;
            padding: 8px;
        }

        td, th {
            text-align: left;
            padding: 8px;
            font-size: 12px;
        }

        .table td, .table th {
            text-align: left;
            padding: 8px;
            font-size: 12px;
        }
        .shdoc-header{
            background: #364150;
            color: #fff;
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
                            <img class="logo" src="{{ public_path('allura-logo2.jpeg') }}"
                                 class="img-responsive" alt="" style="width: 235px;"/>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="padding-left: 450px;">
                <table style="float: right;">
                    <tr>
                        <td style="width: 70px;">Name</td>
                        <td>Collection By Serivce</td>
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
    <table class="table table-default">
        <tr class="shdoc-header">
            <th>Service</th>
            <th>Total</th>
        </tr>
        @if(count($reportData))
            <?php $grand = 0; ?>
            @foreach($reportData as $reportData)
                @if($reportData['amount'])
                    <tr>
                        <td>{{$reportData['name']}}</td>
                        <td>{{number_format($reportData['amount'],2)}}</td>
                        @php
                            $grand +=$reportData['amount'];
                        @endphp
                    </tr>
                @endif
            @endforeach
            <tr style="background: #364150;color: #fff;">
                <td style="font-weight: bold">Grand Total</td>
                <td style="font-weight: bold">{{number_format($grand,2)}}</td>
            </tr>
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
