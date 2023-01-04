<div class="mt-2 mb-7">
     <div class="row mb-6">

        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Name:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter Name" id="search_name" />
        </div>
        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Valid From:</label>
            <div class="input-daterange input-group to-from-datepicker" >
                <input type="text" class="form-control filter-field datatable-input" autocomplete="off" placeholder="From" id="search_start" />

            </div>
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Valid Till:</label>
            <div class="input-daterange input-group to-from-datepicker" >
                <input type="text" class="form-control filter-field datatable-input" autocomplete="off"placeholder="To" id="search_end" />
            </div>
        </div>
        @if(\Illuminate\Support\Facades\Gate::allows("view_inactive_discounts"))
            <div class="col-lg-2 mb-lg-0 mb-6">
                <label>Status:</label>
                <select class="form-control filter-field select2" name="status" id="search_status">
                </select>
            </div>
        @endif
        <div class="col-lg-2 mb-lg-0 mb-6 mt-6">

            @include('admin.partials.filter-buttons')

        </div>
    </div>
   
</div>
