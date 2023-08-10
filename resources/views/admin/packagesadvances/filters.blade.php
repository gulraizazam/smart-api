@push("css")
    <style>

       .position-relative{
            position: relative;
        }

        .filterouterdiv .croxcli {
            position: absolute;
            bottom: 0px;
            right: 0;
            padding-left: 11px !important;
            padding: 9px 11px;
        }

    </style>
@endpush

<div class="mt-2 mb-7">
    <div class="row mb-0 flex-column flex-sm-row">
        <div class="filterouterdiv mb-0 position-relative">
            <label>Patient Search:</label>
            <input class="form-control filter-field patient_search_id" id="patient_search_id" onchange="patientSearch()">
            <input type="hidden" id="add_patient_id" name="patient_id">
            <span onclick="addUsers()" class="croxcli" ><i class="fa fa-times" aria-hidden="true"></i></span>
            <div class="suggesstion-box" style="display: none;">
                <ul class="suggestion-list w-100"></ul>
            </div>
        </div>

        <div class="col-md-3 mb-lg-0 mb-6 @if($errors->has('date_range')) has-error @endif">
            {!! Form::label('date_range', 'Created at:', ['class' => 'control-label']) !!}
            <div class="input-group">
                {!! Form::text('date_range', null, ['id' => 'date_range', 'class' => 'form-control', 'autocomplete' => 'off', 'placeholder' => 'Select Date Range']) !!}
            </div>
        </div>

        <div class="col-md-3 mb-lg-0 mt-8">
            @include('admin.partials.filter-buttons', ['custom_reset' => $custom_reset])
        </div>
    </div>
</div>
