
<div class="mt-2 mb-7">
    <div class="row mb-6">
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Name:</label>
            <input type="text" class="form-control filter-field" placeholder="Enter Name" id="search_name" />
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Service:</label>
            <select class="form-control filter-field select2" name="service" id="search_service">

            </select>
        </div>
        @if(\Illuminate\Support\Facades\Gate::allows("view_inactive_machine_types"))
        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Status:</label>
            <select class="form-control filter-field select2" name="status" id="search_status">

            </select>
        </div>
        @endif
        <div class="col-lg-3 mb-lg-0 mb-6 @if($errors->has('date_range')) has-error @endif">
            {!! Form::label('date_range', 'Created at:', ['class' => 'control-label']) !!}
            <div class="input-group">
                {!! Form::text('date_range', null, ['id' => 'date_range', 'class' => 'form-control', 'autocomplete' => 'off', 'placeholder' => 'Select Date Range']) !!}
            </div>
        </div>
        <div class="col-lg-3 mb-lg-0 mb-6 mt-8">
            @include('admin.partials.filter-buttons')
        </div>
    </div>
</div>


