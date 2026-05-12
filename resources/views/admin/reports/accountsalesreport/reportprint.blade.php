@inject('request', 'Illuminate\Http\Request')
        <!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link href="{{ url('assets/css/print-page.css') }}" rel="stylesheet" type="text/css"/>
    <link href="//fonts.googleapis.com/css?family=Open+Sans:400,300,600,700&subset=all" rel="stylesheet" type="text/css" />
</head>
<body>
<div class="sn-table-holder">
    <div class="sn-report-head">
        <div class="sn-title">
            <h1>{{ 'Center Performance Stats By Service Type Report' }}</h1>
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

    <table class="table">
        <tr>
            <th>Invoice No.</th>
            <th>Centre</th>
            <th>Service</th>
            <th>Patient</th>
            <th>Gender</th>

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
        </tr>
        @if(count($reportData))
            <?php $grandserviceprice = 0; $totalAmount = 0 ; $totalTaxAmount = 0 ;?>
            @foreach($reportData as $reportRow)
                <tr>
                    <td style="text-align: center;">{{ $reportRow->id }}</td>
                    <td>{{ (array_key_exists($reportRow->location_id, $filters['locations'])) ? $filters['locations'][$reportRow->location_id]->name : '-' }}</td>
                    <td>{{ (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->name : '-' }}</td>
                    <td>{{ $reportRow->patient->name }}</td>
                    <td>{{ $reportRow->gender ?? '-' }}</td>
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

            <tr style="background: #364150;color: #fff;">
                <td style="text-align: center; font-weight: bold">Total</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td style="text-align: right;font-weight: bold">{{ number_format( $grandserviceprice, 2) }}</td>
                <td></td>
                <td></td>
                <td></td>
                <td style="text-align: right;font-weight: bold"> {{ number_format( $totalAmount , 2) }} </td>
                <td></td>
                <td></td>
                <td style="text-align: right;font-weight: bold"> {{ number_format( $totalTaxAmount, 2 ) }}</td>
                <td></td>
                <td></td>
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
