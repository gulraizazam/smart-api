{{--Message for success and wraning--}}
<div id="duplicateErr" class="alert alert-danger display-hide">
    <button class="close" data-close="alert"></button>
    Dupliocate record found, please select another one.
</div>
<div id="successMessage" class="alert alert-success display-hide">
    <button class="close" data-close="alert"></button>
    Plans successfully created
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
<div id="consumeservice" class="alert alert-danger display-hide">
    <button class="close" data-close="alert"></button>
    Unable to delete consume service.
</div>
<div id="consumeprice" class="alert alert-danger display-hide">
    <button class="close" data-close="alert"></button>
    Unable to delete consume amount.
</div>
{{--End--}}

{{--Random id for package table--}}
<div class="row">
    <div class="form-group col-md-12 remvwidth">
        <input type="hidden" name="random_id" id="random_id" class="form-control" value="{{$package->random_id}}">
        <input type="hidden" name="slug" id="slug" class="form-control">
    </div>
</div>
{{--End--}}

{{--Get Patient id from drop down--}}
<div class="row">
    <div class="form-group col-md-3">
        {!! Form::label('parent_id', 'Patient Name', ['class' => 'control-label']) !!}
        <span style="font-size:18px;display: block;"><strong>{{$package->user->name}}</strong></span>
        <input type="hidden" id="parent_id" name="parent_id" value="{{$package->patient_id}}">
    </div>
    <div class="form-group col-md-4">
        {!! Form::label('location_id', 'Location', ['class' => 'control-label']) !!}
        <span style="font-size:18px;display: block;"><strong>{{$package->location->name}}</strong></span>
        <input type="hidden" id="location_id" name="location_id" value="{{$package->location->id}}">
    </div>

    <div class="form-group col-md-3">
        {!! Form::label('appointment_id', 'Appointment*', ['class' => 'control-label']) !!}
        <select name="appointment_id" id="appointment_id" class="form-control select2_custom appointment_id">
            <option value="">Select Appointment</option>
            @foreach($appointmentArray as $appointment)
                <option @if($appointment['id'] == $package->appointment_id) selected="selected" @endif value="{{$appointment['id']}}">{{$appointment['name']}}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-2">
        <br><br>
        {!! Form::label('is_exclusive', 'Is Exclusive', ['class' => 'control-label']) !!}
        <label class="mt-checkbox is_exclusive">
            <input id="is_exclusive" type="checkbox" name="is_exclusive" value="1" checked>
            <span></span>
        </label>
    </div>
</div>
{{--End--}}

{{--Getting information about package services--}}
<div class="row">
    <div class="form-group col-md-2">
        {!! Form::label('service_id', 'Services*', ['class' => 'control-label']) !!}
        <select name="service_id" id="service_id" class="form-control select2_custom service_id">
            <option value="">Select Service</option>
            @foreach($locationhasservice as $locationhasservice)
                <option value="{{$locationhasservice->id}}">{{$locationhasservice->name}}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-md-2">
        {!! Form::label('discount_id', 'Discounts', ['class' => 'control-label']) !!}
        <select name="discount_id" id="discount_id" class="form-control select2_custom discount_id">
            <option value="0">Select Discount</option>
        </select>
    </div>
    <div class="form-group col-md-2">
        {!! Form::label('discount_type', 'Discount Type', ['class' => 'control-label']) !!}
        <select name="discount_type" id="discount_type" class="form-control select2_custom">
            <option value="">Select Discount Type</option>
            <option value="Fixed">Fixed</option>
            <option value="Percentage">Percentage</option>
        </select>
    </div>
    <div class="form-group col-md-2">
        {!! Form::label('discount_value', 'Discount Value', ['class' => 'control-label']) !!}
        <input type="number" name="discount_value" id="discount_value" class="form-control">
    </div>
    <div class="form-group col-md-2">
        {!! Form::label('net_amount', 'Price', ['class' => 'control-label']) !!}
        <input type="text" name="net_amount" id="net_amount" class="form-control" readonly='true'>
    </div>
    <div class="col-md-2">
        <button class="btn btn-success" type="submit" name="AddPackage" id="AddPackage"
                style="margin-top: 26px;width:100%;float: right;">Add
        </button>
    </div>
</div>
{{--End--}}

{{--Table for display information of services with discount package--}}
<div class="table-responsive">
    <table id="table" class="table table-striped table-bordered table-advance table-hover" style="margin-top: 25px">
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
        @if($packagebundles)
            @foreach($packagebundles as $packagebundles)
                <tr class="HR_{{$packagebundles->id}}">
                    <td><a href="javascript:void(0);"
                           onclick="toggle({{$packagebundles->id}})"><?php echo $packagebundles->bundle->name; ?></a>
                    </td>
                    <td>{{number_format($packagebundles->service_price)}}</td>
                    <td>
                        @if($packagebundles->discount_id == null)
                            {{'-'}}
                        @elseif($packagebundles->discount_name)
                            {{$packagebundles->discount_name}}
                        @else
                            {{$packagebundles->discount->name}}
                        @endif
                    </td>
                    <td><?php if ($packagebundles->discount_type == null) {
                            echo '-';
                        } else {
                            echo $packagebundles->discount_type;
                        } ?>
                    </td>
                    <td><?php if ($packagebundles->discount_price == null) {
                            echo '0.00';
                        } else {
                            echo $packagebundles->discount_price;
                        } ?>
                    </td>
                    <td>{{$packagebundles->tax_exclusive_net_amount}}</td>
                    <td>{{$packagebundles->tax_percenatage}}</td>
                    <td>{{$packagebundles->tax_including_price}}</td>
                    <td>
                        <input type='hidden' class='package_bundles' name='package_bundles[]'
                               value='{{$packagebundles->id}}'/>
                        @if(Gate::allows('plans_service_delete'))
                            <button class="btn btn-xs btn-danger" onClick=deleteModel('{{$packagebundles->id}}')>Delete
                            </button>
                        @endif
                    </td>
                </tr>
                @foreach ($packageservices as $packageservice)
                    @if($packageservice->package_bundle_id == $packagebundles->id )
                        <?php if ($packageservice->is_consumed == '0') {
                            $consume = 'NO';
                        } else {
                            $consume = 'YES';
                        }?>
                        <tr class="HR_{{$packagebundles->id}} {{$packagebundles->id}}" style="display:none">
                            <td></td>
                            <td><?php echo $packageservice->service->name; ?></td>
                            <td>Amount : {{$packageservice->tax_exclusive_price}}</td>
                            <td>Tax % : {{$packageservice->tax_percenatage}}</td>
                            <td>Tax Amt. : {{$packageservice->tax_including_price}}</td>
                            <td colspan="4">Is Consumed : {{$consume}}</td>
                        </tr>
                    @endif
                @endforeach
            @endforeach
        @endif
    </table>
</div>
{{--End--}}

{{--Maintain the total and payment mode and calculate grand total--}}
<div class="row bottom-data">
    <div class="form-group col-md-3">
        {!! Form::label('total', 'Total', ['class' => 'control-label']) !!}
        <input type="text" name="package_total" id="package_total" value="{{number_format($total_price)}}"
               class="form-control" readonly="true">
    </div>
    <div class="form-group col-md-3">
        {!! Form::label('paymentmode', 'Payment Mode*', ['class' => 'control-label']) !!}
        {!! Form::select('payment_mode_id',$paymentmodes ,old('payment_mode_id'),['class' => 'form-control select2_custom','id'=>'payment_mode_id']) !!}
    </div>
    <div class="form-group col-md-3">
        {!! Form::label('cash_amount', 'Amount', ['class' => 'control-label']) !!}
        {!! Form::number('cash_amount', old('cash_amount') ? old('cash_amount') : 0, ['class' => 'form-control inpt-focus','id'=>'cash_amount','placeholder' => 'Enter Amount','min'=>0]) !!}
    </div>
    <div class="form-group col-md-3">
        {!! Form::label('grand_total', 'Cash Received Remain', ['class' => 'control-label']) !!}
        {!! Form::text('total_price', old('total_price') ? old('total_price') : $grand_total , ['class' => 'form-control inpt-focus','id'=>'grand_total','readonly'=>'true']) !!}
    </div>
</div>
{{--End--}}

{{--Save package information--}}
<div class="row">
    <div class="form-group col-md-12">
        <button class="btn btn-success" type="submit" name="AddPackageFinal" id="AddPackageFinal"
                style="float: right;margin-top: 15px;">
            Save
        </button>
    </div>
</div>
{{--End--}}
{{--History of patient package advances service--}}
<h3 style="margin-top: 0;">History</h3>
<div class="table-responsive">
    <table id="table" class="table table-striped table-bordered table-advance table-hover">
        {{ csrf_field() }}
        <thead>
        <tr>
            <th>Payment Mode</th>
            <th>Cash Flow</th>
            <th>Cash Amount</th>
            <th>Created At</th>
            <th>Action</th>
        </tr>
        </thead>
        @if($packageadvances)
            @foreach($packageadvances as $packageadvances)
                @if($packageadvances->cash_amount != '0')
                    <tr class="fianance_edit_{{$packageadvances->id}}">
                        @if($packageadvances->is_tax == 1 && $packageadvances->cash_flow == 'out')
                        <td>Tax</td>
                        @else
                        <td><?php echo $packageadvances->paymentmode->name; ?></td>
                        @endif
                        
                        <td><?php echo $packageadvances->cash_flow; ?></td>
                        <td><?php echo number_format($packageadvances->cash_amount) ?></td>
                        <td><?php echo \Carbon\Carbon::parse($packageadvances->created_at)->format('F j,Y h:i A'); ?></td>
                        <td>
                            @if($end_previous_date<=$packageadvances->created_at)
                                @if(Gate::allows('plans_cash_edit') && $packageadvances->cash_flow == 'in')
                                    <a class="btn btn-xs btn-info"
                                       href="{{ route('admin.packages.edit_cash',[$packageadvances->id,$package->id]) }}"
                                       data-target="#plan_edit_cash" data-toggle="modal">@lang('global.app_edit')</a>
                                @endif
                                {{--$packageadvances->id,$package->id--}}
                                @if(Gate::allows('plans_cash_delete') && $packageadvances->cash_flow == 'in')
                                    <button class="btn btn-xs btn-danger"
                                            onClick=deleteCashModel({{$packageadvances->id}})>Delete
                                    </button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endif
            @endforeach
        @endif
    </table>
</div>
{{--End--}}
<script>
    function toggle(id) {
        $("." + id).toggle();
    }
</script>