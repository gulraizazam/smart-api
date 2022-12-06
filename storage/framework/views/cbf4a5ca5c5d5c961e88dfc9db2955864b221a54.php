
<div class="mt-2 mb-7">
    <div class="row align-items-center">

        <div class="col-lg-12 col-xl-12">
            <div class="row align-items-center">
                <div class="col-md-5">
                    <label>Name:</label>
                    <input type="text" class="form-control filter-field" placeholder="Name" id="search_name" />
                </div>

                <div class="col-md-4">
                    <label>Status:</label>
                    <select class="form-controll filter-field select2" id="search_status">
                        <option value="">All</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>

                    </select>
                </div>


                <div class="col-md-3 mt-10">

                    <?php echo $__env->make('admin.partials.filter-buttons', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

                </div>

            </div>
        </div>

    </div>
</div>
<?php /**PATH /var/www/html/resources/views/admin/services/filters.blade.php ENDPATH**/ ?>