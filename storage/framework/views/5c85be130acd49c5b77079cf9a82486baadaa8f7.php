<?php $__env->startSection('content'); ?>

    <?php $__env->startPush('css'); ?>
        <link href="<?php echo e(asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.css')); ?>" rel="stylesheet" type="text/css" />
    <?php $__env->stopPush(); ?>
    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    <?php echo $__env->make('admin.partials.breadcrumb', ['module' => 'Consultancy List', 'title' => 'Consultancies'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">

                <?php echo $__env->make('admin.appointments.partials.consultancy-menu', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

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
                            <h3 class="card-label change-label">Consultancies</h3>

                        </div>

                        <div class="card-toolbar">
                            <!--begin::Dropdown-->
                            <?php if(Gate::allows('appointments_destroy')): ?>
                                <div class="delete-records d-none">
                                    <span>Selected Rows: <span class="checkbox-count"></span></span>
                                    <a id="delete-table-rows" href="javascript:void(0);" class="btn btn-danger font-weight-bolder">
                                        <i class="fa fa-trash-alt"></i>Delete
                                    </a>
                                </div>&nbsp;&nbsp;&nbsp;
                            <?php endif; ?>
                            <?php if(Gate::allows('appointments_export_today')): ?>
                                <div class="export-appointments">
                                    <a id="today_consultancies" onclick="loadTodayAppointments('<?php echo e(date('Y-m-d')); ?>', 'consultancy');" href="javascript:void(0);" class="btn btn-info font-weight-bolder">
                                        Today Consultancies
                                    </a>
                                </div>&nbsp;&nbsp;&nbsp;
                            <?php endif; ?>

                            <?php if(Gate::allows('appointments_export')): ?>
                                <div class="delete-records export-appointments">
                                    <a onclick="changeLimitOffset($(this));" title="On each click Max 1000 records will be export." id="appointment_exports" href="<?php echo e(route('admin.appointments.export', [1000, 0])); ?>" class="btn btn-primary font-weight-bolder">
                                        <i class="la la-file-export"></i> Export
                                    </a>
                                </div>
                            <?php endif; ?>
                        <!--end::Button-->
                        </div>

                    </div>

                    <!--Start Appointment Section-->
                    <div class="card-body appointment appointment-section">
                        <!--begin::Search Form-->
                        <?php echo $__env->make('admin.appointments.filters', ['custom_reset' => 'custom_reset'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                        <!--end::Search Form-->

                        <!--begin: Datatable-->
                        <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
                        <!--end: Datatable-->
                    </div>
                    <!--End Appointment Section-->

                    <!--Start Consultancy Section-->
                    <div class="card-body appointment consultancy-section d-none">

                        <?php echo $__env->make('admin.appointments.consultancy.filters', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

                        <div id="consultancy_calendar" style="position: relative">

                            
                            <div class="appointment-loader-base" style="display: none;">
                                <div class="blockui"> <span>Please wait...</span>
                                    <span>
                                        <div class="spinner spinner-primary"></div>
                                    </span>
                                </div>
                            </div>
                            

                        </div>

                    </div>
                    <!--End Consultancy Section-->

                </div>
                <!--end::Card-->

            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->

    
    <?php echo $__env->make('admin.appointments.appointment-forms.modals', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <?php $__env->startPush('js'); ?>

        <script>

            let appointment_limit = '<?php echo e(config('constants.export-appointment-limit')); ?>';

            var limit = '<?php echo e(config('constants.export-appointment-limit')); ?>';
            var offset = 0;

            $(document).ready(function () {
                $("#appointment_exports").attr('href', route('admin.appointments.export', [limit, offset]));
            })

            function changeLimitOffset($this) {
                limit = parseInt(limit) + parseInt(appointment_limit);
                offset = parseInt(offset) + parseInt(appointment_limit);
                setTimeout( function () {
                    $this.attr('href', route('admin.appointments.export', [limit, offset]));
                },1000);
            }

            $(document).ready(function () {
                setTimeout( function () {
                    setDashboardFilters();
                },1500)

            });

            function setDashboardFilters() {

                let result = get_query();

                if(result?.type != null ) {

                    $("#appoint_search_type").val('<?php echo e(request('type')); ?>').change();
                    $("#appoint_search_start").val('<?php echo e(request('from')); ?>');
                    $("#appoint_appoint_end").val('<?php echo e(request('to')); ?>');
                    <?php
                        $ids = explode(',', request('center_id'));
                    ?>
                        <?php if(count($ids) == 1): ?>
                            $("#appoint_search_centre").val('<?php echo e(request('center_id')); ?>').change();
                        <?php endif; ?>

                    $("#appoint_search_status").val('<?php echo e(request('appoint_status')); ?>').change();

                    datatable.search({
                        location_id: '<?php echo e(request('center_id')); ?>',
                        appointment_type_id: '<?php echo e(request('type')); ?>',
                        date_from: '<?php echo e(request('from')); ?>',
                        date_to: '<?php echo e(request('to')); ?>',
                        appointment_status_id: '<?php echo e(request('appoint_status')); ?>',
                        filter: 'filter',
                    }, 'search');
                }
            }

            function getUserCity() {

                setTimeout( function () {

                    let city_value = $("#consultancy_city_filter").val();

                    if (city_value == null || city_value == '') {

                        <?php if(auth()->id() != 1): ?>

                        $.ajax({
                            url: '<?php echo e(route('admin.users.get_cities')); ?>',
                            type: 'GET',
                            dataType: 'json',
                            success: function (response) {
                                if (response.status) {
                                    $("#consultancy_city_filter").val(response.data.city).change();
                                    $("#treatment_city_filter").val(response.data.city).change();
                                    $("#appoint_search_city").val(response.data.city).change();
                                    setTimeout( function () {
                                        getUserCentre();
                                    }, 400);
                                }
                            },
                            error: function () {

                            }
                        });

                        <?php endif; ?>

                    }

                }, 500);

            }

            function getUserCentre() {
                $.ajax({
                    url: '<?php echo e(route('admin.users.get_centers')); ?>',
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        if (response.status) {
                            $("#consultancy_location_filter").val(response.data.center).change();
                            $("#treatment_location_filter").val(response.data.center).change();
                            $("#appoint_search_centre").val(response.data.center).change();
                        }
                    },
                    error: function () {

                    }
                });
            }

        </script>
        <script src="<?php echo e(asset('assets/js/pages/appointment/invoice.js?v=1')); ?>"></script>
        <script src="<?php echo e(asset('assets/js/pages/appointment/consultancy-calendar.js')); ?>"></script>

        <script src="<?php echo e(asset('assets/plugins/custom/fullcalendar/fullcalendar.bundle.js')); ?>"></script>
        <script src="<?php echo e(asset('assets/js/pages/appointment/consultancy-data.js')); ?>"></script>
        

        <script src="<?php echo e(asset('assets/js/pages/crud/forms/validation/appointment/validation.js')); ?>"></script>
        <script src="<?php echo e(asset('assets/js/pages/appointment/plan/create.js')); ?>"></script>
        <script src="<?php echo e(asset('assets/js/pages/appointment/common.js')); ?>"></script>

    <?php $__env->stopPush(); ?>

    <?php $__env->startPush('datatable-js'); ?>

        <script src="<?php echo e(asset('assets/js/pages/appointment/datatable.js')); ?>"></script>
    <?php $__env->stopPush(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.master', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /var/www/html/resources/views/admin/appointments/index.blade.php ENDPATH**/ ?>