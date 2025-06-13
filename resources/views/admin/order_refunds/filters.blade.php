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
        <input type="hidden" name="location_type" id="search_location_type">
        <div class="col-md-3 mb-lg-0 mb-6">
            <label>Order Id:</label>
            <input class="form-control" id="search_order_id" name="order_id" placeholder="Order Id Search">
        </div>
        <div class="fv-row col-md-3">
            <label>Patient Search </label>
            <input class="form-control order_patient_search_id patient_search_id search_field" placeholder="Patients Search">

            <input type="hidden" id="order_patient_search" name="patient_id" class="filter-field search_field">
            <span onclick="addUsers()" class="croxcli"
                style="position:absolute; padding-left: 0% !important; top:37px; right:20px;"><i class="fa fa-times"
                    aria-hidden="true"></i></span>
            <div class="suggesstion-box" style="display: none;">
                <ul class="suggestion-list"></ul>
            </div>
        </div>

        <div class="col-md-3 mb-lg-0 mb-6">
            <label>Product:</label>
            <input class="form-control filter-field" name="product_id" id="search_product_id" placeholder="Product search">
        </div>
        <div class="col-md-3 mb-lg-0 mb-6">
            <label>Location:</label>
            <select class="form-control filter-field select2" name="location" id="search_location">
            </select>
        </div>
    </div>
    <div class="row mb-8 advance-filters" style="display: none;">
        <div class="col-md-3 mb-lg-0 mb-6">
            <label>Created By:</label>
            <select class="form-control filter-field select2" name="created_by" id="search_created_by">
            </select>
        </div>
       
        <div class="col-lg-3 mb-lg-0 mb-6 @if ($errors->has('date_range')) has-error @endif">
            {!! Form::label('date_range', 'Created at:', ['class' => 'control-label']) !!}
            <div class="input-group">
                {!! Form::text('date_range', null, [
                    'id' => 'date_range',
                    'class' => 'form-control',
                    'autocomplete' => 'off',
                    'placeholder' => 'Select Date Range',
                ]) !!}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-10">
            @include('admin.partials.filter-buttons')
        </div>
    </div>
</div>
