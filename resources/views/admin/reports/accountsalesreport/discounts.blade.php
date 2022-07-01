{!! Form::label('discount_id', 'Discounts', ['class' => 'control-label']) !!}
<select name="discount_id" id="discount_id" style="width: 100%" class="form-control select2">
    <option value="">Select Discount</option>
    <option value="0">All Discounts</option>
    @foreach($discounts as $discount)
        <option value="{{$discount->id}}">{{$discount->name}}</option>
    @endforeach
</select>
<span id="discounts_handler"></span>