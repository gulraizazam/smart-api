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

        table.table tr td {
            padding: 12px;
        }

        table.table tr:first-child {
            background-color: #fff;
        }

        .table tr:nth-child(odd) {
            background-color: #dddddd;
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
                            <img class="logo" src="{{ asset('assets/media/new_logo.png') }}"
                                 class="img-responsive" alt="" style="width:235px" />
                        </td>
                    </tr>
                </table>
            </td>
            <td style="padding-left: 450px;">
                <table style="float: right;">
                    <tr>
                        <td style="width: 70px;">Name</td>
                        <td>General Revenue Summary Report</td>
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
        <tr style="background: #364150; color: #fff;">
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
            <tr style="background: #364150; color: #fff;">
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
</div>
<table class="table">
    <tr>
        <td></td>
        <td></td>
    </tr>
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
</body>
</html>
