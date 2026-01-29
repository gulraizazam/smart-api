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
        <table id="allocate_services" class="table table-bordered table-advance">

            <thead>
            <tr>
                <th>#</th>
                <th>Consultancy\Service</th>
                <th>Service Price</th>
                <th>Discount Name</th>
                <th>Discount Price</th>
                <th>Subtotal</th>
                <th>Tax %</th>
                <th>Tax</th>
                <th>Total</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>1</td>
                <td>{{$service?->name ?? 'N/A'}}</td>
                <td>{{number_format($price_tax)}}</td>
                <td>-</td>
                <td>0</td>
                <td>{{number_format($price_tax)}}</td>
                <td>{{$location_info->tax_percentage ?? 0}}</td>
                <td>{{number_format($tax ?? 0)}}</td>
                <td>{{number_format($tax_amt ?? 0)}}</td>
            </tr>
            </tbody>
        </table>
    </div>
    {{--End--}}

    <div class="form-group">

        <div class="row mt-5">

            {{--left section--}}
            <div class="col-md-10">

                <!-- <div class="col-md-12 mt-5">
                    <strong class="mt-5">Date</strong> -->
                    <!-- <span class="d-none"><i  onclick="triggerDate('custom_field');" style="color: #cc8600; font-size: large; cursor: pointer;" class="la la-pencil float-right"></i></span> -->
                    <!-- <input type="text" name="created_at" value="{{\Carbon\Carbon::now()->format('Y-m-d')}}"
                           class="form-control float-right custom_field pr-0 text-right" id="created_at" readonly> -->
                <!-- </div> -->

                @if($price_tax > 0)
                <div class="row mt-5 mb-10">
                    <div class="col-md-6">
                        <strong class="mt-5">Consultation Fee</strong>
                        <input style="width: 100%;" type="number" name="cash" id="cash" value="{{$price_tax}}" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <strong>Payment Mode <span class="text-danger">*</span></strong>
                        {!! Form::select('payment_mode_id',$paymentmodes ,old('payment_mode_id'),['class' => 'form-control','id'=>'payment_mode_id', 'style' => 'width:100%;', 'required' => 'required']) !!}
                    </div>
                </div>
                @endif

                <div class="col-md-12 mt-5 mb-10">
                    <div id="addinvoice" class="text-center">
                        <button class="btn btn-success margin-bottom-5" name="savepackageinformation" id="savepackageinformation"
                                data-print-type="invoice" style="margin-top:20px;"><i class="fa fa-print"></i> Print Invoice
                        </button>
                        <button class="btn btn-info margin-bottom-5" name="savepackageinformation_form" id="savepackageinformation_form"
                                data-print-type="form" style="margin-top:20px;" disabled><i class="fa fa-print"></i> Print Consultation Form
                        </button>
                    </div>

               {{-- <div class="md-col-10" style="max-width: 79.6666666667%">

                    <label style="margin-left: 15px;"><strong>Amount Type</strong></label>
                    <select style="margin-left: 15px;" name="amount_type" id="amount_type" class="form-control discount_id">
                        <option value="0">Default Amount</option>
                        <option value="1">Custom Amount</option>
                    </select>

                </div>--}}

                @if($discounts->count() > 0)
                <div class="col-md-10 mt-5">
                    <label><strong>Discount</strong></label>
                    <select name="discount_id" id="discount_id" class="form-control discount_id">
                        <option value="0">Select Discount</option>
                        @foreach($discounts as $discount)
                            <option value="{{$discount['id']}}">{{$discount['name']}}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="col-md-10 mt-5 discount_type_section" style="display: none;">
                    <label><strong>Discount Type</strong></label>
                    <select name="discount_type" id="discount_type" class="form-control" disabled>
                        <option value="0">Select Discount Type</option>
                        <option value="Fixed">Fixed</option>
                        <option value="Percentage">Percentage</option>
                    </select>
                </div>

                <div class="col-md-10 mt-5 discount_value_section" style="display: none;">
                    <label><strong>Discount Value</strong></label>
                    <input type="number" name="discount_value" id="discount_value" value="0" class="form-control" disabled>
                </div>

            </div>

            {{--end left section--}}

            <div class="col-md-4" style="display: none;">

                {{--<div class="col-md-10 mt-12">
                    <span class="switch switch-sm switch-icon switch_custom">
                        <div class="col-md-12" style="padding-left: 0">
                            <strong>Exclusive</strong>
                               @if($service?->tax_treatment_type_id == Config::get('constants.tax_both') || $service?->tax_treatment_type_id == Config::get('constants.tax_is_exclusive'))
                                    <input type="hidden" name="is_exclusive_consultancy" value="0"/>
                                    <label class="float-right">
                                        <input id="is_exclusive_consultancy" type="checkbox" name="is_exclusive_consultancy" value="1">
                                        <span></span>
                                    </label>
                               @else
                                <input type="hidden" name="is_exclusive_consultancy" value="0"/>

                                <label class="float-right">
                                        <input id="is_exclusive_consultancy" type="checkbox" name="is_exclusive_consultancy" value="0">
                                        <span></span>
                                    </label>
                               @endif
                            </div>
                    </span>
                </div>--}}


                <input type="hidden" class="amount" name="amount" value="{{$price}}">

                <input type="hidden" class="tax" name="tax" value="{{$tax}}">

                <div class="col-md-10 mt-5">
                    <strong>Tax Amt.</strong>
                    <strong id="tax_amt" class="float-right">{{$tax_amt}}</strong>
                    <input type="hidden" class="tax_amt" name="tax_amt" value="{{$tax_amt}}">
                </div>

                <div class="col-md-10 mt-5" style="display: none;">
                    <strong>Balance Amount</strong>
                    <strong id="balance" class="float-right">{{$balance}}</strong>
                    <input type="hidden" class="balance" name="balance" value="{{$balance}}">
                </div>

                <div class="col-md-10 mt-5">
                    <strong>Settle Amount</strong>
                    <strong id="settle" class="float-right">{{$settleamount}}</strong>
                    <input type="hidden" class="settle" name="settle" value="{{$settleamount}}">
                </div>

                <div class="col-md-10 mt-5">
                    <strong>Outstanding</strong>
                    <strong id="outstand" class="float-right">{{$outstanding}}</strong>
                    <input type="hidden" class="outstand" name="outstand" value="{{$outstanding}}">
                </div>

            </div>

            </div>

        </div>
    </div>

</div>
