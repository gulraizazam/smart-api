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
                            <img class="logo" src="{{ public_path('allura-logo1.jpeg') }}"
                                 class="img-responsive" alt=""/>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="padding-left: 450px;">
                <table style="float: right;">
                    <tr>
                        <td style="width: 70px;">Name</td>
                        <td>Account Sales Report</td>
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
        <tr class="shdoc-header">
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
                    <td> {{ $reportRow->patient->name }}</td>
                    <td> {{ $reportRow->gender ?? '-' }}</td>
                    <td> {{ $reportRow->user->name }}</td>
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
                <td style="text-align: center;font-weight: bold">Total</td>
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
</div>

</body>
</html>
