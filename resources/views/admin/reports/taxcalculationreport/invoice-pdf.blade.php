<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Invoice</title>
    <style>
        @page {
            size: 148mm 120mm;
            margin: 8mm;
        }
        body {
            font-family: arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        table {
            font-family: arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
        }
        .invoice_btn {
            float: right;
        }
        .invoice_btn span {
            text-align: center;
            font-size: 18px;
            font-weight: lighter;
            background: #1d1d1b;
            padding: 6px 26px;
            color: #fff;
            display: inline-block;
            vertical-align: middle;
            text-transform: capitalize;
            letter-spacing: 4px;
        }
        .main_heading {
            text-align: left;
            font-size: 17px;
            padding: 5px 0px;
            letter-spacing: .7px;
        }
        .main_heading strong {
            font-size: 16px;
        }
        .logo {
            text-align: left;
            margin-left: -6px;
            margin-bottom: -5px;
            width: 320px;
        }
        .logo_caption {
            font-size: 11px;
            font-weight: lighter;
            margin-left: -6px;
        }
        .logo_caption2 {
            margin-top: -9px;
        }
        .table th {
            border: 1px solid #ddd;
            text-align: left;
            padding: 8px;
            font-size: 12px;
        }
        .table td {
            border: 1px solid #ddd;
            text-align: left;
            padding: 8px;
            font-size: 12px;
        }
        .table tr:nth-child(even) {
            background-color: #f7f7f7;
        }
    </style>
</head>
<body>
    <table>
        <tr>
            <td>
                <div style="font-size:22px; font-weight:bold; letter-spacing:3px;">
                    <span style="font-weight:lighter;"></span> 
                </div>
                <p class="logo_caption">{{ $location->address }}.</p>
                <p class="logo_caption logo_caption2">Phone. {{ $location->fdo_phone }} &nbsp; | &nbsp; Email. care@alluraesthetics.pk &nbsp; | &nbsp; www.alluraesthetics.pk &nbsp; | &nbsp; NTN. {{ $location->ntn }} &nbsp; | &nbsp; STN. {{ $location->stn }}</p>
            </td>
            <td style="padding:0px !important; float:right; width:120px; text-align:right;">
                <div class="invoice_btn" style="width:120px; float:right; text-align:right;">
                    <span>INVOICE</span>
                </div>
            </td>
        </tr>
    </table>

    <table style="margin:19px 0px 30px;">
        <tr>
            <td class="main_heading">{{ \Carbon\Carbon::parse($invoice['invoice_date'])->format('F j, Y') }}</td>
        </tr>
        <tr>
            <td class="main_heading">Consumption Invoice <strong>#{{ $invoice['invoice_number'] }}</strong></td>
        </tr>
    </table>

    <table class="table">
        <tr>
            <th>#</th>
            <th>Service</th>
            <th>Service Price</th>
            <th>Subtotal</th>
            <th>Tax %</th>
            <th>Tax</th>
            <th>Total</th>
        </tr>
        <tr>
            <td>1</td>
            <td>{{ $service_name }}</td>
            <td>{{ number_format($service_price) }}</td>
            <td>{{ number_format($service_price) }}</td>
            <td>{{ $tax_percent }}%</td>
            <td>{{ number_format($tax_amount) }}</td>
            <td>{{ number_format($total_amount) }}</td>
        </tr>
    </table>

    <p style="text-align:center; font-size:12px; margin-top:20px; font-style:italic;">Thank you for your business with Allura Aesthetics.</p>
</body>
</html>
