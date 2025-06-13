<div class="mt-2 mb-7">
    <input type="hidden" class="form-control filter-field" id="search_location_from" name="location_from">
    <input type="hidden" class="form-control filter-field" id="search_location_to" name="location_to">

    <div class="row mb-6">
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Name:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter Name" id="search_name" />
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Transfer From:</label>
            <select class="form-control filter-field select2" name="transfer_from" id="search_transfer_from">
            </select>
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Transfer To:</label>
            <select class="form-control filter-field select2" name="transfer_to" id="search_transfer_to">
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
