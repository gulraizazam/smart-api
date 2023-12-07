<div class="mt-2 mb-7">

    <div class="row align-items-center">
        <div class="advance-search col-md-12 col-lg-12 col-xl-12">
            <div class="row align-items-center mr-2" style="float: right;">
                
            </div>
        </div>
    </div>

    <div class="row mb-6">
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Name:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter Name" id="search_name" />
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
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>City:</label>
            <select class="form-control filter-field select2" name="city" id="search_city">
                <option value="">Select</option>
            </select>
        </div>
        @if (\Illuminate\Support\Facades\Gate::allows('view_inactive_warehouse'))
            <div class="col-lg-3 mb-lg-0 mb-6">
                <label>Status:</label>
                <select class="form-control filter-field select2" name="status" id="search_status">
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
