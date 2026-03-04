<div class="mt-2 mb-7">

    
    <div class="row mb-6">

        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Name:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter Name" id="search_name"/>
        </div>
        <!-- <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Price:</label>
            <input type="text" oninput="phoneField(this);" class="form-control filter-field" placeholder="Enter Price" id="search_price"/>
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Total Services:</label>
            <input type="number" class="form-control filter-field" placeholder="Enter Total Services"
                   id="search_total_services"/>
        </div> -->
        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Valid From:</label>
                <input type="text" id="search_startdate" class="custom-datepicker form-control filter-field datatable-input"
                       placeholder="Valid From" data-col-index="5">
        </div>
        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Valid Till:</label>
            <input type="text" id="search_enddate" class="custom-datepicker form-control filter-field datatable-input"
                   placeholder="Valid To" data-col-index="5">
        </div>
        @if(\Illuminate\Support\Facades\Gate::allows("view_inactive_packages"))
            <div class="col-lg-2 mb-lg-0 mb-6">
                <label>Status:</label>
                <select class="form-control filter-field select2" name="status" id="search_status">

                </select>
            </div>
        @endif
        <div class="col-lg-2 mb-lg-0 mt-7">
            @include('admin.partials.filter-buttons')
        </div>
    </div>
    <div class="row mb-6 advance-filters" style="display: none;">
        
        <!-- <div class="col-lg-3 mb-lg-0 mb-6">
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
        </div> -->
        


    </div>
   
</div>


