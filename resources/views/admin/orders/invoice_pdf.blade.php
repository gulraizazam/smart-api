<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Cutera Aesthetics</title>
    <meta
        content="Cutera Aesthetics is a Medical Spa offering more than 60 treatment for skin rejuvenation and body contouring"
        name="description" />
    <meta content="Red Signal" name="author" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <style>
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

        .main_heading span {
            font-size: 12px;
            text-transform: capitalize;
        }

        .logo {
            text-align: left;
            margin-left: -6px;
            margin-bottom: -5px;
            width: 320px;
        }

        .table th {
            border: 1px solid #ddd;
            text-align: left;
            padding: 8px;
        }

        td,
        th {
            text-align: left;
            padding: 8px;
            font-size: 12px;
        }

        .table td,
        .table th {
            text-align: left;
            padding: 8px;
            font-size: 12px;
        }

        .table tr:nth-child(even) {
            background-color: #f7f7f7;
        }

        .danger-alert {
            color: #000;
            border: 1px solid #f5c6cb;
            padding: 8px 10px;
            text-align: center;
            margin: 10px 0 0;
        }

        .grand-tax {
            margin-top: 0;
        }

        .grand-tax tr:first-child td {
            padding-bottom: 0;
        }

        .grand-tax tr:last-child td {
            padding-top: 0;
        }

        .grand-tax td {
            padding-left: 0;
            padding-right: 0;
        }

        .signature_section {
            margin-top: 80px;
            margin-left: -9px;
        }

        .signature_section p {
            /* float:left; */
            border-top: 1px solid #1d1d1b;
            padding: 12px 0px 0px;
            font-size: 15px;
            margin: 0px 0px -5px;
        }

        .signature_section span {
            font-size: 12px;
            font-weight: bold;
            display: block;
            width: 100%;
        }

        .static_text h4 {
            text-align: left;
            font-size: 17px;
            padding: 5px 0px 38px;
            margin: 0px;
            font-weight: lighter;
        }

        .static_text2.static_text h4 {
            padding: 5px 0px 5px;
        }

        .static_text2 span {
            text-align: left;
            font-size: 17px;
            font-weight: lighter;
            float: left;
            padding-top: 6px;
            margin-right: 7px;
        }

        .static_text2 span.high {
            margin-left: 7px;
        }

        .static_text p {
            margin: 0px 0px 0px;
            padding: 0px;
            border-bottom: 1px dotted #8d8d8d;
        }

        .logo_caption {
            font-size: 11px;
            font-weight: lighter;
            margin-left: -6px;
        }

        .logo_caption2 {
            margin-top: -9px;
        }

        .static_text2 .counter {
            width: 36px;
            height: 36px;
            border: 0.5px solid #ddd;
            margin: 0px 6px;
            border-radius: 100%;
            float: left;
            text-align: center;
            font-size: 17px;
            font-weight: lighter;
            vertical-align: middle;
        }

        .static_text2 .counter strong {
            font-weight: lighter;
            padding-top: 7px;
            display: inline-block;
            width: 100%;
            text-align: center;
        }

        @if ($download != 'download')
            @media not print {
                .invoice-pdf {
                    width: 50%;
                    margin-left: 25%;
                    margin-top: 50px;
                    height: 100%;
                }
            }

            @page {
                size: auto;
                margin-top: 0;
                margin-bottom: 0;
            }
        @endif
    </style>
</head>

<body>
    <div class="invoice-pdf">
        <!-- <table style="display:none;">
        <tr style="padding-left: 50%"> -->
        <!-- </tr>
    </table> -->

        <table>
            <tr>
                <td>
                    <img class="img-responsive logo" src="https://crm2.cutera.pk/public/assets/media/new_logo.png"
                        style="width:235px;" alt="" />
                    <p class="logo_caption">{{ $location_info->address }}.</p>
                    <p class="logo_caption logo_caption2">Phone. {{ $location_info->fdo_phone }} &nbsp; | &nbsp; Email.
                        {{ $account->email }} &nbsp; | &nbsp; www.cuteraesthetics.com &nbsp; | &nbsp; NTN.
                        {{ $location_info->ntn }} &nbsp; | &nbsp; STN. {{ $location_info->stn }}</p>
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
                <td class="main_heading"><?php echo \Carbon\Carbon::parse($invoice_info->created_at)->format('F j,Y'); ?>,
                    {{ \Carbon\Carbon::parse($invoice_info->created_at)->format('h:i a') }}</td>
            </tr>
            <tr>
                <td class="main_heading">Order Invoice <strong>#{{ $invoice_info->id }}</strong></td>
            </tr>
            <tr>
                <td class="main_heading">{{ ucfirst($patient->name) }}, <strong>C-{{ $patient->id }}</strong></td>
            </tr>
        </table>
        <table style="display:none;">
            <tr style="padding-top: 30px;">
                <th>Client</th>
                <th><!-- left empty --></th>
                <th><!-- left empty --></th>
                <th><!-- left empty --></th>
                <th><!-- left empty --></th>
                <th><!-- left empty --></th>
                <th><!-- left empty --></th>
                <th><!-- left empty --></th>
                <th><!-- left empty --></th>
                <th><!-- left empty --></th>
                <th colspan="3" style="width: 250px;">Company</th>
            </tr>
            <tr>
                <td style="width:200px"><strong>Name:</strong><span
                        style="padding-left: 10px;">{{ $patient->name }}</span></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td colspan="3"><strong>Name:</strong><span style="padding-left: 10px;">{{ $account->name }}</span>
                    << /td>
            </tr>
            <tr>
                <td><strong>Patient ID:</strong> <span style="padding-left: 10px;">{{ 'C-' . $patient->id }}</span>
                </td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td colspan="3"><strong>Contact:</strong> <span
                        style="padding-left: 10px;">{{ $company_phone_number->data }}</span></td>
            </tr>
            <tr>
                <td><strong>Email:</strong> <span style="padding-left: 10px;">{{ $patient->email }}</span></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td colspan="3"><strong>Email:</strong> <span
                        style="padding-left: 10px;">{{ $account->email }}</span></td>

            </tr>
            <tr>
                <td></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td colspan="3" style="width:130px"><strong>Clinic Name:</strong> <span
                        style="padding-left: 10px;">{{ $location_info->name }}</span></td>
            </tr>
            <tr>
                <td></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td colspan="3" style="width:130px"><strong>Clinic Contact:</strong> <span
                        style="padding-left: 10px;">{{ $location_info->fdo_phone }}</span></td>
            </tr>
            <tr>
                <td></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td colspan="3"><strong>Address:</strong> <span
                        style="padding-left: 10px;">{{ $location_info->address }}</span></td>
            </tr>
            <tr>
                <td></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td colspan="3"><strong>NTN:</strong> <span
                        style="padding-left: 10px;">{{ $location_info->ntn }}</span></td>
            </tr>
            <tr>
                <td></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td><!-- left empty --></td>
                <td colspan="3"><strong>STN:</strong> <span
                        style="padding-left: 10px;">{{ $location_info->stn }}</span></td>
            </tr>

        </table>
        <table class="table">
            <tr>
                <th># </th>
                <th>Product Name</th>
                <th>Product Price</th>
                <th>Quantity</th>
                <th>Sub Total</th>
            </tr>
            @foreach ($invoice_info->orderDetail as $product)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $product->product->name }} </td>
                    <td>{{ $product->product->sale_price }}</td>
                    <td>{{ $product->quantity }}</td>
                    <td>{{ $product->product->sale_price * $product->quantity }}</td>
                </tr>
            @endforeach
        </table>
        <table class="grand-tax mb-3">
            <tbody>
                <tr>
                    <td style="text-align: right;"><strong>Total:</strong> <?php echo number_format($invoice_info->total_price); ?>/-</td>
                </tr>
            </tbody>
        </table>

    </div>
    {{-- <table style="width: 100%;">
    <tr>
        <td><div class="danger-alert">Invoice is not Refundable</div></td>
    </tr>
</table> --}}

    <script>
        window.print();
        setTimeout(function() {
            window.close();
        }, 100);
    </script>


</body>

</html>
