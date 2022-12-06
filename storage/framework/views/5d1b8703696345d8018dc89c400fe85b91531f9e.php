<?php $__env->startSection('content'); ?>

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    <?php echo $__env->make('admin.partials.breadcrumb', ['module' => 'Machine Types', 'title' => 'Machine Types'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">

                <!--begin::Card-->
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon">
                                <span class="svg-icon svg-icon-md svg-icon-primary">
                                    <!--begin::Svg Icon | path:assets/media/svg/icons/Shopping/Chart-bar1.svg-->
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <rect x="0" y="0" width="24" height="24" />
                                            <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13" rx="1.5" />
                                            <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8" rx="1.5" />
                                            <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero" />
                                            <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5" />
                                        </g>
                                    </svg>
                                    <!--end::Svg Icon-->
                                </span>
                            </span>
                            <h3 class="card-label">Machine Types</h3>
                        </div>
                        <div class="card-toolbar">
                            <!--begin::Dropdown-->
                            <?php if(Gate::allows('machineType_destroy')): ?>
                                <div class="delete-records d-none">
                                    <span>Selected Rows: <span class="checkbox-count"></span></span>
                                    <a id="delete-table-rows" href="javascript:void(0);" class="btn btn-danger font-weight-bolder">
                                        <i class="fa fa-trash-alt"></i>Delete
                                    </a>
                                </div>&nbsp;&nbsp;&nbsp;
                            <?php endif; ?>
                            <?php if(Gate::allows('machineType_create')): ?>
                                <a href="javascript:void(0);" class="btn btn-primary" data-toggle="modal" data-target="#modal_add_machine_types">
                                    <!--begin::Svg Icon | path: icons/duotune/arrows/arr075.svg-->
                                    <i class="la la-plus"></i>
                                    Add New
                                </a>
                        <?php endif; ?>

                        <!--end::Button-->
                        </div>
                    </div>

                    <div class="card-body">
                        <!--begin::Search Form-->
                    <?php echo $__env->make('admin.machine_types.filters', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                    <!--end::Search Form-->

                        <!--begin: Datatable-->
                        <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
                        <!--end: Datatable-->
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->

    <div class="modal fade" id="modal_add_machine_types" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="region-create">

            <?php echo $__env->make('admin.machine_types.create', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        </div>
        <!--end::Modal dialog-->
    </div>


    <div class="modal fade" id="modal_edit_machine_types" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="region-edit">

            <?php echo $__env->make('admin.machine_types.edit', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        </div>
        <!--end::Modal dialog-->
    </div>

    <?php $__env->startPush('datatable-js'); ?>
        <script src="<?php echo e(asset('assets/js/pages/admin_settings/machine_types.js')); ?>"></script>
    <?php $__env->stopPush(); ?>

    <?php $__env->startPush('js'); ?>
        <script src="<?php echo e(asset('assets/js/pages/crud/forms/validation/admin_settings/machine_types.js')); ?>"></script>
    <?php $__env->stopPush(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.master', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /var/www/cuterav2.test/resources/views/admin/machine_types/index.blade.php ENDPATH**/ ?>