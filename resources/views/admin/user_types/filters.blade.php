<div class="mt-2 mb-7">
    <div class="row align-items-center">
        <div class="col-lg-12 col-xl-12">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <label>Name:</label>
                    <input type="text" class="form-control filter-field" placeholder="Name" id="search_name" />
                </div>

                <div class="col-md-3">
                    <label>Type:</label>
                    <input type="text" class="form-control filter-field" placeholder="Type" id="search_type" />
                </div>

                <div class="col-md-3 mt-10">
                    @include('admin.partials.filter-buttons')
                </div>
            </div>
        </div>
    </div>
</div>
