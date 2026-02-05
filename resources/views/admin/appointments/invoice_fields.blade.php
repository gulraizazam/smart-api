<div id="treatment-invoice-create">
    {{--Message for success and wraning--}}
    <div id="successMessage" class="alert alert-success display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
        Invoice successfully created
    </div>
    <div id="wrongMessage" class="alert alert-warning display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
        Something Went Wrong!
    </div>
    <div id="setteledMessage" class="alert alert-warning display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
        This plan is settled out and cannot consume any further treatments.
    </div>
    <div id="noconsultancy" class="alert alert-danger display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
        Please select consultancy from appointment dropdown (Only arrived consultancies will be displayed). 
    </div>
    <div id="outstandingbalance" class="alert alert-danger display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
        Please pay outstanding amount first. 
    </div>
    <div id="definefield" class="alert alert-warning display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
        Kindly define payment mode
    </div>
    <div id="definetreatment" class="alert alert-warning display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
        Kindly select the treatment
    </div>
    <div id="outstandingMessage" class="alert alert-danger display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
        Add this service to the plan to consume it.
    </div>
    <div id="outstandingMessagePayment" class="alert alert-danger display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
       Add the related payment to the plan to proceed.
    </div>
    {{--End--}}

    {{--Some hidden Fields that helps us for saving invoice--}}
    <input type="hidden" value="{{$id}}" id="appointment_id_create">
    <input type="hidden" value="{{$settleamount}}" id="settleamount_for_zero" name="settleamount_for_zero">
    <input type="hidden" value="{{$outstanding}}" id="outstanding_for_zero" name="outstanding_for_zero">
    <input type="hidden" id="package_service_id" name="package_service_id">
    <input type="hidden" value="{{$checked_treatment}}" id="checked_treatment" name="checked_treatment">
    <input type="hidden" value="0" id="checked_bundle_id" name="checked_bundle_id">
    <input type="hidden" value="{{$service_in_plan ?? false}}" id="service_in_plan" name="service_in_plan">

    {{--End--}}

    {{--That if condition show for Service with and without package--}}
    @if($appointment_type->name == Config::get('constants.Service'))
        @if($status == 'false')
            <div class="row">
                <div class="col-md-6">
                    <select class="form-control select2 disabled-field" disabled>
                        <option value="">Select Plan</option>
                    </select>
                </div>
            </div>
            <br>
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-advance table-hover">
                    {{ csrf_field() }}
                    <thead>
                    <tr>
                        <th> Name</th>
                        <th> Price</th>
                        <th> Discount Name</th>
                        <th> Discount Price</th>
                        <th> Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>{{$service->name}}</td>
                        <td><?php echo number_format($amount_create_is_inclusive);?></td>
                        <td>-</td>
                        <td>0.00</td>
                        <td><?php echo number_format($amount_create_is_inclusive);?></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        @endif
        @if($status == 'true')
            <div class="row">
                <div class="col-md-6">
                    <select name="package_id_create" id="package_id_create" class="form-control select2">
                        <option value="">Select Plan</option>
                        @foreach($packages as $key => $package)
                            <option @if($key == '0') selected="selected"
                                    @endif value="{{$package->id}}">{{$package->name}}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <br>
            <div class="table-responsive">
                <table id="table_1" class="table table-striped table-bordered table-advance table-hover">
                    <thead>
                    <?php $constant = 555;?>
                    <tr>
                        <th> Name</th>
                        <th> Service Price</th>
                        <th> Sub Total</th>
                        <th> Tax</th>
                        <th> Total</th>
                    </tr>
                    </thead>
                    
                    <tr class="HR_{{$constant}}">
                    </tr>
                </table>
            </div>
        @endif
    @endif
    {{--End--}}
    <br>


    <div class="form-group">

        <div class="row mt-5">

            {{--left section--}}
            <div class="col-md-8">

                {{--In case if treatment not belong to treatment plan--}}
                <!-- @if($status == 'false')
                    <div class="col-md-10">
                        <label><strong>Appointment</strong></label>
                        <select name="appointment_link_cons" id="appointment_link_cons" class="form-control">
                            <option value="">Select Appointment</option>
                            @foreach($appointmentArray as $appointment)
                                <option value="{{$appointment['id']}}"
                                        @if ($loop->first) selected @endif>{{$appointment['name']}}</option>
                            @endforeach
                        </select>
                    </div>
                @endif -->
                {{--End--}}

            </div>

            <div class="col-md-4">

                {{--In case of services not belong to treatment plans--}}
                @if($appointment_type->name == Config::get('constants.Service'))
                    @if($status == 'false')

                        {{--Hidden Input for service that not belongs to treatment plans--}}
                        <input type="hidden" value="{{$amount_create_is_inclusive}}" id="orignal_price_h">
                        <input type="hidden" value="{{$location_id}}" id="location_id_tax">
                        <input type="hidden" value="{{$service->tax_treatment_type_id}}" id="tax_treatment_type_id">
                        {{--end--}}

                        <div class="col-md-10 mt-12">
                            <!--begin::Option-->
                            <!-- <span class="switch switch-sm switch-icon switch_custom">
                                <div class="col-md-12" style="padding-left: 0">
                                    <strong>Exclusive</strong>

                                @if($service->tax_treatment_type_id == Config::get('constants.tax_both') || $service->tax_treatment_type_id == Config::get('constants.tax_is_exclusive'))

                                    <input type="hidden" name="is_exclusive" value="0"/>

                                        <label class="float-right">
                                            <input id="is_exclusive" type="checkbox" name="is_exclusive" value="1" checked>
                                            <span></span>
                                        </label>


                                @else
                                    <input type="hidden" name="is_exclusive" value="0"/>
                                        <label class="float-right">
                                            <input id="is_exclusive" type="checkbox" name="is_exclusive" value="0">
                                            <span></span>
                                        </label>

                                @endif

                                </div>
                            </span> -->
                        </div>


                    @endif
                @endif
                {{--End--}}

                {{--<div class="col-md-10 mt-5">
                    <strong>Amount</strong>
                    <strong class="float-right" id="amount_create">{{$amount_create}}</strong>
                </div>--}}
                <input type="hidden" class="amount_create" name="amount_create" value="{{$amount_create}}">


               {{-- <div class="col-md-10 mt-5">
                    <strong>Tax</strong>
                    <strong class="float-right" id="tax_create">{{$tax_create}}</strong>
                </div>--}}
                <input type="hidden" class="tax_create" name="tax_create" value="{{$tax_create}}">

                <div class="col-md-10 mt-5">
                    <strong>Total Amount</strong>
                    <strong class="float-right" id="price_create">{{$price}}</strong>
                    <input type="hidden" class="price_create" name="price_create" value="{{$price}}">
                    <input type="hidden" name="remaining" id="remaining" />
                </div>


                @if($balance > 0)
                    <div class="col-md-10 mt-5">
                        <strong>Balance Amount</strong>
                        <strong class="float-right" id="balance_create">{{$balance}}</strong>
                    </div>
                @endif
                <input type="hidden" class="balance_create" name="balance_create" value="{{$balance}}">


                @if($settleamount > 0)
                    <div class="col-md-10 mt-5">
                        <strong>Settle Amount</strong>
                        <strong class="float-right" id="settle_create">{{$settleamount}}</strong>
                    </div>
                @endif
                <input type="hidden" class="settle_create" name="settle_create" value="{{$settleamount}}">

                <div class="col-md-10 mt-5">
                    <strong>Outstanding</strong>
                    <strong class="float-right" id="outstand_create">{{$outstanding}}</strong>
                    <input type="hidden" class="outstand_create" name="outstand_create" value="{{$outstanding}}">
                </div>

                @if($outstanding > 0)
                <script>
                    $(document).ready(function() {
                        // Show outstanding message immediately if outstanding > 0
                        setTimeout(function() {
                            $('#outstandingMessage').show();
                            $('#treatment_addinvoice').hide();
                        }, 100);
                    });
                </script>
                @endif

                <!-- <div class="col-md-11 mt-5">
                    <strong class="mt-5">Date</strong> -->
                    <!-- @if(Auth::user()->hasRole('Super-Admin'))
                    <span><i  onclick="triggerDate('custom_field');" style="color: #cc8600; font-size: large; cursor: pointer;" class="la la-pencil float-right"></i></span>
                    @endif -->
                    <input type="hidden" name="created_at" value="{{\Carbon\Carbon::now()->format('Y-m-d')}}"
                           class="form-control float-right custom_field" id="created_at" readonly>
                <!-- </div> -->

                <div class="col-md-10 mt-5 mb-10" id="pay_section">
                    <!-- <strong class="mt-5">Pay</strong> -->
                    <input style="width: 50%;" type="hidden" name="cash_create" id="cash_create" value="0" class="form-control float-right" min="0" oninput="this.value = !!this.value && Math.abs(this.value) >= 0 ? Math.abs(this.value) : null;">
                </div>

                <div class="col-md-10 mt-5" id="paymentmode" style="display: none;">
                    <strong>Payment Mode</strong>
                    {!! Form::select('payment_mode_id',$paymentmodes ,old('payment_mode_id'),['class' => 'form-control float-right','id'=>'payment_mode_id', 'style' => 'width:50%;']) !!}

                </div>


                <div class="col-md-10 mt-5 mb-10">
                    <div id="treatment_addinvoice" style="display: none;">
                        <button class="btn btn-primary spinner-button" name="savepackageinformation" id="treatment_savepackageinformation"
                                style="float: right;margin-top:20px;"><i class="la la-paper-plane-o"></i> Consume & Print Invoice
                        </button>
                    </div>
                </div>

            </div>

        </div>

    </div>


</div>
