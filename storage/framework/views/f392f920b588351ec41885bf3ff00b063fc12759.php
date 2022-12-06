<div class="mt-2 mb-7">

    <div class="row align-items-center">
        <div class="advance-search col-md-12 col-lg-12 col-xl-12">
            <div class="row align-items-center mr-2" style="float: right;">
                <div class="row">
                    <button class="btn btn-sm btn-default ml-2 mt-10" onclick="advanceFilters();">
                        <i class="advance-arrow fa fa-caret-right"></i>
                        Advance
                    </button>
                </div>
            </div>
        </div>
    </div>



    <div class="row mb-6">

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>ID:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter ID" id="search_id" />
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Full Name:</label>
            <input class="form-control filter-field" id="search_full_name" placeholder="Enter Name">
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Phone:</label>
            <input type="text" oninput="phoneField(this);" class="form-control filter-field" placeholder="e.g: 0300XXXXXXX" id="search_phone" />
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>City:</label>
            <select class="form-control filter-field select2" id="search_city_id"></select>
        </div>


    </div>

    <div class="row mb-8 advance-filters" style="display: none;">

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Region:</label>
            <select class="form-control filter-field select2" id="search_region_id"></select>
        </div>

        <?php if(request('type') == ''): ?>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Lead Status:</label>
            <select class="form-control filter-field select2" id="search_status_id"></select>
        </div>
        <?php endif; ?>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Service:</label>
            <select class="form-control filter-field select2" id="search_service_id"></select>
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

    </div>

    <div class="row mb-8 advance-filters" style="display: none;">
        <div class="col-lg-3 mb-lg-0">
            <label>Created By:</label>
            <select class="form-control filter-field select2" id="search_created_by">
            </select>
        </div>
    </div>

    <div class="row">
        <div class="col-md-10">

            <?php echo $__env->make('admin.partials.filter-buttons', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        </div>
    </div>
</div>
<?php /**PATH /var/www/cuterav2.test/resources/views/admin/leads/filters.blade.php ENDPATH**/ ?>