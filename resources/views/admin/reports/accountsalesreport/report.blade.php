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
            <h1>{{ 'Account Sales Report'  }}</h1>
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
                    <img src="{{asset('allura-logo1.jpeg')}}" style="height: 180px;">
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
                    <th>Invoice No.</th>
                    <th>Centre</th>
                    <th>Service</th>
                    <th>Patient</th>
                    <th>Created by</th>
                    <th>Service Price</th>
                    <th>Discount Name</th>
                    <th>Discount Type</th>
                    <th>Discount Amount</th>
                    <th>Amount</th>
                    <th>Tax</th>
                    <th>Tax Value</th>
                    <th>Total Amount</th>
                    <th>Is Exclusive</th>
                    <th>Payment Date</th>
                    </thead>
                    <tbody>
                    @if(count($reportData))
                        <?php $grandserviceprice = 0; $totalAmount = 0 ; $totalTaxAmount = 0 ;?>
                        @foreach($reportData as $reportRow)
                            <tr>
                                <td style="text-align: center;">{{ $reportRow->id }}</td>
                                <td>{{ (array_key_exists($reportRow->location_id, $filters['locations'])) ? $filters['locations'][$reportRow->location_id]->name : '-' }}</td>
                                <td>{{ (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->name : '-' }}</td>
                                <td>{{ $reportRow->patient->name }}</td>
                                <td>{{ $reportRow->user->name }}</td>
                                <td style="text-align: right;">
                                    <?php
                                    $grandserviceprice += (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->price : 0;
                                    echo number_format((array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->price : 0);
                                    ?>
                                </td>
                                <td>{{ (array_key_exists($reportRow->discount_id, $filters['discounts'])) ? $filters['discounts'][$reportRow->discount_id]->name : '-' }}</td>
                                <td>{{ $reportRow->discount_type==null?'-':$reportRow->discount_type }}</td>
                                <td style="text-align: right;">{{ number_format( $reportRow->discount_price==null?0:$reportRow->discount_price,2) }}</td>
                                <td style="text-align: right;">
                                    <?php
                                    $totalAmount += $reportRow->tax_exclusive_serviceprice == null ? 0 : $reportRow->tax_exclusive_serviceprice ;
                                    echo number_format($reportRow->tax_exclusive_serviceprice==null? 0:$reportRow->tax_exclusive_serviceprice,2)
                                    ?>
                                </td>
                                <td> {{ $reportRow->tax_percentage.'%' }}</td>
                                <td style="text-align: right"> {{ number_format( $reportRow->tax_price==null?0:$reportRow->tax_price, 2 ) }} </td>
                                <td style="text-align: right">
                                    <?php
                                    $totalTaxAmount += $reportRow->tax_including_price==null ? 0 : $reportRow->tax_including_price ;
                                    echo number_format( $reportRow->tax_including_price==null?0: $reportRow->tax_including_price , 2)
                                    ?>
                                </td>
                                <td>{{ ($reportRow->is_exclusive) ? 'Yes' : 'No' }}</td>
                                <td>{{ ($reportRow->created_at) ? \Carbon\Carbon::parse($reportRow->created_at, null)->format('M j, Y') . ' at ' . \Carbon\Carbon::parse($reportRow->created_at, null)->format('h:i A') : '-' }}</td>
                            </tr>
                        @endforeach

                        <tr style="background: #364150;color: #fff; font-weight: bold">
                            <td style="text-align: center; color: #fff;">Total</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td style="text-align: right;color: #fff;">{{ number_format( $grandserviceprice, 2) }}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td style="text-align: right;color: #fff;"> {{ number_format( $totalAmount , 2) }} </td>
                            <td></td>
                            <td></td>
                            <td style="text-align: right;color: #fff;"> {{ number_format( $totalTaxAmount, 2 ) }}</td>
                            <td></td>
                            <td></td>
                        </tr>

                    @else
                        <tr>
                            <td colspan="16" align="center">No record round.</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="clear clearfix"></div>
    <!-- Liabilities and Assets -->
    <script src="{{ url('js/admin/scrollbar/scrollbardev.js') }}" type="text/javascript"></script>
</div>
