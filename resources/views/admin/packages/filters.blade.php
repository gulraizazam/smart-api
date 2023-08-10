<div class="mt-2 mb-7">

    <div class="row mb-6 plan-filters align-items-end">

        <!-- <div class="col-lg-1 mb-lg-0 mb-6">
            <label style="width: 122%;">Patient Id:</label>
            <input style="width: 122%;" type="text" class="form-control filter-field" placeholder="Enter ID" id="search_id" />
        </div> -->

        <div class="col-lg-2 mb-lg-0 mb-6" id="patient_id">
            <label>Patient Search:</label>
            <input style="width: 110%;" class="form-control filter-field patient_id"  placeholder="Patients Search">
            <input type="hidden" class="filter-field search_field" id="search_patient_id">
            <span onclick="addUsers()" class="croxcli" style="position:absolute; padding-left: 0% !important; top:37px; right:3px;"><i class="fa fa-times" aria-hidden="true"></i></span>
            <div class="suggesstion-box" style="display: none;">
                <ul class="suggestion-list"></ul>
            </div>
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6 search_input">
            <label>Plan ID:</label>
            <select class="form-control filter-field package_id" id="search_plan_id"></select>
        </div>

        <div class="col-lg-2 mb-lg-0 mb-6">
            <label>Centre:</label>
            <select class="form-control filter-field select2" id="search_location_id"></select>
        </div>
        @if(\Illuminate\Support\Facades\Gate::allows("view_inactive_plans"))
            <div class="col-lg-2 mb-lg-0 mb-6">
                <label>Status:</label>
                <select class="form-control filter-field select2" id="search_status">
                </select>
            </div>
        @endif
        <div class="col-md-2 mb-lg-0 mb-6 @if($errors->has('date_range')) has-error @endif">
            {!! Form::label('date_range', 'Created at:', ['class' => 'control-label']) !!}
            <div class="input-group">
                {!! Form::text('date_range', null, ['id' => 'date_range', 'class' => 'form-control', 'autocomplete' => 'off', 'placeholder' => 'Select Date Range']) !!}
            </div>
        </div>
        <div class="col-lg-2 mb-lg-0 mb-6 pl-0">
            @include('admin.partials.filter-buttons')
        </div>
    </div>

</div>
