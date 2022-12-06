<div id="treatment-invoice-create">
    
    <div id="successMessage" class="alert alert-success display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
        Invoice successfully created
    </div>
    <div id="wrongMessage" class="alert alert-warning display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
        Something Went Wrong!
    </div>
    <div id="noconsultancy" class="alert alert-danger display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
        Please select consultancy from appointment dropdown (Only arrived consultancies will be displayed). 
    </div>
    <div id="definefield" class="alert alert-warning display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
        Kindly define payment mode
    </div>
    <div id="definetreatment" class="alert alert-warning display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
        Kindly select the treatment
    </div>
    

    
    <input type="hidden" value="<?php echo e($id); ?>" id="appointment_id_create">
    <input type="hidden" value="<?php echo e($settleamount); ?>" id="settleamount_for_zero" name="settleamount_for_zero">
    <input type="hidden" value="<?php echo e($outstanding); ?>" id="outstanding_for_zero" name="outstanding_for_zero">
    <input type="hidden" id="package_service_id" name="package_service_id">
    <input type="hidden" value="<?php echo e($checked_treatment); ?>" id="checked_treatment" name="checked_treatment">
    <input type="hidden" value="0" id="checked_bundle_id" name="checked_bundle_id">

    

    
    <?php if($appointment_type->name == Config::get('constants.Service')): ?>
        <?php if($status == 'false'): ?>
            <div class="row">
                <div class="col-md-6">
                    <select class="form-control select2 disabled-field" disabled>
                        <option value="">Select Package</option>
                    </select>
                </div>
            </div>
            <br>
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-advance table-hover">
                    <?php echo e(csrf_field()); ?>

                    <thead>
                    <tr>
                        <th> Name</th>
                        <th> Price</th>
                        <th> Discount Name</th>
                        <th> Discount Type</th>
                        <th> Discount Price</th>
                        <th> Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td><?php echo e($service->name); ?></td>
                        <td><?php echo number_format($amount_create_is_inclusive);?></td>
                        <td>-</td>
                        <td>-</td>
                        <td>0.00</td>
                        <td><?php echo number_format($amount_create_is_inclusive);?></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if($status == 'true'): ?>
            <div class="row">
                <div class="col-md-6">
                    <select name="package_id_create" id="package_id_create" class="form-control select2">
                        <option value="">Select Package</option>
                        <?php $__currentLoopData = $packages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $package): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option <?php if($key == '0'): ?> selected="selected"
                                    <?php endif; ?> value="<?php echo e($package->id); ?>"><?php echo e($package->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
            </div>
            <br>
            <div class="table-responsive">
                <table id="table_1" class="table table-striped table-bordered table-advance table-hover">
                    <thead>
                    <?php $constant = 555;?>
                    <tr>
                        <th> Name</th>
                        <th> Price</th>
                        <th> Discount Name</th>
                        <th> Discount Type</th>
                        <th> Discount Price</th>
                        <th> Amount</th>
                        <th> Tax %</th>
                        <th> Tax Amt.</th>
                    </tr>
                    </thead>
                    
                    <tr class="HR_<?php echo e($constant); ?>">
                    </tr>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <br>


    <div class="form-group">

        <div class="row mt-5">

            
            <div class="col-md-8">

                
                <?php if($status == 'false'): ?>
                    <div class="col-md-10">
                        <label><strong>Appointment</strong></label>
                        <select name="appointment_link_cons" id="appointment_link_cons" class="form-control">
                            <option value="">Select Appointment</option>
                            <?php $__currentLoopData = $appointmentArray; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $appointment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($appointment['id']); ?>"
                                        <?php if($loop->first): ?> selected <?php endif; ?>><?php echo e($appointment['name']); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                <?php endif; ?>
                

            </div>

            <div class="col-md-4">

                
                <?php if($appointment_type->name == Config::get('constants.Service')): ?>
                    <?php if($status == 'false'): ?>

                        
                        <input type="hidden" value="<?php echo e($amount_create_is_inclusive); ?>" id="orignal_price_h">
                        <input type="hidden" value="<?php echo e($location_id); ?>" id="location_id_tax">
                        <input type="hidden" value="<?php echo e($service->tax_treatment_type_id); ?>" id="tax_treatment_type_id">
                        

                        <div class="col-md-10 mt-12">
                            <!--begin::Option-->
                            <span class="switch switch-sm switch-icon switch_custom">
                                <div class="col-md-12" style="padding-left: 0">
                                    <strong>Exclusive</strong>

                                <?php if($service->tax_treatment_type_id == Config::get('constants.tax_both') || $service->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')): ?>

                                    <input type="hidden" name="is_exclusive" value="0"/>

                                        <label class="float-right">
                                            <input id="is_exclusive" type="checkbox" name="is_exclusive" value="1" checked>
                                            <span></span>
                                        </label>


                                <?php else: ?>
                                    <input type="hidden" name="is_exclusive" value="0"/>
                                        <label class="float-right">
                                            <input id="is_exclusive" type="checkbox" name="is_exclusive" value="0">
                                            <span></span>
                                        </label>

                                <?php endif; ?>

                                </div>
                            </span>
                        </div>


                    <?php endif; ?>
                <?php endif; ?>
                

                
                <input type="hidden" class="amount_create" name="amount_create" value="<?php echo e($amount_create); ?>">


               
                <input type="hidden" class="tax_create" name="tax_create" value="<?php echo e($tax_create); ?>">

                <div class="col-md-10 mt-5">
                    <strong>Total Amount</strong>
                    <strong class="float-right" id="price_create"><?php echo e($price); ?></strong>
                    <input type="hidden" class="price_create" name="price_create" value="<?php echo e($price); ?>">
                    <input type="hidden" name="remaining" id="remaining" />
                </div>


                <?php if($balance > 0): ?>
                    <div class="col-md-10 mt-5">
                        <strong>Balance Amount</strong>
                        <strong class="float-right" id="balance_create"><?php echo e($balance); ?></strong>
                    </div>
                <?php endif; ?>
                <input type="hidden" class="balance_create" name="balance_create" value="<?php echo e($balance); ?>">


                <?php if($settleamount > 0): ?>
                    <div class="col-md-10 mt-5">
                        <strong>Settle Amount</strong>
                        <strong class="float-right" id="settle_create"><?php echo e($settleamount); ?></strong>
                    </div>
                <?php endif; ?>
                <input type="hidden" class="settle_create" name="settle_create" value="<?php echo e($settleamount); ?>">

                <div class="col-md-10 mt-5">
                    <strong>Outstanding</strong>
                    <strong class="float-right" id="outstand_create"><?php echo e($outstanding); ?></strong>
                    <input type="hidden" class="outstand_create" name="outstand_create" value="<?php echo e($outstanding); ?>">
                </div>

                <div class="col-md-11 mt-5">
                    <strong class="mt-5">Date</strong>
                    <span><i  onclick="triggerDate('custom_field');" style="color: #cc8600; font-size: large; cursor: pointer;" class="la la-pencil float-right"></i></span>
                    <input type="text" name="created_at" value="<?php echo e(\Carbon\Carbon::now()->format('Y-m-d')); ?>"
                           class="form-control custom-datepicker float-right custom_field" id="created_at" readonly>
                </div>

                <div class="col-md-10 mt-5 mb-10">
                    <strong class="mt-5">Pay</strong>
                    <input style="width: 50%;" type="text" name="cash_create" id="cash_create" value="0" class="form-control float-right">
                </div>

                <div class="col-md-10 mt-5" id="paymentmode" style="display: none;">
                    <strong>Payment Mode</strong>
                    <?php echo Form::select('payment_mode_id',$paymentmodes ,old('payment_mode_id'),['class' => 'form-control float-right','id'=>'payment_mode_id', 'style' => 'width:50%;']); ?>


                </div>


                <div class="col-md-10 mt-5 mb-10">
                    <div id="treatment_addinvoice" >
                        <button class="btn btn-primary spinner-button" name="savepackageinformation" id="treatment_savepackageinformation"
                                style="float: right;margin-top:20px;"><i class="la la-paper-plane-o"></i> Save & Print Invoice
                        </button>
                    </div>
                </div>

            </div>

        </div>

    </div>


</div>
<?php /**PATH /var/www/cuterav2.test/resources/views/admin/appointments/invoice_fields.blade.php ENDPATH**/ ?>