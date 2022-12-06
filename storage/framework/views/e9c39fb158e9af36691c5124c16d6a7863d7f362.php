<?php $__env->startSection('content'); ?>


    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    <?php echo $__env->make('admin.partials.breadcrumb', ['module' => 'Edit Roles', 'title' => 'Roles'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">

                <form class="form fv-plugins-bootstrap" method="post" id="permissions-form" action="<?php echo e(route('admin.roles.update', $role)); ?>">
                    <?php echo method_field('put'); ?>
                    <?php echo csrf_field(); ?>

                    <?php echo $__env->make('admin.roles.fields', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

                    <!--begin::Card-->
                    <div class="card card-custom gutter-b example example-compact">

                        
                        <div class="card-header">
                            <h3 class="card-title">Dashboard Permissions</h3>
                        </div>
                        <div class="card-body">
                            <!--begin::Form-->
                            <?php if(count($DashboardPermissions)): ?>
                                <?php $__currentLoopData = $DashboardPermissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $Permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                    <div class="form-group row">

                                        <label class="col-2 col-form-label"><strong><?php echo e($Permission['title']); ?></strong></label>
                                        <input id="allow_<?php echo e($Permission['name']); ?>" type="checkbox" name="permission[]"
                                               class="allow_all allow <?php echo e($Permission['name']); ?> allow_<?php echo e($Permission['name']); ?>"
                                               value="<?php echo e($Permission['name']); ?>" checked="true" style="visibility: hidden;"
                                               onclick="FormValidation.checkMyModule(this,'allow_<?php echo e($Permission['name']); ?>');">

                                        <div class="col-9 col-form-label">
                                            <div class="checkbox-inline">
                                                <?php $__currentLoopData = $dashboardPermissionsMapping; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                                    <?php if(array_key_exists($Permission['key'] . $key, $Permission['children'])): ?>
                                                        <label class="checkbox permission_checkbox">
                                                            <input id="sub-allow_<?php echo e($Permission['children'][$Permission['key'] . $key]['name']); ?>"
                                                                   type="checkbox" name="permission[]"
                                                                   class="allow_all allow <?php echo e($Permission['name']); ?>  sub-allow_<?php echo e($Permission['name']); ?>"
                                                                   value="<?php echo e($Permission['children'][$Permission['key'] . $key]['name']); ?>"
                                                                   <?php if(isset($AllowedPermissions[$Permission['children'][$Permission['key'] . $key]['id']])): ?> checked="true"
                                                                   <?php endif; ?> onclick="FormValidation.checkMyParent(this,'allow_<?php echo e($Permission['name']); ?>' , 'sub-allow_<?php echo e($Permission['name']); ?>', '<?php echo e($Permission['children'][$Permission['key'] . $key]['name']); ?>' );">
                                                            <span></span><?php echo e($name); ?></label>
                                                    <?php endif; ?>

                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </div>
                                        </div>

                                    </div>

                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                        <!--end::Form-->
                        </div>
                        
                    </div>

                    <div class="card card-custom gutter-b example example-compact">
                        
                        <div class="card-header">
                            <h3 class="card-title">General Permissions</h3>
                        </div>
                        <div class="card-body">
                            <!--begin::Form-->
                            <?php if(count($Permissions)): ?>
                                <?php $__currentLoopData = $Permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $Permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                    <div class="form-group row">

                                        <label class="col-2 col-form-label"><strong><?php echo e($Permission['title']); ?></strong></label>

                                        <div class="col-9 col-form-label">
                                            <div class="checkbox-inline">

                                                <label class="checkbox permission_checkbox">
                                                    <input id="allow_<?php echo e($Permission['name']); ?>" type="checkbox" name="permission[]"
                                                           class="allow_all allow <?php echo e($Permission['name']); ?> allow_<?php echo e($Permission['name']); ?>"
                                                           value="<?php echo e($Permission['name']); ?>"
                                                           <?php if(isset($AllowedPermissions[$Permission['id']])): ?> checked="true"
                                                           <?php endif; ?> onclick="FormValidation.checkMyModule(this,'allow_<?php echo e($Permission['name']); ?>');">
                                                    <span></span>Display</label>

                                                <?php $__currentLoopData = $permissionsMapping; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                                    <?php if(array_key_exists($Permission['key'] . $key, $Permission['children'])): ?>
                                                        <label class="checkbox permission_checkbox">
                                                            <input id="sub-allow_<?php echo e($Permission['children'][$Permission['key'] . $key]['name']); ?>"
                                                                   type="checkbox" name="permission[]"
                                                                   class="allow_all allow <?php echo e($Permission['name']); ?>  sub-allow_<?php echo e($Permission['name']); ?>"
                                                                   value="<?php echo e($Permission['children'][$Permission['key'] . $key]['name']); ?>"
                                                                   <?php if(isset($AllowedPermissions[$Permission['children'][$Permission['key'] . $key]['id']])): ?> checked="true"
                                                                   <?php endif; ?> onclick="FormValidation.checkMyParent(this,'allow_<?php echo e($Permission['name']); ?>' , 'sub-allow_<?php echo e($Permission['name']); ?>', '<?php echo e($Permission['children'][$Permission['key'] . $key]['name']); ?>' );">
                                                            <span></span><?php echo e($name); ?></label>
                                                    <?php endif; ?>

                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </div>
                                        </div>

                                    </div>

                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                        <!--end::Form-->
                        </div>
                        
                    </div>

                    <div class="card card-custom gutter-b example example-compact">
                        
                        <div class="card-header">
                            <h3 class="card-title">Reports Permissions</h3>
                        </div>
                        <div class="card-body">
                            <!--begin::Form-->
                            <?php if(count($ReportsPermissions)): ?>
                                <?php $__currentLoopData = $ReportsPermissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $Permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                    <div class="form-group row">

                                        <label class="col-2 col-form-label"><strong><?php echo e($Permission['title']); ?></strong></label>

                                        <div class="col-9 col-form-label">
                                            <div class="checkbox-inline">

                                                <label class="checkbox permission_checkbox">
                                                    <input id="allow_<?php echo e($Permission['name']); ?>" type="checkbox" name="permission[]"
                                                           class="allow_all allow <?php echo e($Permission['name']); ?> allow_<?php echo e($Permission['name']); ?>"
                                                           value="<?php echo e($Permission['name']); ?>"
                                                           <?php if(isset($AllowedPermissions[$Permission['id']])): ?> checked="true"
                                                           <?php endif; ?> onclick="FormValidation.checkMyModule(this,'allow_<?php echo e($Permission['name']); ?>');">
                                                    <span></span>Display</label>

                                                <?php $__currentLoopData = $reportsPermissionsMapping; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <?php if(array_key_exists($Permission['key'] . $key, $Permission['children'])): ?>
                                                        <label class="checkbox permission_checkbox">
                                                            <input id="sub-allow_<?php echo e($Permission['children'][$Permission['key'] . $key]['name']); ?>"
                                                                   type="checkbox" name="permission[]"
                                                                   class="allow_all allow <?php echo e($Permission['name']); ?>  sub-allow_<?php echo e($Permission['name']); ?>"
                                                                   value="<?php echo e($Permission['children'][$Permission['key'] . $key]['name']); ?>"
                                                                   <?php if(isset($AllowedPermissions[$Permission['children'][$Permission['key'] . $key]['id']])): ?> checked="true"
                                                                   <?php endif; ?> onclick="FormValidation.checkMyParent(this,'allow_<?php echo e($Permission['name']); ?>' , 'sub-allow_<?php echo e($Permission['name']); ?>', '<?php echo e($Permission['children'][$Permission['key'] . $key]['name']); ?>' );">
                                                            <span></span><?php echo e($name); ?></label>
                                                    <?php endif; ?>

                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </div>
                                        </div>

                                    </div>

                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                        <!--end::Form-->

                            <button type="submit" class="btn btn-primary spinner-button" >
                                <span class="indicator-label">Save</span>
                            </button>
                        </div>
                    </div>

                </form>

            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->

    <div class="modal fade" id="modal_add_permission" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="permission-create">
            
        </div>
        <!--end::Modal dialog-->
    </div>
    <!--end::Modal - Add task-->



    <?php $__env->startPush('datatable-js'); ?>
        <script src="<?php echo e(asset('assets/js/pages/users/role.js')); ?>"></script>
    <?php $__env->stopPush(); ?>

    <?php $__env->startPush('js'); ?>
        <script src="<?php echo e(asset('assets/js/pages/crud/forms/validation/permission/permission-validate.js')); ?>"></script>
    <?php $__env->stopPush(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.master', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /var/www/cuterav2.test/resources/views/admin/roles/edit.blade.php ENDPATH**/ ?>