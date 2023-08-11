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
            <h1>{{ 'Sales Detail Report' }}</h1>
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
                $total_refund_cash_location = 0;
                $total_refund_card_location = 0;
                $total_refund_bank_location = 0;
                $total_refund_location = 0;
            @endphp

            <div class="table-wrapper" id="topscroll">
                <table class="table">
                    <thead>
                    <th>ID</th>
                    <th>Patient Name</th>
                    <th>Transaction type</th>
                    <th>Cash</th>
                    <th>Card </th>
                    <th>Bank/Wire Transfer</th>
                    
                    <th>Created At</th>
                    </thead>
                    <tbody>
                    @if($report_data)
                        @foreach($report_data as $reportlocation)
                        <tr style="background:#2fa0d3;color: #fff;">
                                <td style="color: #fff;">{{$reportlocation['name']}}</td>
                                <td style="color: #fff;">{{$reportlocation['city']}}</td>
                                <td style="color: #fff;">{{$reportlocation['region']}}</td>
                                <td style="color: #fff;"></td>
                                <td style="color: #fff;"></td>
                                <td style="color: #fff;"></td>
                                <td style="color: #fff;"></td>
                            @foreach($reportlocation['revenue_data'] as $reportRow)

                                @php
                                    $total_revenue_cash_location += $reportRow['revenue_cash_in']?$reportRow['revenue_cash_in']:0;
                                    $total_revenue_card_location += $reportRow['revenue_card_in']?$reportRow['revenue_card_in']:0;
                                    $total_revenue_bank_location += $reportRow['revenue_bank_in']?$reportRow['revenue_bank_in']:0;
                                    $total_refund_cash_location += $reportRow['refund_cash_in']?$reportRow['refund_cash_in']:0;
                                    $total_refund_card_location += $reportRow['refund_card_in']?$reportRow['refund_card_in']:0;
                                    $total_refund_bank_location += $reportRow['refund_bank_in']?$reportRow['refund_bank_in']:0;
                                    $total_refund_location += $reportRow['refund_out']?$reportRow['refund_out']:0;
                                @endphp

                                <tr>
                                    <td>{{$reportRow['patient_id'] }}</td>
                                    <td>{{$reportRow['patient']}}</td>
                                    <td>{{$reportRow['transtype']}}</td>
                                    <td>@if($reportRow['revenue_cash_in'])
                                     {{number_format($reportRow['revenue_cash_in'],2)}}
                                        @endif
                                        @if($reportRow['refund_cash_in'])
                                         ({{number_format($reportRow['refund_cash_in'],2)}})
                                        @endif
                                    </td>
                                    <td>
                                        @if($reportRow['revenue_card_in'])
                                         {{number_format($reportRow['revenue_card_in'],2)}}
                                        @endif
                                        @if($reportRow['refund_card_in'])
                                         ({{number_format($reportRow['refund_card_in'],2)}})
                                        @endif
                                    </td>
                                    <td>
                                        @if($reportRow['revenue_bank_in'])
                                         {{number_format($reportRow['revenue_bank_in'],2)}}
                                        @endif
                                        @if($reportRow['refund_bank_in'])
                                         ({{number_format($reportRow['refund_bank_in'],2)}})
                                        @endif
                                    </td>
                                   
                                    <td>{{$reportRow['created_at']}}</td>
                                </tr>
                            @endforeach
                            @php
                            $t_cash = $total_revenue_cash_location - $total_refund_cash_location;
                            $t_card = $total_revenue_card_location - $total_refund_card_location;
                            $t_bank = $total_revenue_bank_location - $total_refund_bank_location;
                            @endphp                            
                                <tr style="background:#364150;color: #fff;">
                                <td style="color: #fff;"> {{$reportlocation['name']}}</td>
                                <td style="color: #fff;">Total</td>
                                <td style="color: #fff;"></td>
                                <td style="color: #fff;"> {{number_format($total_revenue_cash_location,2)}}</td>
                                <td style="color: #fff;"> {{number_format($total_revenue_card_location,2)}}</td>
                                <td style="color: #fff;"> {{number_format( $total_revenue_bank_location,2)}}</td>
                                
                                <td style="color: #fff;"></td>
                            </tr>

                            @php
                                $total_revenue_cash_location = 0;
                                $total_revenue_card_location = 0;
                                $total_revenue_bank_location = 0;
                                $total_refund_location = 0;
                                $t_revenue = $t_cash + $t_card + $t_bank;
                                $inhandBalance = $total_revenue -$total_refund;
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
                        <th>Cash </th>
                        <td> {{number_format( $t_cash,2)}}</td>
                    </tr>
                    <tr>
                        <th>Card </th>
                        <td> {{number_format( $t_card,2)}}</td>
                    </tr>
                    <tr>
                        <th>Bank/Wire Transfer</th>
                        <td> {{number_format( $t_bank,2)}}</td>
                    </tr>
                    <tr>
                        <th>Gross Sales</th>
                        <td> {{number_format($total_revenue,2)}}</td>
                    </tr>
                    <tr>
                        <th>Refund Out <br>
                        <table class="table table-sm border" style="max-width: 350px; margin:14px auto 10px;">
                            <tbody>
                                <tr>
                                    <th class="pl-3" style="color: #8b8b8b;">Cash</th>
                                    <td style="font-weight:400;color: #8b8b8b;"> {{$total_refund_cash_location}}</td>
                                </tr>
                                <tr>
                                    <th class="pl-3" style="color: #8b8b8b;">Card</th>
                                    <td style="font-weight:400;color: #8b8b8b;"> {{$total_refund_card_location}}</td>
                                </tr>
                                <tr>
                                    <th class="pl-3" style="color: #8b8b8b;">Bank/Wire Transfer</th>
                                    <td style="font-weight:400;color: #8b8b8b;"> {{$total_refund_bank_location}}</td>
                                </tr>
                            </tbody>
                        </table>
                        </th>
                        <td> ({{number_format($total_refund,2)}})
                    </td>
                    </tr>
                    
                    <tr>
                        <th>Net Sales</th>
                        <td> {{number_format($inhandBalance,2)}}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="clear clearfix"></div>

</div>
