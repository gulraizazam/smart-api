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
            <label>Search Lead:</label>
            <input type="text" class="form-control lead_search_filter" placeholder="Search by ID, Name or Phone" id="lead_search_filter" autocomplete="off" />
            <input type="hidden" id="search_id" class="filter-field" />
            <input type="hidden" id="search_full_name" class="filter-field" />
            <input type="hidden" id="search_phone" class="filter-field" />
            <div class="suggesstion-box-leads" style="display: none; position: absolute; z-index: 1000; background: white; border: 1px solid #ddd; max-height: 300px; overflow-y: auto; width: calc(100% - 30px);">
                <ul class="suggestion-list-leads" style="list-style: none; padding: 0; margin: 0;"></ul>
            </div>
        </div>
        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>City:</label>
            <select class="form-control filter-field select2" id="search_city_id" onchange="LoadLoc()"></select>
        </div>
        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Centre:</label>
            <select class="form-control filter-field select2" id="search_location_id">
                <option value="">All</option>
            </select>
        </div>
        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Service:</label>
            <select class="form-control filter-field select2" id="search_service_id"></select>
        </div>
        @if(request('type') == '')
        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Lead Status:</label>
            <select class="form-control filter-field select2" id="search_status_id"></select>
        </div>
        @endif
        @if(request('type') == 'junk')
        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Service:</label>
            <select class="form-control filter-field select2" id="search_service_id"></select>
        </div>
        @endif

    </div>

    <div class="row mb-8 advance-filters" style="display: none;">
        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Gender:</label>
            <select class="form-control filter-field select2" id="search_gender_id">
                <option value="">Select</option>
                <option value="1">Male</option>
                <option value="2">Female</option>
            </select>
        </div>
        <div class="col-md-3 mb-lg-0 mb-6">
            {!! Form::label('date_range', 'Created at:', ['class' => 'control-label']) !!}
            <div class="input-group">
                {!! Form::text('date_range', null, ['id' => 'date_range', 'class' => 'form-control', 'autocomplete' => 'off', 'placeholder' => 'Select Date Range']) !!}
            </div>
        </div>
        <div class="col-lg-3 mb-lg-0">
            <label>Created By:</label>
            <select class="form-control filter-field select2" id="search_created_by">
            </select>
        </div>
    </div>


    <div class="row">
        <div class="col-md-10">

            @include('admin.partials.filter-buttons')

        </div>
    </div>
</div>
