<div class="mt-2 mb-7">

    <div class="row mb-6">

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Year:</label>
            <select class="form-control filter-field select2" id="search_year">
            </select>
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Month:</label>
            <select class="form-control filter-field select2" id="search_month">
            </select>
        </div>

        <div class="col-md-3 mb-lg-0 mb-6 @if($errors->has('date_range')) has-error @endif">
            {!! Form::label('date_range', 'Created at:', ['class' => 'control-label']) !!}
            <div class="input-group">
                {!! Form::text('date_range', null, ['id' => 'date_range', 'class' => 'form-control', 'autocomplete' => 'off', 'placeholder' => 'Select Date Range']) !!}
            </div>
        </div>

        <div class="col-md-3 mb-lg-0 mt-8">

            @include('admin.partials.filter-buttons')

        </div>

    </div>

</div>
