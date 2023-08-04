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
            <label>Patient ID:</label>
            <input class="form-control filter-field" id="search_id">
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6" id="patient_id">

            <label>Patient Search:</label>
            <input class="form-control filter-field search_patient">
            <input type="hidden" class="filter-field search_field" id="search_patient">
            <span onclick="addUsers();" class="croxcli" style="padding-left: 0% !important; top:36px; right:22px; position: absolute;"><i class="fa fa-times" aria-hidden="true"></i></span>
            <div class="suggesstion-box" style="display: none;">
                <ul class="suggestion-list"></ul>
            </div>

        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Centres:</label>
            <select class="form-control filter-field select2" id="search_centres">
            </select>
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Plans:</label>
            <select class="form-control filter-field package_id" id="search_plans">
            </select>
        </div>


    </div>

    <div class="row mb-8 advance-filters" style="display: none;">

        <div class="col-md-3 mb-lg-0 mb-6 @if($errors->has('date_range')) has-error @endif">
            {!! Form::label('date_range', 'Created at:', ['class' => 'control-label']) !!}
            <div class="input-group">
                {!! Form::text('date_range', null, ['id' => 'date_range', 'class' => 'form-control', 'autocomplete' => 'off']) !!}
            </div>
        </div>


    </div>

    <div class="row">
        <div class="col-md-10">

            @include('admin.partials.filter-buttons')

        </div>
    </div>
</div>
