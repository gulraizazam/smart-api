<div class="mt-2 mb-7">

    <div class="row mb-6">

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Name:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter Name" id="search_name" />
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Featured:</label>
            <select class="form-control filter-field select2" name="payment_type" id="search_is_featured">
            </select>
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Region:</label>
            <select class="form-control filter-field select2" name="type" id="search_region_id">

            </select>
        </div>
        @if(\Illuminate\Support\Facades\Gate::allows("view_inactive_cities"))
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Status:</label>
            <select class="form-control filter-field select2" name="status" id="search_status">

            </select>
        </div>
        @endif
    </div>
    <div class="row">
        <div class="col-md-10">

            @include('admin.partials.filter-buttons')

        </div>
    </div>
</div>


