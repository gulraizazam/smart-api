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
    <div id="definefield" class="alert alert-warning display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
        Kindly define payment mode
    </div>
    <div id="definetreatment" class="alert alert-warning display-hide" style="display: none;">
        <button class="close" data-close="alert"></button>
        Kindly select the treatment
    </div>
    {{--End--}}

    {{--Some hidden Fields that helps us for saving invoice--}}
    <input type="hidden" value="{{$id}}" id="appointment_id_create">
    <input type="hidden" value="{{$settleamount}}" id="settleamount_for_zero" name="settleamount_for_zero">
    <input type="hidden" value="{{$outstanding}}" id="outstanding_for_zero" name="outstanding_for_zero">
    <input type="hidden" id="package_service_id" name="package_service_id">
    <input type="hidden" value="{{$checked_treatment}}" id="checked_treatment" name="checked_treatment">
    <input type="hidden" value="0" id="checked_bundle_id" name="checked_bundle_id">

    {{--End--}}

    {{--That if condition show for Service with and without package--}}
    @if($appointment_type->name == Config::get('constants.Service'))
        @if($status == 'false')
            <div class="row">
                <div class="col-md-6">
                    <select class="form-control select2 disabled-field" disabled>
                        <option value="">Select Package</option>
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
                        <th> Discount Type</th>
                        <th> Discount Price</th>
                        <th> Amount</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>{{$service->name}}</td>
                        <td><?php echo number_format($amount_create_is_inclusive);?></td>
                        <td>-</td>
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
                        <option value="">Select Package</option>
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
                        <th> Price</th>
                        <th> Discount Name</th>
                        <th> Discount Type</th>
                        <th> Discount Price</th>
                        <th> Amount</th>
                        <th> Tax %</th>
                        <th> Tax Amt.</th>
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
    {{--That is generic inputs that use for all conditon--}}
    <div class="invice-holder">

        {{--In case if treatment not belong to treatment plan--}}
        @if($status == 'false')
            <div class="row">
                <div class="col-md-4 text-right"></div>
                <div class="col-md-4 text-right">
                    <label><strong>Appointment</strong></label>
                </div>
                <div class="col-md-4">
                    <select name="appointment_link_cons" id="appointment_link_cons" class="form-control select2">
                        <option value="">Select Appointment</option>
                        @foreach($appointmentArray as $appointment)
                            <option value="{{$appointment['id']}}"
                                    @if ($loop->first) selected @endif>{{$appointment['name']}}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif
        {{--End--}}
        <br>
        {{--In case of services not belong to treatment plans--}}
        @if($appointment_type->name == Config::get('constants.Service'))
            @if($status == 'false')

                {{--Hidden Input for service that not belongs to treatment plans--}}
                <input type="hidden" value="{{$amount_create_is_inclusive}}" id="orignal_price_h">
                <input type="hidden" value="{{$location_id}}" id="location_id_tax">
                <input type="hidden" value="{{$service->tax_treatment_type_id}}" id="tax_treatment_type_id">
                {{--end--}}

                <div class="row">
                    <div class="col-md-4 text-right"></div>
                    <div class="col-md-4 text-right">
                        <label><strong>Exclusive</strong></label>
                    </div>
                    <div class="col-md-4">
                        <label class="mt-checkbox is_consultancy_1">
                            @if($service->tax_treatment_type_id == Config::get('constants.tax_both') || $service->tax_treatment_type_id == Config::get('constants.tax_is_exclusive'))

                                <input type="hidden" name="is_exclusive" value="0"/>
                                <label class="custom_checkbox checkbox-all">
                                    <input class="select-all-checkboxes" id="is_exclusive" type="checkbox" name="is_exclusive" value="1" checked>
                                    <strong></strong>
                                </label>

                            @else
                                <input type="hidden" name="is_exclusive" value="0"/>
                                <label class="custom_checkbox checkbox-all">
                                    <input class="select-all-checkboxes"  id="is_exclusive" type="checkbox" name="is_exclusive" value="0">
                                    <strong></strong>
                                </label>
                            @endif
                        </label>
                    </div>
                </div>
            @endif
        @endif
        {{--End--}}
        <br>
        {{--introduce for tax--}}
        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Amount</strong></label>
            </div>
            <div class="col-md-4">
                <input type="text" name="amount_create" id="amount_create" class="form-control disabled-field" value="{{$amount_create}}"
                       readonly>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Tax Price</strong></label>
            </div>
            <div class="col-md-4">
                <input type="text" name="tax_create" id="tax_create" class="form-control disabled-field" value="{{$tax_create}}" readonly>
            </div>
        </div>
        {{--end for introduce tax--}}
        <br>

        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Tax Amt.</strong></label>
            </div>
            <div class="col-md-4">
                <input type="text" name="price_create" id="price_create" class="form-control" value="{{$price}}" readonly>
                <input type="hidden" name="remaining" id="remaining" />
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Balance Amount</strong></label>
            </div>
            <div class="col-md-4">
                <input type="text" name="balance_create" id="balance_create" class="form-control disabled-field" value="{{$balance}}"
                       readonly>
            </div>
        </div>
        <br>

        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Amount Received</strong></label>
            </div>
            <div class="col-md-4">
                <input type="number" name="cash_create" id="cash_create" value="0" class="form-control">
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Settle Amount</strong></label>
            </div>
            <div class="col-md-4">
                <input type="text" name="settle_create" id="settle_create" class="form-control disabled-field" value="{{$settleamount}}"
                       readonly>
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-4 text-right"></div>
            <div class="col-md-4 text-right">
                <label><strong>Outstanding</strong></label>
            </div>
            <div class="col-md-4">
                <input type="text" name="outstand_create" id="outstand_create" class="form-control disabled-field" value="{{$outstanding}}"
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
                {!! Form::select('payment_mode_id',$paymentmodes ,old('payment_mode_id'),['class' => 'form-control','id'=>'payment_mode_id']) !!}
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12" id="treatment_addinvoice">
            <button class="btn btn-success spinner-button" name="savepackageinformation" id="treatment_savepackageinformation"
                    style="float: right;margin-top:20px;">Save
            </button>
        </div>
    </div>
    {{--End--}}

</div>
