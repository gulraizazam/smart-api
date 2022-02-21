<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title">@lang('global.app_detail')</h4>
</div>
<div class="modal-body">
    <div class="portlet-body form">
        <div class="form-group">
            <div class="form-body">
                <div class="row">
                    <div class="col-md-12">
                        {!! Form::label('parent_id', 'Patient*', ['class' => 'control-label']) !!}
                        <span style="font-size:18px;display: block;"><strong>{{$patient_name->name}}</strong></span>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="portlet-body">
                            <div class="table-scrollable">
                                <table class="table table-striped table-hover">
                                    <thead>
                                    <tr>
                                        <th> #</th>
                                        <th> Amount</th>
                                        <th> Cash Flow</th>
                                        <th> Is_Refund</th>
                                        <th> Package</th>
                                        <th> invoice</th>
                                        <th> Appointment</th>
                                        <th> Refund note</th>
                                        <th> Created at</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php $c = 1; ?>
                                    @foreach($package_advances as $packageadvances)
                                        <tr>
                                            <td>{{$c++}}</td>
                                            <td><?php echo number_format($packageadvances->cash_amount); ?></td>
                                            <td>{{$packageadvances->cash_flow}}</td>
                                            <td><?php echo $packageadvances->is_refund ? 'Yes' : 'No'; ?></td>

                                            <td>
                                                @if($packageadvances->package_id)
                                                    {{ $packageadvances->package->name }}
                                                @else
                                                    {{ '-' }}
                                                @endif
                                            </td>

                                            <td>
                                                @if($packageadvances->invoice_id)
                                                    <?php echo  sprintf('%05d', $packageadvances->invoice->id);?>
                                                @else
                                                    {{ '-' }}
                                                @endif
                                            </td>
                                            <td>
                                                @if($packageadvances->appointment_id)
                                                    <?php echo  sprintf('%05d', $packageadvances->appointment->id);?>
                                                @else
                                                    {{ '-' }}
                                                @endif
                                            </td>
                                            <td>{{$packageadvances->refund_note}}</td>
                                            <td>{{\Carbon\Carbon::parse($packageadvances->created_at)->format('F j,Y h:i A')}}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>