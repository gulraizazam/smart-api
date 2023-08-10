@inject('request', 'Illuminate\Http\Request')
@if($request->get('medium_type') != 'web')
    @if($request->get('medium_type') == 'pdf')
        @include('partials.pdf_head')
    @else
        @include('partials.head')
    @endif
    <style type="text/css">
        @page {
            margin: 10px 20px;
        }

        @media print {
            table {
                font-size: 12px;
            }

            .tr-root-group {
                background-color: #F3F3F3;
                color: rgba(0, 0, 0, 0.98);
                font-weight: bold;
            }

            .tr-group {
                font-weight: bold;
            }

            .bold-text {
                font-weight: bold;
            }

            .error-text {
                font-weight: bold;
                color: #FF0000;
            }

            .ok-text {
                color: #006400;
            }
        }
    </style>
@endif
<div class="sn-table-holder">
    <div class="sn-report-head">
        <div class="sn-title">
            <h1>{{ 'General Revenue Summary Report'  }}</h1>
        </div>
        <div class="sn-buttons">
            @if($request->get('medium_type') == 'web')
                <a class="btn sn-white-btn btn-default" href="javascript:;" onclick="printReport('excel');">
                    <i class="fa fa-file-excel-o"></i><span>Excel</span>
                </a>
                <a class="btn sn-white-btn btn-default" href="javascript:;" onclick="printReport('pdf');">
                    <i class="fa fa-file-pdf-o"></i><span>PDF</span>
                </a>
                <a class="btn sn-white-btn btn-default" href="javascript:;" onclick="printReport('print');">
                    <i class="fa fa-print"></i><span>Print</span>
                </a>
            @endif
        </div>
    </div>
</div>
<div class="panel-body sn-table-body">
    <div class="bordered">
        <div class="sn-table-head">
            <div class="row">
                <div class="col-md-2">
                    <img style="width: 180px;" src="{{asset('logo_final.png')}}" >
                </div>
                <div class="col-md-6">&nbsp;</div>
                <div class="col-md-4">
                    <table class="dark-th-table table table-bordered">
                        <tr>
                            <th width="25%">Duration</th>
                            <td>From {{ $start_date }} to {{ $end_date }}</td>
                        </tr>
                        <tr>
                            <th>Date</th>
                            <td>{{ \Carbon\Carbon::now()->format('Y-m-d') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="table-wrapper" id="topscroll">
                <table class="table">
                    <thead>
                    <th>Centre</th>
                    <th>City</th>
                    <th>Region</th>
                    <th>Cash </th>
                    <th>Card </th>
                    <th>Bank/Wire Transfer</th>
                    <th>Refund/Out</th>
                    <th>In Hand</th>
                    </thead>
                    <tbody>
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
                            <td style="font-weight: bold;color: #fff;">Total</td>
                            <td></td>
                            <td></td>
                            <td style="font-weight: bold;color: #fff;">{{number_format($total_revenue_cash_in,2)}}</td>
                            <td style="font-weight: bold;color: #fff;">{{number_format($total_revenue_card_in,2)}}</td>
                            <td style="font-weight: bold;color: #fff;">{{number_format($total_revenue_bank_in,2)}}</td>
                            <td style="font-weight: bold;color: #fff;">{{number_format($total_refund,2)}}</td>
                            <td style="font-weight: bold;color: #fff;">{{number_format(($total_revenue_cash_in+$total_revenue_card_in+$total_revenue_bank_in)-$total_refund,2)}}</td>

                        </tr>
                    @else
                        <tr>
                            <td colspan="12" align="center">No record round.</td>
                        </tr>
                    @endif
                    </tbody>
                </table>

                <table class="table">
                    <tr>
                        <th>Cash </th>
                        <td>{{number_format($total_revenue_cash_in,2)}}</td>
                    </tr>
                    <tr>
                        <th>Card </th>
                        <td>{{number_format($total_revenue_card_in,2)}}</td>
                    </tr>
                    <tr>
                        <th>Bank/Wire Transfer</th>
                        <td>{{number_format($total_revenue_bank_in,2)}}</td>
                    </tr>
                    <tr>
                        <th>Total Revenue</th>
                        <td>{{number_format($total_revenue,2)}}</td>
                    </tr>
                    <tr>
                        <th>Refund</th>
                        <td>{{number_format($total_refund,2)}}</td>
                    </tr>
                    <tr>
                        <th>In Hand Balance</th>
                        <td>{{number_format(($total_revenue-$total_refund),2)}}</td>

                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="clear clearfix"></div>

</div>
