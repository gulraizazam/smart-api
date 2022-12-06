<?php $__env->startSection('content'); ?>

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    <?php echo $__env->make('admin.partials.breadcrumb', ['module' => 'Leads List', 'title' => 'Leads'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

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
                            <h3 class="card-label"><?php echo e(\Illuminate\Support\Str::title(request('type'))); ?> Leads</h3>

                        </div>

                        <div class="card-toolbar">
                            <!--begin::Dropdown-->
                            <?php if(Gate::allows('leads_destroy')): ?>
                                <div class="delete-records d-none">
                                    <span>Selected Rows: <span class="checkbox-count"></span></span>
                                    <a id="delete-table-rows" href="javascript:void(0);" class="btn btn-danger font-weight-bolder">
                                        <i class="fa fa-trash-alt"></i>Delete
                                    </a>
                                </div>&nbsp;&nbsp;&nbsp;
                            <?php endif; ?>

                            <?php if(Gate::allows('leads_import')): ?>
                                <a href="javascript:void(0);" data-toggle="modal" data-target="#modal_import_leads" class="btn btn-primary pull-right margin-r-5">
                                    <i class="fa fa-upload"></i>
                                    <span class="hidden-xs"> Import </span>
                                </a>
                            <?php endif; ?>
                            &nbsp;&nbsp;
                            <?php if(Gate::allows('leads_export')): ?>
                                <div class="btn-group">
                                    <a class="btn  btn-primary" href="javascript:void(0);" data-toggle="dropdown">
                                        <i class="fa fa-download"></i>
                                        <span class="hidden-xs"> Export </span>
                                        <i class="fa fa-angle-down"></i>
                                    </a>
                                    <ul class="dropdown-menu pull-right export_leads" id="datatable_ajax_tools">
                                        <li>
                                            <a href="#" title="Max pdf export limit is 100 records" id="export-pdf-leads" data-href="<?php echo e(route('admin.leads.export.pdf')); ?>" data-action="0" class="tool-action"><i class="la la-file-pdf"></i>
                                                PDF
                                                <!-- <span class="export-pdf-limit">(1 to <?php echo e(config('constants.export-lead-pdf-limit')); ?>)</span></a> -->
                                            </a>
                                        </li>
                                        <li>
                                            <a href="#" title="Max export limit is 1000 records" id="export-leads" data-href="<?php echo e(route('admin.leads.export.excel')); ?>" data-action="1" class="tool-action"><i class="la la-file-excel"></i>
                                                Excel
                                                <!-- <span class="export-excel-limit">(1 to <?php echo e(config('constants.export-lead-excel-limit')); ?>)</span> -->
                                            </a>
                                        </li>
                                        <li>
                                            <a href="#" data-href="<?php echo e(route('admin.leads.export.excel')); ?>" id="csv-leads" data-action="2" class="tool-action"><i class="la la-file-csv"></i> CSV</a>
                                        </li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            &nbsp;&nbsp;
                            <?php if(Gate::allows('leads_create')): ?>
                                <a href="javascript:void(0);" id="create_lead" onclick="createLead('<?php echo e(route('admin.leads.create')); ?>');" class="btn btn-primary" data-toggle="modal" data-target="#modal_add_leads">
                                    <i class="la la-plus"></i>
                                    Add New
                                </a>
                            <?php endif; ?>

                        <!--end::Button-->
                        </div>

                    </div>

                    <div class="card-body">
                        <!--begin::Search Form-->
                        <?php echo $__env->make('admin.leads.filters', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
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

    <div class="modal fade" id="modal_change_status" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="leads_change_status">

            <?php echo $__env->make('admin.leads.change-status', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_view_lead" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered mediam-modal" id="leads_view_lead">

            <?php echo $__env->make('admin.leads.view', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_convert_lead" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered mediam-modal" id="convert_lead">

            <?php echo $__env->make('admin.leads.convert', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_add_leads" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="leads_add">

            <?php echo $__env->make('admin.leads.create', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_edit_leads" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="edit_leads">

            <?php echo $__env->make('admin.leads.edit', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_import_leads" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="import_leads">

            <?php echo $__env->make('admin.leads.import', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        </div>
        <!--end::Modal dialog-->
    </div>

    <?php $__env->startPush('js'); ?>
        <script src="<?php echo e(asset('assets/js/jquery.inputmask.bundle.min.js')); ?>"></script>
        <script src="<?php echo e(asset('assets/js/jquery.copy-to-clipboard.js')); ?>"></script>

        <script src="<?php echo e(asset('assets/js/pages/crud/forms/validation/leads/leads.js')); ?>"></script>
        <script src="<?php echo e(asset('assets/js/search-phone.js')); ?>"></script>
    <?php $__env->stopPush(); ?>

    <?php $__env->startPush('datatable-js'); ?>
        <script>

            let lead_type = '<?php echo e(request('type')); ?>';
            let junk = '<?php echo e(config('constants.lead_status_junk')); ?>';

            var limit = '<?php echo e(config('constants.export-lead-excel-limit')); ?>';
            var offset = 0;

            var pdf_limit = '<?php echo e(config('constants.export-lead-pdf-limit')); ?>';
            var pdf_offset = 0;

            $(document).ready(function () {
                //$("#export-leads").attr('href', route('admin.leads.export.excel', [limit, offset]));

                //$("#export-pdf-leads").attr('href', route('admin.leads.export.pdf', [pdf_limit, pdf_offset]));
            });

            function setExportLimit($this) {

                let previousLimit = limit;
                let next = <?php echo e(config('constants.export-lead-excel-limit')); ?>;
                limit = parseInt(limit) + parseInt(next);
                offset = parseInt(offset) + parseInt(next);

                setTimeout( function () {
                    $this.attr('href', route('admin.leads.export.excel', [limit, offset]));

                    $(".export-excel-limit").text("("+previousLimit+" to "+limit+")")
                },1000);
            }

            function setPdfLimit($this) {

                let pdf_previousLimit = pdf_limit;
                let next = <?php echo e(config('constants.export-lead-pdf-limit')); ?>;

                pdf_limit = parseInt(pdf_limit) + parseInt(next);
                pdf_offset = parseInt(pdf_offset) + parseInt(next);

                setTimeout( function () {
                    //$this.attr('href', route('admin.leads.export.pdf', [pdf_limit, pdf_offset]));

                    $(".export-pdf-limit").text("("+pdf_previousLimit+" to "+pdf_limit+")")
                },1000);
            }
        </script>
        <script src="<?php echo e(asset('assets/js/pages/leads/leads.js')); ?>"></script>

        <script>
            jQuery(document).ready( function () {
                <?php if(request('create') != '' && request('create') !== null): ?>
                    $("#create_lead").click()
                <?php endif; ?>

                <?php if(request('from') != '' && request('to') != ''): ?>
                    setTimeout( function () {

                        $("#search_created_from").val("<?php echo e(request('from')); ?>");
                        $("#search_created_to").val("<?php echo e(request('to')); ?>");
                        $("#apply-filters").click();

                    }, 800);

                <?php endif; ?>
            });

            function getUserCity() {

                <?php if(auth()->id() != 1): ?>

                $.ajax({
                    url: '<?php echo e(route('admin.users.get_cities')); ?>',
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        if (response.status) {
                            $("#search_city_id").val(response.data.city).change();
                            $("#add_city_id").val(response.data.city).change();
                        }
                    },
                    error: function () {

                    }
                });

                <?php endif; ?>

            }

        </script>
    <?php $__env->stopPush(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.master', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /var/www/cuterav2.test/resources/views/admin/leads/index.blade.php ENDPATH**/ ?>