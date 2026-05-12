@inject('request', 'Illuminate\Http\Request')
        <!DOCTYPE html>
<html>
<head>
    <link href="{{ url('assets/css/print-page.css') }}" rel="stylesheet" type="text/css"/>
</head>
<body>
<div class="sn-table-holder">
    <div class="sn-report-head">
        <div class="sn-title">
            <h1>{{ 'Collection By Service' }}</h1>
        </div>
    </div>
</div>
<div class="invoice-pdf">
    <div class="sn-table-head">
        <div class="print-logo">
            <img src="{{ public_path('allura-logo2.jpeg') }}" style="width: 235px;" alt=""/>
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
    <div class="table-wrapper" id="topscroll">
        <table class="table">
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
                <td style="font-weight: bold;color: #fff;">Grand Total</td>
                <td style="font-weight: bold;color: #fff;">{{number_format($grand,2)}}</td>
            </tr>
        @else
            <tr>
                <td colspan="12" align="center">No record round.</td>
            </tr>
        @endif
    </table>
</div>
</div>

<script>

    window.print();
    setTimeout(function () { window.close(); }, 100);

</script>

</body>
</html>
