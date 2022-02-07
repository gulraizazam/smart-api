<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title">@lang('global.app_display')</h4>
</div>
<div class="modal-body">
    <div class="portlet-body form">
        <div class="form-group">
            <div class="form-body">
                <div class="row">
                    <div class="form-group col-md-4">
                        {!! Form::label('parent_id', 'Patient', ['class' => 'control-label']) !!}
                        <span style="font-size:18px;display: block;"><strong>{{$package->user->name}}</strong></span>
                        <input type="hidden" id="parent_id" name="parent_id" value="{{$package->patient_id}}">
                    </div>
                    <div class="form-group col-md-4">
                        {!! Form::label('location_id', 'Location', ['class' => 'control-label']) !!}
                        <span style="font-size:18px;display: block;"><strong>{{$package->location->name}}</strong></span>
                    </div>
                </div>
                {{--Table for display information of services with discount package--}}
                <div class="table-responsive">
                    <table id="table" class="table table-striped table-bordered table-advance table-hover">
                        {{ csrf_field() }}
                        <thead>
                        <tr>
                            <th>Service Name</th>
                            <th>Service Price</th>
                            <th>Discount Name</th>
                            <th>Discount Type</th>
                            <th>Discount Price</th>
                            <th>Subtotal</th>
                            <th>Tax %</th>
                            <th>Tax Price</th>
                            <th>Total</th>
                        </tr>
                        </thead>
                        @if($packagebundles)
                            @foreach($packagebundles as $packagebundles)
                                <tr>
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
                                    <td>{{$packagebundles->tax_price}}</td>
                                    <td>{{$packagebundles->tax_including_price}}</td>

                                </tr>
                                @foreach ($packageservices as $packageservice)
                                    @if($packageservice->package_bundle_id == $packagebundles->id )
                                        <?php if ($packageservice->is_consumed == '0') {
                                            $consume = 'NO';
                                        } else {
                                            $consume = 'YES';
                                        }?>
                                        <tr class="{{$packagebundles->id}}" style="display: none">
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
                {{--Grand total show here--}}
                <div class="row">
                    <div class="col-md-10 col-xs-8"></div>
                    <div class="col-md-2 col-xs-4 invoice-block">
                        <ul class="list-unstyled amounts">
                            <li>
                                <strong>Total:</strong> <?php echo number_format($package->total_price);?>/-
                            </li>
                        </ul>
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
                        </tr>
                        </thead>
                        @if($packageadvances)
                            @foreach($packageadvances as $packageadvances)
                                @if($packageadvances->cash_amount != '0' && $packageadvances->is_tax == 0)
                                    <tr>
                                    <td><?php echo $packageadvances->paymentmode->name; ?></td>
                                    <td><?php echo $packageadvances->cash_flow; ?></td> 
                                    @if($packageadvances->cash_flow == 'out' && $packageadvances->is_tax == 0)
                                        @if(! is_null($packageadvances->refund_note))
                                                <td>{{App\Models\PackageAdvances::getAppointmentPackage($packageadvances->appointment_id, $packageadvances->patient_id, $packageadvances->id)}}</td>
                                        @else
                                                <td>{{App\Models\PackageAdvances::getAppointmentPackage($packageadvances->appointment_id, $packageadvances->patient_id)}}</td>
                                        @endif
                                    @elseif($packageadvances->is_tax == 0)
                                    <td><?php echo number_format($packageadvances->cash_amount) ?></td>
                                    @endif
                                    
                                        
                                        <td><?php echo \Carbon\Carbon::parse($packageadvances->created_at)->format('F j,Y h:i A'); ?></td>
                                    </tr>
                                @endif
                            @endforeach
                        @endif
                    </table>
                </div>
                {{--End--}}
            </div>
        </div>
    </div>
    <div style="text-align: right">
        <a class="btn btn-lg blue hidden-print margin-bottom-5" target="_blank"
           href="{{ route('admin.packages.package_pdf',[$package->id]) }}">@lang('global.app_pdf')
            <i class="fa fa-print"></i>
        </a>
    </div>
</div>
<script>
    function toggle(id) {
        $("." + id).toggle();
    }
</script>




