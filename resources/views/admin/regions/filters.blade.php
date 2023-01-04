
<div class="mt-2 mb-7">

    <div class="row mb-6">

        <div class="col-lg-6 mb-lg-0 mb-6">
            <label>Name:</label>
            <input type="text" value="{{$filters['name'] ?? ''}}" class="form-control filter-field" placeholder="Enter Name" id="search_name" />
        </div>
        @if(\Illuminate\Support\Facades\Gate::allows("view_inactive_regions"))
            <div class="col-lg-3 mb-lg-0 mb-6">
                <label>Status:</label>
                <select class="form-control filter-field select2" name="status" id="search_status">

                </select>
            </div>
        @endif
        <div class="col-lg-3 mb-lg-0 mb-6 mt-8">
            @include('admin.partials.filter-buttons')
        </div>
    </div>
</div>


