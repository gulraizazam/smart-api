@inject('request', 'Illuminate\Http\Request')
        <!DOCTYPE html>
<html>
<head>
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
            border: 1px solid #dddddd;
            text-align: left;
            padding: 8px;
        }
        td, th {
            text-align: left;
            font-size: 12px;
            padding: 8px;
        }
        .table td, .table th {
            text-align: left;
            padding: 5px;
            font-size: 12px;
        }
        table.table tr td{
            padding: 12px;
        }
        table.table tr:first-child{
            background-color: #fff;
        }
        .table tr:nth-child(odd) {
            background-color: #dddddd;
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
                            <td >
                                <img class="logo" src="{{ public_path('allura-logo1.jpeg') }}" class="img-responsive" alt="" style="width: 235px;"/>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="padding-left: 450px;">
                    <table style="float: right;">
            <tr>
                <td style="width: 70px;">Name</td>
                <td>Sale Summary Doctors Wise</td>
            </tr>
            <tr>
                <td style="width: 70px;">Duration</td>
                <td>From:&nbsp;<strong>{{ $start_date }}</strong>&nbsp;To:&nbsp;<strong>{{ $end_date }}</strong></td>
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
            <th width="20%">Doctor</th>
            <th>Service</th>
            <th>Total</th>
        </tr>
        @if(count($reportData))
            <?php $servicegrandtotal = 0;?>
            @foreach($reportData as $reportpackagedata)
                <tr>
                    <td><?php echo $reportpackagedata['name']; ?></td>
                    <td></td>
                    <td></td>
                </tr>
                <?php $count = 0; $servicetotal = 0;?>
                @foreach($reportpackagedata['records'] as $reportRow )
                    <tr>
                        <td></td>
                        <td>{{$reportRow['name']}}</td>
                        <td>
                            <?php
                            $servicetotal += $reportRow['amount'];
                            echo number_format($reportRow['amount'], 2);
                            ?>
                        </td>
                    </tr>
                @endforeach
                <tr style="background-color:#3aaddc;color: #fff;">
                    <td><?php echo $reportpackagedata['name']; ?></td>
                    <td>Total</td>
                    <td>
                        <?php
                        $servicegrandtotal += $servicetotal;
                        echo number_format($servicetotal,2);
                        ?>
                    </td>
                </tr>
            @endforeach
            <tr class="shdoc-header">
                <td></td>
                <td>Grand Total</td>
                <td><?php echo number_format($servicegrandtotal,2); ?></td>
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
