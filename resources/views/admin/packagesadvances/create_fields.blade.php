{{--Message for success and wraning--}}
<div id="duplicateErr" class="alert alert-danger display-hide">
    <button class="close" data-close="alert"></button>
    Dupliocate record found, please select another one.
</div>
<div id="successMessage" class="alert alert-success display-hide">
    <button class="close" data-close="alert"></button>
    Advance Add successfully.
</div>
<div id="inputfieldMessage" class="alert alert-danger display-hide">
    <button class="close" data-close="alert"></button>
    Kindly enter required fields
</div>
<div id="wrongMessage" class="alert alert-danger display-hide">
    <button class="close" data-close="alert"></button>
    Something went wrong!
</div>
<div id="exceedMessage" class="alert alert-danger display-hide">
    <button class="close" data-close="alert"></button>
    Your value increase from total price. kindly enter correct value
</div>
{{--End--}}

{{--Form that gather information from packages and package advances--}}
<div class="row">
    <div class="form-group col-md-4">
        {!! Form::label('patient_id', 'Patient*', ['class' => 'control-label']) !!}
        <select name="patient_id" id="patient_id_1" class="form-control patient_id"></select>
    </div>
    <div class="form-group col-md-4">
        {!! Form::label('package_id', 'Package', ['class' => 'control-label']) !!}
        <select name="package_id_1" class="form-control select2" id="package_id_1">
            <option value="" selected>Select Package</option>
        </select>
    </div>
    <div class="form-group col-md-4">
        {!! Form::label('totol_price', 'Total Price', ['class' => 'control-label']) !!}
        <input type="text" id="total_price_1" name="total_price_1" readonly="true" class="form-control">
    </div>
</div>
<div class="row">
    <div class="form-group col-md-4">
        {!! Form::label('cash_total_amount', 'Cash Receive', ['class' => 'control-label']) !!}
        <input type="text" id="cash_total_amount_1" name="cash_total_amount_1" readonly="true" class="form-control">
    </div>

{{--End--}}

{{--Form that get information from user at run time--}}

    <div class="col-md-4">
        {!! Form::label('paymentmode', 'Payment Mode*', ['class' => 'control-label']) !!}
        {!! Form::select('payment_mode_id',$paymentmodes ,old('payment_mode_id'),['class' => 'form-control select2','id'=>'payment_mode_id_1']) !!}
    </div>
    <div class="col-md-4">
        {!! Form::label('cash_amount', 'Cash Amount', ['class' => 'control-label']) !!}
        <input type="number" id="cash_amount_1" name="cash_amount_1" value="0" class="form-control">
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <button class="btn btn-success" name="AddAmount_1" id="AddAmount_1" style="margin-top: 10px;float: right;">Add
        </button>
    </div>
</div>
{{--End--}}
