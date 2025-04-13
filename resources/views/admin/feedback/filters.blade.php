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
        

        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Phone:</label>
            <input type="number" oninput="phoneField(this);" class="form-control filter-field" placeholder="e.g: 0300XXXXXXX" id="search_phone" />
        </div>
        
        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Centre:</label>
            <select class="form-control filter-field select2" id="search_location_id">
                <option value="">Select</option>
                <option value="2">CUTERA DHA Karachi</option>
                <option value="3">CUTERA Bahadurabad Karachi</option>
                <option value="46">CUTERA Johar Town, Lahore</option>
                <option value="47">CUTERA Gulshan/Johar Karachi</option>
                <option value="48">CUTERA DHA Lahore</option>
                <option value="49">CUTERA Gulberg Lahore</option>
                <option value="50">CUTERA Faisalabad</option>
                
            </select>
        </div>
        
    </div>

    <div class="row mb-8 advance-filters" style="display: none;">

       
        <div class="col-md-3 mb-lg-0 mb-6">
            {!! Form::label('date_range', 'Created at:', ['class' => 'control-label']) !!}
            <div class="input-group">
                {!! Form::text('date_range', null, ['id' => 'date_range', 'class' => 'form-control', 'autocomplete' => 'off', 'placeholder' => 'Select Date Range']) !!}
            </div>
        </div>
       
    </div>


    <div class="row">
        <div class="col-md-10">

            @include('admin.partials.filter-buttons')

        </div>
    </div>
</div>
