<div class="mt-2 mb-7">

    <div class="row mb-6">

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Patient Id:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter ID" id="search_id" />
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6" id="patient_id">
            <label>Name:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter Name" id="search_name" />
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Patient Name:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter Patient Name" id="search_patient_name" />
        </div>

        <div class="col-md-3 mb-lg-0 mb-6 @if($errors->has('date_range')) has-error @endif">
            {!! Form::label('date_range', 'Created at:', ['class' => 'control-label']) !!}
            <div class="input-group">
                {!! Form::text('date_range', null, ['id' => 'date_range', 'class' => 'form-control', 'autocomplete' => 'off', 'placeholder' => 'Select Date Range']) !!}
            </div>
        </div>

    </div>


    <div class="row">
        <div class="col-md-12">

            @include('admin.partials.filter-buttons')

        </div>
    </div>
</div>
