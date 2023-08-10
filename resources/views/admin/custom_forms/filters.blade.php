<div class="mt-2 mb-7">

    <div class="row mb-6">

        <div class="col-lg-3 mb-lg-0 mb-6" id="patient_id">
            <label>Name:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter Name" id="search_name" />
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Form Type:</label>

            <select id="search_form_type" name="form_type_id" class="form-control select2">

            </select>
        </div>

        <div class="col-md-3 mb-lg-0 mb-6 @if($errors->has('date_range')) has-error @endif">
            {!! Form::label('date_range', 'Created at:', ['class' => 'control-label']) !!}
            <div class="input-group">
                {!! Form::text('date_range', null, ['id' => 'date_range', 'class' => 'form-control', 'autocomplete' => 'off', 'placeholder' => 'Select Date Range']) !!}
            </div>
        </div>
        @if(\Illuminate\Support\Facades\Gate::allows("view_inactive_custom_forms"))
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Status:</label>

            <select id="search_status" name="status" class="form-control select2">

            </select>
        </div>
        @endif

    </div>


    <div class="row">
        <div class="col-md-12">

            @include('admin.partials.filter-buttons')

        </div>
    </div>
</div>
