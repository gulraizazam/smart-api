<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Smart</title>
    <meta content="Smart Aesthetic is a Medical Spa offering more than 60 treatment for skin rejuvenation and body contouring" name="description" />
    <meta content="Red Signal" name="author"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <style>
        table {
            font-family: arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
        }
        .invoice_btn{
            float:right;
        }
        .invoice_btn span {
            text-align: center;
            font-size:18px;
            font-weight:lighter;
            background:#1d1d1b;
            padding:6px 26px;
            color:#fff;
            display: inline-block;
            vertical-align:middle;
            text-transform:capitalize;
            letter-spacing:4px;
        }
        .main_heading{
            text-align: left;
            font-size:17px;
            padding:5px 0px;
            letter-spacing:.7px;
        }
        .main_heading  strong{
            font-size:16px;
        }
        .main_heading span{
            font-size:12px;
            text-transform:capitalize;
        }
        .logo {
            text-align: left;
            margin-left:-6px;
            margin-bottom:-5px;
            width:320px;
        }

        .table th {
            border: 1px solid #ddd;
            text-align: left;
            padding: 8px;
        }

        td, th {
            text-align: left;
            padding: 8px;
            font-size: 12px;
        }

        .table td, .table th {
            text-align: left;
            padding: 8px;
            font-size: 12px;
        }

        .table tr:nth-child(even) {
            background-color: #f7f7f7;
        }
        .danger-alert{
            color: #000;
            border:1px solid #f5c6cb;
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
        .signature_section{
            margin-top:80px;
            margin-left:-9px;
        }
        .signature_section p{
            /* float:left; */
            border-top:1px solid #1d1d1b;
            padding:12px 0px 0px;
            font-size:15px;
            margin:0px 0px -5px;
        }
        .signature_section span{
            font-size:12px;
            font-weight:bold;
            display:block;
            width:100%;
        }
        .static_text h4{
            text-align: left;
            font-size:17px;
            padding:5px 0px 38px;
            margin:0px;
            font-weight:lighter;
        }
        .static_text2.static_text h4{
            padding:5px 0px 5px;
        }
        .static_text2 span{
            text-align: left;
            font-size:17px;
            font-weight:lighter;
            float:left;
            padding-top:6px;
            margin-right:7px;
        }
        .static_text2 span.high{
            margin-left:7px;
        }
        .static_text p{
            margin:0px 0px 0px;
            padding:0px;
            border-bottom:1px dotted #8d8d8d;
        }
        .logo_caption{
            font-size:11px;
            font-weight:lighter;
            margin-left:-6px;
        }
        .logo_caption2{
            margin-top:-9px;
        }
        .static_text2 .counter{
            width:36px;
            height:36px;
            border: 0.5px solid #ddd;
            margin:0px 6px;
            border-radius:100%;
            float:left;
            text-align:center;
            font-size:17px;
            font-weight:lighter;
            vertical-align: middle;
        }
        .static_text2 .counter strong{
            font-weight:lighter;
            padding-top:7px;
            display:inline-block;
            width:100%;
            text-align:center;
        }

        <?php if($download != 'download'): ?>
            @media  not print {
                .invoice-pdf {
                    width: 50%;
                    margin-left: 25%;
                    margin-top: 50px;
                    height: 100%;
                }
            }
            @page  {
                size: auto;
                margin-top: 0;
                margin-bottom: 0;
            }
        <?php endif; ?>

    </style>
</head>

<body>
<div class="invoice-pdf">
    <!-- <table style="display:none;">
        <tr style="padding-left: 50%"> -->
            <?php if($invoicestatus->slug == 'cancelled'): ?>
                
            <?php endif; ?>
        <!-- </tr>
    </table> -->

    <table>
        <tr>
            <td>
                <img class="img-responsive logo" src="<?php echo e(asset('assets/media/logos/smart-invoice-logo.png')); ?>" alt=""/>
                <p class="logo_caption"><?php echo e($location_info->address); ?>.</p>
                <p class="logo_caption logo_caption2">Phone. <?php echo e($location_info->fdo_phone); ?>  &nbsp; |  &nbsp; Email. <?php echo e($account->email); ?>  &nbsp; | &nbsp;  www.smartaesthetics.pk  &nbsp; | &nbsp; NTN. <?php echo e($location_info->ntn); ?> &nbsp; | &nbsp; STN. <?php echo e($location_info->stn); ?></p>
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
            <td class="main_heading"><?php echo \Carbon\Carbon::parse($Invoiceinfo->created_at)->format('F j,Y'); ?>, <?php echo e(\Carbon\Carbon::parse($Invoiceinfo->created_at)->format('h:i a')); ?></td>
        </tr>
        <tr>
            <td class="main_heading">Consumption Invoice <strong>#<?php echo e($Invoiceinfo->id); ?></strong></td>
        </tr>
        <tr>
            <td class="main_heading"><?php echo e(ucfirst($patient->name)); ?>, <strong>C-<?php echo e($patient->id); ?></strong></td>
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
            <td style="width:200px"><strong>Name:</strong><span style="padding-left: 10px;"><?php echo e($patient->name); ?></span></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td colspan="3"><strong>Name:</strong><span style="padding-left: 10px;"><?php echo e($account->name); ?></span><</td>
        </tr>
        <tr>
            <td><strong>Patient ID:</strong> <span style="padding-left: 10px;"><?php echo e('C-'.$patient->id); ?></span></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td colspan="3"><strong>Contact:</strong> <span style="padding-left: 10px;"><?php echo e($company_phone_number->data); ?></span></td>
        </tr>
        <tr>
            <td><strong>Email:</strong> <span style="padding-left: 10px;"><?php echo e($patient->email); ?></span></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td><!-- left empty --></td>
            <td colspan="3"><strong>Email:</strong> <span style="padding-left: 10px;"><?php echo e($account->email); ?></span></td>

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
            <td colspan="3" style="width:130px"><strong>Clinic Name:</strong> <span style="padding-left: 10px;"><?php echo e($location_info->name); ?></span></td>
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
                        style="padding-left: 10px;"><?php echo e($location_info->fdo_phone); ?></span></td>
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
            <td colspan="3"><strong>Address:</strong> <span style="padding-left: 10px;"><?php echo e($location_info->address); ?></span></td>
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
            <td colspan="3"><strong>NTN:</strong> <span style="padding-left: 10px;"><?php echo e($location_info->ntn); ?></span></td>
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
            <td colspan="3"><strong>STN:</strong> <span style="padding-left: 10px;"><?php echo e($location_info->stn); ?></span></td>
        </tr>

    </table>
    <table class="table">
        <tr>
            <th> #</th>
            <th>Consultancy\Service</th>
            <th> Service Price</th>
            <th> Discount Name</th>
            <th> Discount Type</th>
            <th> Discount Price</th>
            <th> Subtotal</th>
            <th> Tax %</th>
            <th> Tax Price</th>
            <th> Total</th>
        </tr>
        <tr>
            <td> 1</td>
            <td><?php echo e($service->name); ?> </td>
            <td>
                <?php if($Invoiceinfo->is_exclusive == '0' && $bundle->type == 'single'): ?>
                    <?php echo e(number_format(($Invoiceinfo->service_price)-($Invoiceinfo->tax_price))); ?>

                <?php elseif($Invoiceinfo->is_exclusive == '0' && $bundle->type == 'multiple'): ?>
                    <?php echo e(number_format($Invoiceinfo->service_price)); ?>

                <?php elseif($Invoiceinfo->is_exclusive == '1'): ?>
                    <?php echo e(number_format($Invoiceinfo->service_price)); ?>

                <?php endif; ?>

            </td>
            <td>
                <?php if($discount != null): ?>
                    <?php echo e($discount->name); ?>

                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
            <td>
                <?php if($Invoiceinfo->discount_type != null): ?>
                    <?php echo e($Invoiceinfo->discount_type); ?>

                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
            <td>
                <?php if($Invoiceinfo->discount_price != null): ?>
                    <?php echo e(number_format($Invoiceinfo->discount_price)); ?>

                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
            <td>
                <?php if($Invoiceinfo->is_exclusive == '0'): ?>
                    <?php if($Invoiceinfo->discount_price == null && $bundle->type == 'single'): ?>
                        <?php echo e(number_format(($Invoiceinfo->service_price)-($Invoiceinfo->tax_price))); ?>

                    <?php else: ?>
                        <?php echo e(number_format($Invoiceinfo->tax_exclusive_serviceprice)); ?>

                    <?php endif; ?>
                <?php elseif($Invoiceinfo->is_exclusive == '1'): ?>
                    <?php echo e(number_format($Invoiceinfo->tax_exclusive_serviceprice)); ?>

                <?php endif; ?>
            </td>
            <td><?php echo e($Invoiceinfo->tax_percenatage); ?></td>
            <td>
                <?php echo e($Invoiceinfo->tax_price); ?>

            </td>
            <td>
                <?php echo e(number_format($Invoiceinfo->tax_including_price)); ?>

            </td>
        </tr>
    </table>
    <table class="grand-tax" style="display:none;">
        <tbody>
        <tr>
            <td style="text-align: right;"><strong>Total:</strong> <?php echo number_format($Invoiceinfo->total_price);?>/-</td>
        </tr>
        <tr>
            <td><strong>Note:</strong> All treatment prices are inclusive of taxes.</td>
        </tr>
        </tbody>
    </table>
    <table style="margin:25px 0px 0px -9px;" class="static_text static_text2">
        <tr>
            <td>
                <h4>How satisfied are you with quality of service(s) provided?</h4>
            </td>
        </tr>
        <tr>
            <td style="padding-bottom:40px;">
                <span>>> Low</span>
                <div class="counter"> <strong>1 </strong></div>
                <div class="counter"> <strong>2</strong></div>
                <div class="counter"> <strong>3</strong></div>
                <div class="counter"> <strong>4</strong></div>
                <div class="counter"> <strong>5</strong></div>
                <span class="high">High >></span>
            </td>
        </tr>
    </table>

    <table style="margin:32px 0px 0px -8px;" class="static_text">
        <tr>
            <td>
                <h4>Your feedback:</h4>
                <p></p><br><br>
                <p></p><br><br>
                <p></p><br><br>
                <p></p><br><br>
            </td>
        </tr>
    </table>

    <table class="signature_section">
        <tr>
            <td>
                <p>Customer Signature</p> <br>
                <span><?php echo e(ucfirst($patient->name)); ?></span>
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
            <td>
                <p>Doctor's Signature</p> <br>
                <span><?php echo e($appointment_info?->doctor?->name); ?></span>
            </td>
        </tr>
    </table>
</div>


<script>

    window.print();
    setTimeout(function () { window.close(); }, 100);

</script>


</body>

</html>
<?php /**PATH /var/www/cuterav2.test/resources/views/admin/invoices/invoice_pdf.blade.php ENDPATH**/ ?>