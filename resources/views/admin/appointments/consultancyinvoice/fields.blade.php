<div id="consultancy-invoice-create">
    {{--Message for success and wraning--}}
    <div id="successMessage" class="alert alert-success display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
        Invoice successfully created
    </div>
    <div id="wrongMessage" class="alert alert-warning display-hide"  style="display: none;">
        <button class="close" data-close="alert"></button>
        Something Went Wrong!
    </div>
    <div id="definefield" class="alert alert-warning display-hide"  style="display: none;">
        <button class="close" data-close="alert"></button>
        Kindly define payment mode
    </div>
    <div id="percentageMessage" class="alert alert-danger display-hide"  style="display: none;">
        <button class="close" data-close="alert"></button>
        Your discount limit exceeded.
    </div>
    <div id="customfield" class="alert alert-warning display-hide"  style="display: none;">
        <button class="close" data-close="alert"></button>
        Cash must be greater than zero
    </div>
    {{--End--}}

    {{--Some hidden Fields that helps us for saving invoice--}}

    <input type="hidden" value="{{$id}}" id="invoice_appointment_id">
    <input type="hidden" value="{{$location_info->id}}" id="id_location">
    <input type="hidden" value="{{$price_tax}}" id="price_for_calculation">
    <input type="hidden" value="{{$service?->tax_treatment_type_id ?? 0}}" id="tax_treatment_type_id">


    <input type="hidden" value="" id="settleamount_cash">
    <input type="hidden" value="" id="outstanding_cash">

    {{--End--}}

    {{--That if condition show for consultancey--}}
    <div class="table-responsive">
        <table class="table table-striped table-bordered table-advance table-hover">
            {{ csrf_field() }}
            <thead>
            <tr>
                <th> Name</th>
                <th> Price</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>{{$service?->name ?? 'N/A'}}</td>
                <td>{{number_format($price_tax)}}</td>
            </tr>
            </tbody>
        </table>
    </div>
    {{--End--}}
    <br>
    <div class="invice-holder">
        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Exclusive</strong></label>
            </div>
            <div class="col-md-4">
                <label class="mt-checkbox">
                    @if($service?->tax_treatment_type_id == Config::get('constants.tax_both') || $service?->tax_treatment_type_id == Config::get('constants.tax_is_exclusive'))
                        <input type="hidden" name="is_exclusive_consultancy" value="0"/>
                        <label class="custom_checkbox checkbox-all">
                            <input class="select-all-checkboxes" id="is_exclusive_consultancy" type="checkbox" name="is_exclusive_consultancy" value="1" checked>
                            <strong></strong>
                        </label>
                    @else
                        <input type="hidden" name="is_exclusive_consultancy" value="0"/>
                        <label class="custom_checkbox checkbox-all">
                            <input class="select-all-checkboxes"  id="is_exclusive_consultancy" type="checkbox" name="is_exclusive_consultancy" value="0">
                            <strong></strong>
                        </label>

                    @endif
                </label>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Amount Type</strong></label>
            </div>
            <div class="col-md-4">
                <select name="amount_type" id="amount_type" class="form-control discount_id">
                    <option value="0">Default Amount</option>
                    <option value="1">Custom Amount</option>
                </select>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Discount</strong></label>
            </div>
            <div class="col-md-4">
                <select name="discount_id" id="discount_id" class="form-control select2 discount_id">
                    <option value="0">Select Discount</option>
                    @foreach($discounts as $discount)
                        <option value="{{$discount['id']}}">{{$discount['name']}}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Discount Type</strong></label>
            </div>
            <div class="col-md-4">
                <select name="discount_type" id="discount_type" class="form-control select2" disabled>
                    <option value="0">Select Discount Type</option>
                    <option value="Fixed">Fixed</option>
                    <option value="Percentage">Percentage</option>
                </select>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Discount Value</strong></label>
            </div>
            <div class="col-md-4">
                <input type="number" name="discount_value" id="discount_value" value="0" class="form-control disabled-field" disabled>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Amount</strong></label>
            </div>
            <div class="col-md-4">
                <input type="text" name="amount" id="amount" class="form-control disabled-field" value="{{$price}}" readonly>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Tax Price</strong></label>
            </div>
            <div class="col-md-4">
                <input type="text" name="tax" id="tax" class="form-control disabled-field" value="{{$tax}}" readonly>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Tax Amt.</strong></label>
            </div>
            <div class="col-md-4">
                <input type="text" name="tax_amt" id="tax_amt" class="form-control disabled-field" value="{{$tax_amt}}" readonly>
            </div>
        </div>
        {{--<br>--}}
        <div class="row" style="display: none;">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Balance Amount</strong></label>
            </div>
            <div class="col-md-4">
                <input type="text" name="balance" id="balance" class="form-control disabled-field" value="{{$balance}}" readonly>
            </div>
        </div>
        <br>

        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Amount Received</strong></label>
            </div>
            <div class="col-md-4">
                <input type="number" name="cash" id="cash" value="{{$cash}}" class="form-control">
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Settle Amount</strong></label>
            </div>
            <div class="col-md-4">
                <input type="text" name="settle" id="settle" class="form-control disabled-field" value="{{$settleamount}}" readonly>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Outstanding</strong></label>
            </div>
            <div class="col-md-4">
                <input type="text" name="outstand" id="outstand" class="form-control disabled-field" value="{{$outstanding}}"
                       onchange='outstandingAmount()' readonly>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Date</strong></label>
            </div>
            <div class="col-md-4">
                <input type="text" name="created_at" value="{{\Carbon\Carbon::now()->format('Y-m-d')}}"
                       class="form-control disabled-field custom-datepicker" id="created_at" required readonly>
            </div>
        </div>
        <br>
        <div class="row" id="paymentmode" style="display: none">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Payment Mode</strong></label>
            </div>
            <div class="col-md-4">
                {!! Form::select('payment_mode_id',$paymentmodes ,old('payment_mode_id'),['class' => 'form-control select2','id'=>'payment_mode_id']) !!}
            </div>
        </div>
    </div>

    <div class="row">
        <hr>
        <div class="col-md-12" id="addinvoice">
            <button class="btn btn-success spinner-button" name="savepackageinformation" id="savepackageinformation"
                style="float: right;margin-top:20px;">Save
            </button>
        </div>
    </div>

</div>
