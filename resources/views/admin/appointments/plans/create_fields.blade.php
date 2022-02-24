{{--Message for success and wraning--}}
<div id="duplicateErr" class="alert alert-danger display-hide">
    <button class="close" data-close="alert"></button>
    Dupliocate record found, please select another one.
</div>
<div id="successMessage" class="alert alert-success display-hide">
    <button class="close" data-close="alert"></button>
    Plan successfully created
</div>
<div id="inputfieldMessage" class="alert alert-danger display-hide">
    <button class="close" data-close="alert"></button>
    Kindly enter required fields or you enter wrong value.
</div>
<div id="wrongMessage" class="alert alert-danger display-hide">
    <button class="close" data-close="alert"></button>
    Something went wrong!
</div>
<div id="percentageMessage" class="alert alert-danger display-hide">
    <button class="close" data-close="alert"></button>
    Your discount limit exceeded.
</div>
<div id="AlreadyExitMessage" class="alert alert-danger display-hide">
    <button class="close" data-close="alert"></button>
    Unable to enter same service with different price.
</div>
<div id="datanotexist" class="alert alert-danger display-hide">
    <button class="close" data-close="alert"></button>
    That center not have any service.
</div>
<div id="DiscountRange" class="alert alert-danger display-hide">
    <button class="close" data-close="alert"></button>
    Your discount limit exceeded.
</div>
{{--End--}}

{{--Random id for package table--}}
<div class="row">
    <div class="form-group col-md-12">
        <input type="hidden" name="random_id_1" id="random_id_1" class="form-control" value="{{$random_id}}">
        <input type="hidden" name="slug_1" id="slug_1" class="form-control">
        <input type="hidden" id="client_id" class="form-control" value="{{$patients->id}}">
    </div>
</div>
{{--End--}}

{{--Get Patient id from drop down--}}
<div class="row">
    <div class="form-group col-md-3">
        {!! Form::label('patient_id', 'Patients*', ['class' => 'control-label']) !!}
        <h4><strong>{{$patients->name}}</strong></h4>
        <input type="hidden" name="patient_id_1" id="parent_id_1" value="{{$patients->id}}" class="form-control">
    </div>
    <div class="form-group col-md-4">
        {!! Form::label('location_id', 'Centers*', ['class' => 'control-label']) !!}
        {!! Form::select('location_id_1', $locations,$appointmentinformation->location_id, ['class' => 'form-control select2','id' => 'location_id_1']) !!}
    </div>
    <div class="form-group col-md-3">
        {!! Form::label('appointment_id', 'Appointment', ['class' => 'control-label']) !!}
        <select name="appointment_id_1" id="appointment_id_1" class="form-control select2 appointment_id_1"></select>
    </div>
    <div class="form-group col-md-2">
        <br><br>
        {!! Form::label('is_exclusive', 'Is Exclusive', ['class' => 'control-label']) !!}
        <label class="mt-checkbox is_exclusive">
            <input type="hidden" name="is_exclusive" value="0"/>
            <input id="is_exclusive" type="checkbox" name="is_exclusive" value="1" checked/>
            <span></span>
        </label>
    </div>
</div>
{{--End--}}

{{--Getting information about package services--}}
<div class="row">
    <div class="form-group col-md-2">
        {!! Form::label('service_id', 'Services*', ['class' => 'control-label']) !!}
        <select name="service_id_1" id="service_id_1" class="form-control select2 service_id_1"
                data-placeholder="Select Service"></select>
    </div>
    <div class="form-group col-md-2">
        {!! Form::label('discount_id', 'Discounts', ['class' => 'control-label']) !!}
        <select name="discount_id_1" id="discount_id_1" class="form-control select2 discount_id_1">
            <option value="0">Select Discount</option>
        </select>
    </div>
    <div class="form-group col-md-2">
        {!! Form::label('discount_type', 'Discount Type', ['class' => 'control-label']) !!}
        <select name="discount_type_1" id="discount_type_1" class="form-control select2">
            <option value="">Select Discount Type</option>
            <option value="Fixed">Fixed</option>
            <option value="Percentage">Percentage</option>
        </select>
    </div>
    <div class="form-group col-md-2">
        {!! Form::label('discount_value', 'Discount Value', ['class' => 'control-label']) !!}
        <input type="number" name="discount_value_1" id="discount_value_1" class="form-control">
    </div>
    <div class="form-group col-md-2">
        {!! Form::label('net_amount', 'Price', ['class' => 'control-label']) !!}
        <input type="text" name="net_amount_1" id="net_amount_1" class="form-control" readonly='true'>
    </div>
    <div class="col-md-2">
        <button class="btn btn-success" name="AddPackage_1" id="AddPackage_1" style="margin-top: 26px;width:100%;float: right;">
            Add
        </button>
    </div>
</div>
{{--End--}}

{{--Table for display information of services with discount package--}}
<div class="table-responsive">
    <table id="table_1" class="table table-striped table-bordered table-advance table-hover" style="margin-top: 25px">
        {{ csrf_field() }}
        <thead>
        <tr>
            <th>Service Name</th>
            <th>Service Price</th>
            <th>Discount Name</th>
            <th>Discount Type</th>
            <th>Discount Price</th>
            <th>Amount</th>
            <th>Tax %</th>
            <th>Tax Amt.</th>
            <th>Action</th>
        </tr>
        </thead>
        <tr class="HR_{{$random_id}}">
        </tr>
    </table>
</div>
{{--End--}}

{{--Maintain the total and payment mode and calculate grand total--}}
<div class="row bottom-data">
    <div class="form-group col-md-3">
        {!! Form::label('total', 'Total', ['class' => 'control-label']) !!}
        <input type="text" name="package_total_1" id="package_total_1" value="0" class="form-control" readonly="true">
    </div>
    <div class="form-group col-md-3">
        {!! Form::label('paymentmode', 'Payment Mode*', ['class' => 'control-label']) !!}
        {!! Form::select('payment_mode_id',$paymentmodes ,old('payment_mode_id'),['class' => 'form-control select2','id'=>'payment_mode_id_1']) !!}
    </div>
    <div class="form-group col-md-3">
        {!! Form::label('cash_amount', 'Amount', ['class' => 'control-label']) !!}
        {!! Form::number('cash_amount', old('cash_amount') ? old('cash_amount') : 0, ['class' => 'form-control inpt-focus','id'=>'cash_amount_1','placeholder' => 'Enter Amount','min'=>0]) !!}
    </div>
    <div class="form-group col-md-3">
        {!! Form::label('grand_total', 'Cash Received Remain', ['class' => 'control-label']) !!}
        {!! Form::text('total_price', old('total_price'), ['class' => 'form-control inpt-focus','id'=>'grand_total_1','readonly'=>'true']) !!}
    </div>
</div>
{{--End--}}

{{--Save package information--}}
<div class="row">
    <div class="form-group col-md-12">
        <button class="btn btn-success" name="AddPackageFinal_1" id="AddPackageFinal_1"
                style="float: right">
            Save
        </button>
    </div>
</div>
{{--End--}}
