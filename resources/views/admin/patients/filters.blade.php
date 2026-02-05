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
            <label>Patient Search:</label>
            <select class="form-control select2-patient-search" id="search_patient_id" name="search_patient_id">
                <option value=""></option>
            </select>
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Name:</label>
            <input class="form-control filter-field" id="search_name" placeholder="Enter Name">
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Membership:</label>
            <select class="form-control filter-field select2" id="search_membership">
            </select>
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Gender:</label>
            <select class="form-control filter-field select2" id="search_gender">
            </select>
        </div>
    </div>
    <div class="row mb-8 advance-filters" style="display: none;">
        <div class="col-md-3 mb-lg-0 mb-6 @if($errors->has('date_range')) has-error @endif">
            {!! Form::label('date_range', 'Created at:', ['class' => 'control-label']) !!}
            <div class="input-group">
                {!! Form::text('date_range', null, ['id' => 'date_range', 'class' => 'form-control', 'autocomplete' => 'off', 'placeholder' => 'Select Date Range']) !!}
            </div>
        </div>
        @if(\Illuminate\Support\Facades\Gate::allows("view_inactive_patients"))
        <div class="col-lg-3 mb-lg-0">
            <label>Status:</label>
            <select class="form-control filter-field select2" id="search_status">
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