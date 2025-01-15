<form action="" id="search_inventory_report_form">
    <div class="mt-2 mb-7">
        <input type="hidden" class="form-control filter-field" id="search_location_type" name="location_type">

        <div class="row mb-6">
            <div class="form-group col-md-3 " id="report_type_div">
                {!! Form::label('report_type', 'Report Type:', ['class' => 'control-label']) !!}
                <select class="form-control" id="report_types" name="report_type">
                    <option value="">Select Report</option>
                    <option value="stock_report">Stock Report</option>
                    <option value="sales_report">Sales Report</option>
                    <option value="doctor_sales_report">Doctor Wise Sales Report</option>
                </select>
                @error('report_type')
                <div class="alert alert-danger">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-lg-3 mb-lg-0 mb-6">
                <label>Product Name:</label>
                <input type="text" class="form-control" name="name" id="search_product_name" placeholder="Product Name">
            </div>
            <div class="col-lg-3 mb-lg-0 mb-6">
                <label>Location:</label>
                <select class="form-control filter-field select2" name="location" id="search_location">
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
</form>
