
<div class="mt-2 mb-7">
    <div class="row align-items-center">

        <div class="col-lg-12 col-xl-12">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <label>Name:</label>
                    <input type="text" value="{{$filters['name'] ?? ''}}" class="form-control filter-field" placeholder="Name" id="search_name" />
                </div>

                <div class="col-md-3">
                    <label>Type:</label>
                    <input type="text" value="{{$filters['type'] ?? ''}}" class="form-control filter-field" placeholder="Type" id="search_type" />
                </div>


                <div class="col-md-2 mt-10">
                    <div class="input-icon">
                        <button class="btn btn-sm btn-success" id="apply-filters">Search</button>
                        <button class="btn btn-sm btn-danger ml-2" onclick="resetFilters();" id="reset-filters">Reset</button>

                    </div>
                </div>

            </div>
        </div>

    </div>
</div>
