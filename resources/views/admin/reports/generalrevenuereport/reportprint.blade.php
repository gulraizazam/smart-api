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
            <h1>{{ 'Sales Detail Report' }}</h1>
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

    @php
        $total_revenue_cash_location = 0;
        $total_revenue_card_location = 0;
        $total_revenue_bank_location = 0;
        $total_refund_location = 0;
    @endphp

    <table class="table">
        <tr>
            <th>ID</th>
            <th>Patient Name</th>
            <th>Transaction type</th>
            <th>Cash </th>
            <th>Card </th>
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
                        <td> {{ $reportRow['patient_id'] }}</td>
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
                <tr style="background: #364150;color: #fff;font-weight: bold">
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
