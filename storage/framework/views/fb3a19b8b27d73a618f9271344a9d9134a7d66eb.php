

<!--begin::Modal content-->
<div class="modal-content">

    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder rota-title">Display</h2>
        <!--end::Modal title-->
        <!--begin::Close-->
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" data-kt-users-modal-action="close">
            <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
            <span class="svg-icon svg-icon-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                    <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                </svg>
            </span>
            <!--end::Svg Icon-->
        </div>
        <!--end::Close-->
    </div>
    <!--end::Modal header-->
    <!--begin::Modal body-->
    <div class="modal-body scroll-y mx-5 mx-xl-15">

        <table style="margin-top: 20px;">
            <tr>
                <td>
                    <img style="width: 235px; margin-bottom: 10px;" class="img-responsive logo" src="<?php echo e(asset('assets/media/logos/smart-invoice-logo.png')); ?>" alt=""/>
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


        

       

        <!--begin::Form-->
        <div class="d-flex flex-column scroll-y me-n7 pe-7 mt-10" id="kt_modal_resourcerotas_scroll">

            <div class="form-group">

                <div class="row">


                    <div class="table-responsive">
                        <table id="allocate_services" class="table table-bordered table-advance">

                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Consultancy\Service</th>
                                <th>Service Price</th>
                                <th>Discount Name</th>
                                <th>Discount Type</th>
                                <th>Discount Price</th>
                                <th>Subtotal</th>
                                <th>Tax %</th>
                                <th>Tax Price</th>
                                <th>Total</th>
                            </tr>
                            </thead>

                            <tbody>
                                <tr>
                                <td>1</td>
                                <td><?php echo e($service->name); ?> </td>
                                <td>
                                    <?php if($Invoiceinfo->is_exclusive == '0' && $bundle->type == 'single'): ?>
                                        <?php if($Invoiceinfo->service_price == '0'): ?>
                                            <?php echo e(number_format($Invoiceinfo->tax_including_price)); ?>

                                        <?php else: ?>
                                            <?php echo e(number_format(($Invoiceinfo->service_price)-($Invoiceinfo->tax_price))); ?>

                                        <?php endif; ?>
                                    <?php elseif($Invoiceinfo->is_exclusive == '0' && $bundle->type == 'multiple'): ?>
                                        <?php if($Invoiceinfo->service_price == '0'): ?>
                                            <?php echo e(number_format($Invoiceinfo->tax_including_price)); ?>

                                        <?php else: ?>
                                            <?php echo e(number_format($Invoiceinfo->service_price)); ?>

                                        <?php endif; ?>
                                    <?php elseif($Invoiceinfo->is_exclusive == '1'): ?>
                                        <?php if($Invoiceinfo->service_price == '0'): ?>
                                            <?php echo e(number_format($Invoiceinfo->tax_including_price)); ?>

                                        <?php else: ?>
                                            <?php echo e(number_format($Invoiceinfo->service_price)); ?>

                                        <?php endif; ?>
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
                                <td>
                                    <?php echo e($Invoiceinfo->tax_percenatage); ?>

                                </td>
                                <td><?php echo e($Invoiceinfo->tax_price); ?></td>
                                <td>
                                    <?php echo e(number_format($Invoiceinfo->tax_including_price)); ?>

                                </td>
                            </tr>
                            </tbody>

                        </table>
                    </div>

                </div>

                <div class="row">
                    <div class="col-md-12 col-sm-12 col-xs-12 mt-10">
                        <ul class="list-unstyled amounts float-right">
                            <li>
                                <strong>Total:</strong> <?php echo number_format($Invoiceinfo->total_price);?>/-
                            </li>
                        </ul>
                        <br/>
                        <div class="text-center">
                            <?php if($Invoiceinfo->appointment_type_id == 1): ?>

                                <a class="btn btn-success blue hidden-print margin-bottom-5" target="_blank"
                                href="<?php echo e(route('admin.invoices.invoice_pdf',[$Invoiceinfo->id,'download',1])); ?>">Print Invoice
                                    <i class="fa fa-print"></i>
                                </a>

                                <a class="btn btn-primary blue hidden-print margin-bottom-5" target="_blank"
                                href="<?php echo e(route('admin.invoices.invoice_pdf',[$Invoiceinfo->id, 'download'])); ?>">Print Consultancy Form
                                    <i class="fa fa-print"></i>
                                </a>

                            <?php else: ?>

                                <a class="btn btn-success blue hidden-print margin-bottom-5" target="_blank"
                                href="<?php echo e(route('admin.invoices.invoice_pdf',[$Invoiceinfo->id])); ?>">Print Invoice
                                    <i class="fa fa-print"></i>
                                </a>

                                <a class="btn  btn-primary blue hidden-print margin-bottom-5" target="_blank"
                                href="<?php echo e(route('admin.invoices.invoice_pdf',[$Invoiceinfo->id, 'download'])); ?>">Download 
                                    <i class="fa fa-download"></i>
                                </a>

                            <?php endif; ?>
                        </div>

                    </div>
                </div>


            </div>

        </div>
        <!--end::Scroll-->


    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->



<?php /**PATH /var/www/cuterav2.test/resources/views/admin/appointments//invoice/displayInvoice.blade.php ENDPATH**/ ?>