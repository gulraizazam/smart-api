@inject('request', 'Illuminate\Http\Request')
        <!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link href="{{ url('assets/css/print-page.css') }}" rel="stylesheet" type="text/css"/>
</head>
<body>

<div class="sn-table-holder">
    <div class="sn-report-head">
        <div class="sn-title">
            <h1>{{ 'SALE SUMMARY DOCTORS WISE' }}</h1>
        </div>
    </div>
</div>
<div class="invoice-pdf">
    <div class="sn-table-head">
        <div class="print-logo">
            <img src="{{ public_path('allura-logo1.jpeg') }}" style="width: 235px;" alt=""/>
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
            <th width="20%">Doctor</th>
            <th>Service</th>
            <th>Total</th>
        </tr>
        @if(count($reportData))
            <?php $servicegrandtotal = 0;?>
            @foreach($reportData as $reportpackagedata)
                <tr style="background-color: #dddddd">
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
            <tr style="background: #364150;color: #fff; font-weight: bold">
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

<script>

    window.print();
    setTimeout(function () { window.close(); }, 100);

</script>

</body>
</html>
