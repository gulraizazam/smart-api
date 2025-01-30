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
            <label>Brand:</label>
            <select class="form-control filter-field select2" name="brand_id" id="search_brand_id">
            </select>
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Name:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter Name" id="search_name" />
        </div>

        <!-- <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Product Type:</label>
            <select class="form-control filter-field select2" name="product-type" id="search_product_type">
                <option value="" selected disabled>Select Product Type</option>
                <option value="in_house_use">In House Use</option>
                <option value="for_sale">For Sale</option>
            </select>
        </div> -->

       
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
