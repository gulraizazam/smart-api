{{--Some Hidden fields that helps for refunds save--}}
<input type="hidden" name="package_id" id="package_id" value="{{$id}}" class="form-control">
<input type="hidden" name="is_adjustment_amount" value="{{$is_adjustment_amount}}" class="form-control">
<input type="hidden" name="return_tax_amount" value="{{$return_tax_amount}}" class="form-control">
<input type="hidden" name="date_backend" id="date_backend" value="{{$date_backend}}" class="form-control">
{{--End--}}
<div class="row">
    <div class="form-group col-md-6">
        {!! Form::label('refund_note', 'Refund Note', ['class' => 'control-label']) !!}
        <textarea name="refund_note" class="form-control" style="width: 650px; height: 129px;"
                  placeholder="Enter Reason Here" required></textarea>
        @if($errors->has('refund_note'))
            <p class="help-block">
                {{ $errors->first('refund_note') }}
            </p>
        @endif
    </div>
</div>
<div class="row">
    @if($document == 'true')
        <div class="form-group col-md-12">
            {!! Form::label('Documentation Charges', 'Documentation Charges', ['class' => 'control-label']) !!}
            {!! Form::text('documentationcharges',$documentationcharges->data, ['class' => 'form-control inpt-focus', 'placeholder' => '', 'readonly' =>'true']) !!}
        </div>
    @else
        <div class="form-group col-md-12">
            {!! Form::label('Message', 'Documentation Charges Already Taken', ['class' => 'control-label']) !!}
        </div>
    @endif
</div>
<div class="row">
    <div class="form-group col-md-6">
        {!! Form::label('balance', 'Balance', ['class' => 'control-label']) !!}
        {!! Form::text('balance',$refundable_amount, ['class' => 'form-control inpt-focus', 'placeholder' => '', 'readonly' =>'true']) !!}
        @if($errors->has('balance'))
            <p class="help-block">
                {{ $errors->first('balance') }}
            </p>
        @endif
    </div>
    <div class="form-group col-md-6">
        {!! Form::label('refund_amount', 'Refund Amount', ['class' => 'control-label']) !!}
        <input type="number" name="refund_amount" id="refund_amount" class="form-control"
               max="<?php echo filter_var($refundable_amount, FILTER_SANITIZE_NUMBER_INT); ?>" required>
        @if($errors->has('refund_amount'))
            <p class="help-block">
                {{ $errors->first('refund_amount') }}
            </p>
        @endif
    </div>
</div>
<div class="row">
    <div class="form-group col-md-6">
        {!! Form::label('created_at', 'Date*', ['class' => 'control-label']) !!}
        <input type="text" name="created_at" value="" class="form-control date_to_rota" id="created_at" required>
        @if($errors->has('created_at'))
            <p class="help-block">
                {{ $errors->first('created_at') }}
            </p>
        @endif
    </div>
</div>
<div class="clearfix"></div>