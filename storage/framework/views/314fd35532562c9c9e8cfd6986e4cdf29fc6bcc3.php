
<div class="mt-2 mb-7">
    <div class="row mb-6">
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Name:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter Name" id="search_name" />
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Slug:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter Slug" id="search_slug" />
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Status:</label>
            <select class="form-control filter-field select2" name="status" id="search_status">

            </select>
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6 mt-8">
            <?php echo $__env->make('admin.partials.filter-buttons', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
    </div>
</div>


<?php /**PATH /var/www/cuterav2.test/resources/views/admin/sms_templates/filters.blade.php ENDPATH**/ ?>