<input type="hidden" value="{{$pack_adv_info->id}}" name="package_advances_id">
<input type="hidden" value="{{$package_id}}" name="package_id">

<div class="row">
    @if(Gate::allows('plans_cash_edit_payment_mode'))
        <div class="form-group col-md-6">
            {!! Form::label('payment_mode_id', 'Payment Mode*', ['class' => 'control-label']) !!}
            <select name="payment_mode_id" id="payment_mode_id" class="form-control select2_finance_edit" required>
                <option value="">Select Payment Mode</option>
                @foreach($paymentmodes as $payment)
                    <option @if($payment->id == $pack_adv_info->payment_mode_id) selected="selected"
                            @endif value="{{$payment->id}}">{{$payment->name}}</option>
                @endforeach
            </select>
            @if($errors->has('payment_mode_id'))
                <p class="help-block">
                    {{ $errors->first('payment_mode_id') }}
                </p>
            @endif
        </div>
    @else
        <input type="hidden" id="payment_mode_id" name="payment_mode_id" value="{{$pack_adv_info->payment_mode_id}}">
    @endif

    @if(Gate::allows('plans_cash_edit_amount'))
        <div class="form-group col-md-6">
            {!! Form::label('cash_amount', 'Amount*', ['class' => 'control-label']) !!}
            <input type="number" name="cash_amount" id="cash_amount" value="{{$pack_adv_info->cash_amount}}"
                   class="form-control inpt-focus" ,
                   onkeypress="return ((event.charCode > 47 && event.charCode < 58) || (event.charCode < 96 && event.charCode > 123))"
                   required>
            @if($errors->has('cash_amount'))
                <p class="help-block">
                    {{ $errors->first('cash_amount') }}
                </p>
            @endif
        </div>
    @else
        <input type="hidden" name="cash_amount" id="cash_amount" value="{{$pack_adv_info->cash_amount}}"
               class="form-control inpt-focus" ,
               onkeypress="return ((event.charCode > 47 && event.charCode < 58) || (event.charCode < 96 && event.charCode > 123))"
               required>
    @endif
</div>
<div class="row">
    @if(Gate::allows('plans_cash_edit_date'))
        <div class="form-group col-md-6">
            {!! Form::label('created_at', 'Date*', ['class' => 'control-label']) !!}
            <input type="text" name="created_at"
                   value="{{\Carbon\Carbon::parse($pack_adv_info->created_at)->format('Y-m-d')}}"
                   class="form-control date_to_rota" id="created_at" required>
            @if($errors->has('created_at'))
                <p class="help-block">
                    {{ $errors->first('created_at') }}
                </p>
            @endif
        </div>
    @else
        <input type="hidden" name="created_at"
               value="{{\Carbon\Carbon::parse($pack_adv_info->created_at)->format('Y-m-d')}}"
               class="form-control date_to_rota" id="created_at" required>
    @endif
</div>
<div class="clearfix"></div>