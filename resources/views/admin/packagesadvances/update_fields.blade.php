{{--Message for success and wraning--}}
<div id="duplicateErr" class="alert alert-danger display-hide">
    <button class="close" data-close="alert"></button>
    Dupliocate record found, please select another one.
</div>
<div id="successMessage" class="alert alert-success display-hide">
    <button class="close" data-close="alert"></button>
    Package successfully created
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
        <select name="patient_id" id="patient_id" class="form-control select2" disabled="disabled">
            <option value="">Select Patient</option>
            @foreach($leads as $patient)
                <option @if($packageadvances->patient_id == $patient->id) selected="selected" @endif value={{$patient->id}}>{{$patient->name}}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-4">
        {!! Form::label('package_id', 'Package', ['class' => 'control-label']) !!}
        <select name="package_id" class="form-control select2" id="package_id" disabled="disabled">
            <option value="">Select Package</option>
            @foreach($package_info as $package)
                <option  @if($packageadvances->package_id == $package->id) selected="selected" @endif value={{$package->id}}>{{$package->name}}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-4">
        {!! Form::label('totol_price', 'Total Price', ['class' => 'control-label']) !!}
        <input type="text" id="total_price" name="total_price" readonly="true" value = {{$total_price}} class="form-control">

    </div>
</div>
<div class="row">
    <div class="form-group col-md-4">
        {!! Form::label('cash_total_amount', 'Cash Receive', ['class' => 'control-label']) !!}
        <input type="text" id="cash_total_amount" name="cash_total_amount" value="{{ $total_amount }}" readonly="true" class="form-control">
    </div>
{{--End--}}

{{--Cash Amount previouse and id use for only update for key up function and update amount amount--}}
<input type="hidden" id="cash_amount_update" value="{{$packageadvances->cash_amount}}">
<input type="hidden" id="package_advance_id" value="{{$packageadvances->id}}">
{{--End--}}

{{--Form that get information from user at run time--}}
    <div class="form-group col-md-4">
        {!! Form::label('payment_mode_id', 'Payment Mode', ['class' => 'control-label']) !!}
        <select name="payment_mode_id" class="form-control select2" id="payment_mode_id">
            <option value="">Select Payment Mode</option>
            @foreach($paymentmodes as $paymentmodes)
                <option  @if($packageadvances->payment_mode_id == $paymentmodes->id) selected="selected" @endif value={{$paymentmodes->id}}>{{$paymentmodes->name}}</option>
            @endforeach
        </select>
    </div>

    <div class="form-group col-md-4">
        {!! Form::label('cash_amount', 'Cash Amount', ['class' => 'control-label']) !!}
        <input type="number" id="cash_amount" name="cash_amount" value="{{ $packageadvances->cash_amount }}" class="form-control">
    </div>
</div>
<div class="row">
    <div class="form-group col-md-12">
        <button class="btn btn-success" type="submit" name="AddAmount" id="AddAmount" style="margin-top: 10px;float: right;">Add
        </button>
    </div>
</div>
{{--End--}}
