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
            <h1>{{ 'General Revenue Detail Report' }}</h1>
        </div>
        <div class="sn-buttons">
            @if($request->get('medium_type') == 'web')
                <a class="btn sn-white-btn btn-default" href="javascript:;"
                   onclick="printReport('excel');">
                    <i class="fa fa-file-excel-o"></i><span>Excel</span>
                </a>
                <a class="btn sn-white-btn btn-default" href="javascript:;" onclick="printReport('pdf');">
                    <i class="fa fa-file-pdf-o"></i><span>PDF</span>
                </a>
                <a class="btn sn-white-btn btn-default" href="javascript:;"
                   onclick="printReport('print');">
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

            @php
                $total_revenue_cash_location = 0;
                $total_revenue_card_location = 0;
                $total_revenue_bank_location = 0;
                $total_refund_location = 0;
            @endphp

            <div class="table-wrapper" id="topscroll">
                <table class="table">
                    <thead>
                    <th>ID</th>
                    <th>Patient Name</th>
                    <th>Transaction type</th>
                    <th>Revenue Cash In</th>
                    <th>Revenue Card In</th>
                    <th>Revenue Bank/Wire In</th>
                    <th>Refund/Out</th>
                    <th>Created At</th>
                    </thead>
                    <tbody>
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
                                    <td>{{$reportRow['patient_id'] }}</td>
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

                            <tr style="background:#364150;color: #fff;">
                                <td style="color: #fff;">{{$reportlocation['name']}}</td>
                                <td style="color: #fff;">Total</td>
                                <td style="color: #fff;"></td>
                                <td style="color: #fff;">{{number_format($total_revenue_cash_location,2)}}</td>
                                <td style="color: #fff;">{{number_format($total_revenue_card_location,2)}}</td>
                                <td style="color: #fff;">{{number_format($total_revenue_bank_location,2)}}</td>
                                <td style="color: #fff;">{{number_format($total_refund_location,2)}}</td>
                                <td style="color: #fff;">{{number_format(($total_revenue_cash_location+$total_revenue_card_location+$total_revenue_bank_location)-$total_refund_location,2)}}</td>
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
                    </tbody>
                </table>

                <table class="table">
                    <tr>
                        <th>Revenue Cash In</th>
                        <td>{{number_format($total_revenue_cash_in,2)}}</td>
                    </tr>
                    <tr>
                        <th>Revenue Card In</th>
                        <td>{{number_format($total_revenue_card_in,2)}}</td>
                    </tr>
                    <tr>
                        <th>Revenue Bank/Wire In</th>
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
