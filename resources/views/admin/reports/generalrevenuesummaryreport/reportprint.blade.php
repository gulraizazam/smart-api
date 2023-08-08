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
            <h1>{{ 'General Revenue Summary Report' }}</h1>
        </div>
    </div>
</div>
<div class="invoice-pdf">
    <div class="sn-table-head">
        <div class="print-logo">
            <img src="{{ asset('assets/media/new_logo.png') }}" style="width:235px" alt=""/>
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
            <th>Centre</th>
            <th>City</th>
            <th>Region</th>
            <th>Cash </th>
            <th>Card </th>
            <th>Bank/Wire Transfer</th>
            <th>Refund/Out</th>
            <th>In Hand</th>
        </tr>
        @if($report_data)
            @foreach($report_data as $reportRow)
                <tr>
                    <td>{{$reportRow['name']}}</td>
                    <td>{{$reportRow['city']}}</td>
                    <td>{{$reportRow['region']}}</td>
                    <td>{{number_format($reportRow['revenue_cash_in'],2)}}</td>
                    <td>{{number_format($reportRow['revenue_card_in'],2)}}</td>
                    <td>{{number_format($reportRow['revenue_bank_in'],2)}}</td>
                    <td>{{number_format($reportRow['refund_out'],2)}}</td>
                    <td>{{number_format($reportRow['in_hand'],2)}}</td>
                </tr>
            @endforeach
            <tr style="background:#364150;color: #fff;">
                <td style="font-weight: bold">Total</td>
                <td></td>
                <td></td>
                <td style="font-weight: bold">{{number_format($total_revenue_cash_in,2)}}</td>
                <td style="font-weight: bold">{{number_format($total_revenue_card_in,2)}}</td>
                <td style="font-weight: bold">{{number_format($total_revenue_bank_in,2)}}</td>
                <td style="font-weight: bold">{{number_format($total_refund,2)}}</td>
                <td style="font-weight: bold">{{number_format(($total_revenue_cash_in+$total_revenue_card_in+$total_revenue_bank_in)-$total_refund,2)}}</td>
            </tr>
        @else
            <tr>
                <td colspan="12" align="center">No record round.</td>
            </tr>
        @endif
    </table>
    <table class="table">
        <tr></tr>
        <tr>
            <td>Cash </td>
            <td>{{number_format($total_revenue_cash_in,2)}}</td>
        </tr>
        <tr>
            <td>Card </td>
            <td>{{number_format($total_revenue_card_in,2)}}</td>
        </tr>
        <tr>
            <td>Bank/Wire Transfer</td>
            <td>{{number_format($total_revenue_bank_in,2)}}</td>
        </tr>
        <tr>
            <td>Total Revenue</td>
            <td>{{number_format($total_revenue,2)}}</td>
        </tr>
        <tr>
            <td>Refund</td>
            <td>{{number_format($total_refund,2)}}</td>
        </tr>
        <tr>
            <td>In Hand Balance</td>
            <td>{{number_format(($total_revenue-$total_refund),2)}}</td>
        </tr>
    </table>
</div>

<script>

    window.print();
    setTimeout(function () { window.close(); }, 100);

</script>

</body>
</html>
