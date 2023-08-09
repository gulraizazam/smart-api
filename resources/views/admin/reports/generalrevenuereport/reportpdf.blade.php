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
                            <img class="logo" src="{{ asset('assets/media/new_logo.png') }}"
                                 class="img-responsive" alt="" style="width:235px"/>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="padding-left: 450px;">
                <table style="float: right;">
                    <tr>
                        <td style="width: 70px;">Name</td>
                        <td>Sales Detail Report</td>
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

    @php
        $total_revenue_cash_location = 0;
        $total_revenue_card_location = 0;
        $total_revenue_bank_location = 0;
        $total_refund_location = 0;
    @endphp

    <table class="table">
        <tr class="shdoc-header">
            <th>ID</th>
            <th>Patient Name</th>
            <th>Transaction type</th>
            <th>Cash</th>
            <th>Card</th>
            <th>Bank/Wire Transfer</th>
            <th>Refund/Out</th>
            <th>Created At</th>
        </tr>
        @if($report_data)
            @foreach($report_data as $reportlocation)
                <tr>
                    <td>{{$reportlocation['name']}}</td>
                    <td>{{$reportlocation['city']}}</td>
                    <td>{{$reportlocation['region']}}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                @foreach($reportlocation['revenue_data'] as $reportRow)

                    @php
                        $total_revenue_cash_location += $reportRow['revenue_cash_in']?$reportRow['revenue_cash_in']:0;
                        $total_revenue_card_location += $reportRow['revenue_card_in']?$reportRow['revenue_card_in']:0;
                        $total_revenue_bank_location += $reportRow['revenue_bank_in']?$reportRow['revenue_bank_in']:0;
                        $total_refund_location += $reportRow['refund_out']?$reportRow['refund_out']:0;
                    @endphp

                    <tr>
                        <td>{{ $reportRow['patient_id'] }}</td>
                        <td>{{$reportRow['patient']}}</td>
                        <td>{{$reportRow['transtype']}}</td>
                        <td>@if($reportRow['revenue_cash_in'])
                                {{number_format($reportRow['revenue_cash_in'],2)}}
                            @endif
                        </td>
                        <td>
                            @if($reportRow['revenue_card_in'])
                                {{number_format($reportRow['revenue_card_in'],2)}}
                            @endif
                        </td>
                        <td>
                            @if($reportRow['revenue_bank_in'])
                                {{number_format($reportRow['revenue_bank_in'],2)}}
                            @endif
                        </td>
                        <td>
                            @if($reportRow['refund_out'])
                                {{number_format($reportRow['refund_out'],2)}}
                            @endif
                        </td>
                        <td>{{$reportRow['created_at']}}</td>
                    </tr>
                @endforeach
                <tr style="background: #364150;color: #fff;">
                    <td>{{$reportlocation['name']}}</td>
                    <td>Total</td>
                    <td></td>
                    <td>{{number_format($total_revenue_cash_location,2)}}</td>
                    <td>{{number_format($total_revenue_card_location,2)}}</td>
                    <td>{{number_format($total_revenue_bank_location,2)}}</td>
                    <td>{{number_format($total_refund_location,2)}}</td>
                    <td>{{number_format(($total_revenue_cash_location+$total_revenue_card_location+$total_revenue_bank_location)-$total_refund_location,2)}}</td>
                </tr>

                @php
                    $total_revenue_cash_location = 0;
                    $total_revenue_card_location = 0;
                    $total_revenue_bank_location = 0;
                    $total_refund_location = 0;
                @endphp

            @endforeach
        @else
            <tr>
                <td colspan="12" align="center">No record round.</td>
            </tr>
        @endif
    </table>
    <table class="table">
        <tr>
            <td></td>
            <td></td>
        </tr>
        <tr style="font-weight: bold">
            <td>Cash </td>
            <td>{{number_format($total_revenue_cash_in,2)}}</td>
        </tr>
        <tr style="font-weight: bold">
            <td>Card</td>
            <td>{{number_format($total_revenue_card_in,2)}}</td>
        </tr>
        <tr style="font-weight: bold">
            <th>Bank/Wire Transfer</th>
            <td>{{number_format($total_revenue_bank_in,2)}}</td>
        </tr>
        <tr>
            <td>Total Revenue</td>
            <td>{{number_format($total_revenue,2)}}</td>
        </tr>
        <tr style="font-weight: bold">
            <td>Refund</td>
            <td>{{number_format($total_refund,2)}}</td>
        </tr>
        <tr style="font-weight: bold">
            <td>In Hand Balance</td>
            <td>{{number_format(($total_revenue-$total_refund),2)}}</td>
        </tr>
    </table>
</div>
</body>
</html>
