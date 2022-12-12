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
            <label>Name:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter Name" id="search_name"/>
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Price:</label>
            <input type="text" oninput="phoneField(this);" class="form-control filter-field" placeholder="Enter Price" id="search_price"/>
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Total Services:</label>
            <input type="number" class="form-control filter-field" placeholder="Enter Total Services"
                   id="search_total_services"/>
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Apply Discount:</label>
            <select class="form-control filter-field select2" id="search_apply_discount">

            </select>
        </div>
    </div>
    <div class="row mb-6 advance-filters" style="display: none;">
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Valid From:</label>
                <input type="text" id="search_startdate" class="custom-datepicker form-control filter-field datatable-input"
                       placeholder="Valid From" data-col-index="5">
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Valid To:</label>
            <input type="text" id="search_enddate" class="custom-datepicker form-control filter-field datatable-input"
                   placeholder="Valid To" data-col-index="5">
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Create at:</label>
            <div class="input-daterange input-group to-from-datepicker">
                <input type="text" id="search_created_from" class="form-control filter-field datatable-input"
                       name="created_from" placeholder="From" data-col-index="5">
                <div class="input-group-append">
                    <span class="input-group-text">
                        <i class="la la-ellipsis-h"></i>
                    </span>
                </div>
                <input type="text" id="search_created_to" class="form-control filter-field datatable-input"
                       name="created_to" placeholder="To" data-col-index="5">
            </div>
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Status:</label>
            <select class="form-control filter-field select2" name="status" id="search_status">

            </select>
        </div>


    </div>
    <div class="row">
        <div class="col-md-10">
            @include('admin.partials.filter-buttons')
        </div>
    </div>

</div>


