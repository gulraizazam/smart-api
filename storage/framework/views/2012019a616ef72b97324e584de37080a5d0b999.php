<div id="consultancy-invoice-create">
    
    <div id="successMessage" class="alert alert-success display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
        Invoice successfully created
    </div>
    <div id="wrongMessage" class="alert alert-warning display-hide"  style="display: none;">
        <button class="close" data-close="alert"></button>
        Something Went Wrong!
    </div>
    <div id="definefield" class="alert alert-warning display-hide"  style="display: none;">
        <button class="close" data-close="alert"></button>
        Kindly define payment mode
    </div>
    <div id="percentageMessage" class="alert alert-danger display-hide"  style="display: none;">
        <button class="close" data-close="alert"></button>
        Your discount limit exceeded.
    </div>
    <div id="customfield" class="alert alert-warning display-hide"  style="display: none;">
        <button class="close" data-close="alert"></button>
        Cash must be greater than zero
    </div>
    

    

    <input type="hidden" value="<?php echo e($id); ?>" id="invoice_appointment_id">
    <input type="hidden" value="<?php echo e($location_info->id); ?>" id="id_location">
    <input type="hidden" value="<?php echo e($price_tax); ?>" id="price_for_calculation">
    <input type="hidden" value="<?php echo e($service?->tax_treatment_type_id ?? 0); ?>" id="tax_treatment_type_id">


    <input type="hidden" value="" id="settleamount_cash">
    <input type="hidden" value="" id="outstanding_cash">

    

    
    <div class="table-responsive">
        <table class="table table-striped table-bordered table-advance table-hover">

            <thead>
            <tr>
                <th> Name</th>
                <th> Price</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><?php echo e($service?->name ?? 'N/A'); ?></td>
                <td><?php echo e(number_format($price_tax)); ?></td>
            </tr>
            </tbody>
        </table>
    </div>
    

    <div class="form-group">

        <div class="row mt-5">

            
            <div class="col-md-10">

                <div class="col-md-12 mt-5">
                    <strong class="mt-5">Date</strong>
                    <span class="d-none"><i  onclick="triggerDate('custom_field');" style="color: #cc8600; font-size: large; cursor: pointer;" class="la la-pencil float-right"></i></span>
                    <input type="text" name="created_at" value="<?php echo e(\Carbon\Carbon::now()->format('Y-m-d')); ?>"
                           class="form-control float-right custom_field pr-0 text-right" id="created_at" readonly>
                </div>

                <div class="col-md-12 mt-5 mb-10">
                    <strong class="mt-5">Pay</strong>
                    <input style="width: 50%;" type="text" name="cash" id="cash" value="<?php echo e($cash); ?>" class="form-control float-right">
                </div>

                <div class="col-md-12 mt-5">
                    <strong>Payment Mode</strong>
                    <?php echo Form::select('payment_mode_id',$paymentmodes ,old('payment_mode_id'),['class' => 'form-control float-right','id'=>'payment_mode_id', 'style' => 'width:50%;']); ?>


                </div>

                <br>
                <div class="col-md-12 mt-5 mb-10">
                    <div id="addinvoice">
                        <button class="btn btn-primary spinner-button" name="savepackageinformation" id="savepackageinformation"
                                style="float: right;margin-top:20px;"><i class="la la-paper-plane-o"></i> Show Invoice
                        </button>
                    </div>

               

                <?php if($discounts->count() > 0): ?>
                <div class="col-md-10 mt-5">
                    <label><strong>Discount</strong></label>
                    <select name="discount_id" id="discount_id" class="form-control discount_id">
                        <option value="0">Select Discount</option>
                        <?php $__currentLoopData = $discounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $discount): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($discount['id']); ?>"><?php echo e($discount['name']); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="col-md-10 mt-5 discount_type_section" style="display: none;">
                    <label><strong>Discount Type</strong></label>
                    <select name="discount_type" id="discount_type" class="form-control" disabled>
                        <option value="0">Select Discount Type</option>
                        <option value="Fixed">Fixed</option>
                        <option value="Percentage">Percentage</option>
                    </select>
                </div>

                <div class="col-md-10 mt-5 discount_value_section" style="display: none;">
                    <label><strong>Discount Value</strong></label>
                    <input type="number" name="discount_value" id="discount_value" value="0" class="form-control" disabled>
                </div>

            </div>

            

            <div class="col-md-4" style="display: none;">

                


                <input type="hidden" class="amount" name="amount" value="<?php echo e($price); ?>">

                <input type="hidden" class="tax" name="tax" value="<?php echo e($tax); ?>">

                <div class="col-md-10 mt-5">
                    <strong>Tax Amt.</strong>
                    <strong id="tax_amt" class="float-right"><?php echo e($tax_amt); ?></strong>
                    <input type="hidden" class="tax_amt" name="tax_amt" value="<?php echo e($tax_amt); ?>">
                </div>

                <div class="col-md-10 mt-5" style="display: none;">
                    <strong>Balance Amount</strong>
                    <strong id="balance" class="float-right"><?php echo e($balance); ?></strong>
                    <input type="hidden" class="balance" name="balance" value="<?php echo e($balance); ?>">
                </div>

                <div class="col-md-10 mt-5">
                    <strong>Settle Amount</strong>
                    <strong id="settle" class="float-right"><?php echo e($settleamount); ?></strong>
                    <input type="hidden" class="settle" name="settle" value="<?php echo e($settleamount); ?>">
                </div>

                <div class="col-md-10 mt-5">
                    <strong>Outstanding</strong>
                    <strong id="outstand" class="float-right"><?php echo e($outstanding); ?></strong>
                    <input type="hidden" class="outstand" name="outstand" value="<?php echo e($outstanding); ?>">
                </div>

            </div>

            </div>

        </div>
    </div>

</div>
<?php /**PATH /var/www/cuterav2.test/resources/views/admin/appointments/consultancyinvoice/fields.blade.php ENDPATH**/ ?>