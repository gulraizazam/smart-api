
<div class="mt-2 mb-7">
    <div class="row mb-6">
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Name:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter Name" id="search_name" />
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Service:</label>
            <select class="form-control filter-field select2" name="service" id="search_service">

            </select>
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Status:</label>
            <select class="form-control filter-field select2" name="status" id="search_status">

            </select>
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Create at:</label>
            <div class="input-daterange input-group to-from-datepicker" >
                <input type="text" id="search_created_from" autocomplete="off" class="form-control filter-field datatable-input" name="created_from" placeholder="From" data-col-index="5">
                <div class="input-group-append">
                    <span class="input-group-text">
                        <i class="la la-ellipsis-h"></i>
                    </span>
                </div>
                <input type="text" id="search_created_to" autocomplete="off" class="form-control filter-field datatable-input" name="created_to" placeholder="To" data-col-index="5">
            </div>
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6 mt-8">
            <?php echo $__env->make('admin.partials.filter-buttons', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
    </div>
</div>


<?php /**PATH /var/www/cuterav2.test/resources/views/admin/machine_types/filters.blade.php ENDPATH**/ ?>