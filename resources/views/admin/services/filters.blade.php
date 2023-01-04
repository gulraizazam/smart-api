
<div class="mt-2 mb-7">
    <div class="row align-items-center">

        <div class="col-lg-12 col-xl-12">
            <div class="row align-items-center">
                <div class="col-md-5">
                    <label>Name:</label>
                    <input type="text" class="form-control filter-field" placeholder="Name" id="search_name" />
                </div>
                @if(\Illuminate\Support\Facades\Gate::allows("view_inactive_services"))
                    <div class="col-md-4">
                        <label>Status:</label>
                        <select class="form-controll filter-field select2" id="search_status">
                            <option value="">All</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>

                        </select>
                    </div>
                @endif
                <div class="col-md-3 mt-10">

                    @include('admin.partials.filter-buttons')

                </div>

            </div>
        </div>

    </div>
</div>
