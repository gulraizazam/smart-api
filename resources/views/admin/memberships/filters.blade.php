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
            <label>Code:</label>
            <input class="form-control filter-field" id="search_code_name" placeholder="Enter Code">
        </div>
        <div class="col-md-3 mb-lg-0 mb-6">
            {!! Form::label('date_range', 'Created at:', ['class' => 'control-label']) !!}
            <div class="input-group">
                {!! Form::text('date_range', null, ['id' => 'date_range', 'class' => 'form-control', 'autocomplete' => 'off', 'placeholder' => 'Select Date Range']) !!}
            </div>
        </div>
        <div class="col-lg-3 mb-lg-0">
            <label>Membership Type:</label>
            <select class="form-control filter-field select2" id="search_membership_type">
                <option value="">Select</option>
                <option value="3">Student Membership</option>
                <option value="4">Gold Membership</option>
            </select>
        </div>
        <div class="col-md-4 mt-8">

@include('admin.partials.filter-buttons')

</div>
    </div>

    <div class="row">
       
    </div>
</div>
