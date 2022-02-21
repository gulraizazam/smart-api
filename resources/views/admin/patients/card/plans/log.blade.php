@extends('admin.patients.card.patient_layout')
@inject('Auth', 'Auth')
@inject('filters', 'App\Helpers\Filters')
@section('patient_stylesheets')
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <link href="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css'}}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/datatables.min.css') }}" rel="stylesheet"
          type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.css') }}"
          rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}"
          rel="stylesheet" type="text/css"/>
    <link href="{{ url('metronic/assets/pages/css/invoice.min.css') }}" rel="stylesheet" type="text/css"/>
@endsection

@section('patient_content')
    <div class="portlet-title tabbable-line">
        <div class="caption caption-md">
            <i class="icon-globe theme-font hide"></i>
            <span class="caption-subject font-blue-madison bold uppercase">@lang('global.invoices.title')</span>
        </div>
    </div>
    <div class="portlet-body">
        <div class="table-container">
        </div>
    </div>
    <div class="portlet-body">
        <div class="portlet light bordered">
            <div class="portlet-title">

                <div class="caption">
                    <span class="caption-subject font-green-sharp bold uppercase">Package id {{$id}}</span>
                </div>

                <div class="actions">
                    @if (Gate::allows('patients_plan_log_excel'))
                        <a href="{{ route('admin.plans.log',[$id,$patient->id, 'excel']) }}" class="btn green">Excel</a>
                        <span style="padding-right: 5px;"></span>
                    @endif
                    <a href="{{ route('admin.plans.index',[$patient->id]) }}" class="btn dark pull-right">@lang('global.app_back')</a>

                </div>

            </div>
            <div class="portlet-body">
                <input type="hidden" id="patient_id" value="{{ $patient->id }}"/>
                <div class="portlet-body table-wrapper" style="overflow: auto;">
                    @if(count($finance_log))
                        @php $f_count = 1; @endphp
                        <table id="table" class="table">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Cash Flow</th>
                                <th>Cash Amount</th>
                                <th>Refund</th>
                                <th>Adjustment</th>
                                <th>Tax</th>
                                <th>Cancel</th>
                                <th>Delete</th>
                                <th>Refund Note</th>
                                <th>Payment Mode</th>
                                <th>Appointment Type</th>
                                <th>Location</th>
                                <th>Created By</th>
                                <th>Updated_by</th>
                                <th>Plan</th>
                                <th>Invoice Id</th>
                                <th>Created At Shown</th>
                                <th>Updated At Shown</th>
                                <th>Created At</th>
                                <th>Updated At</th>
                                <th>Deleted At</th>
                                <th>Detail</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($finance_log as $log)
                                @if( (isset($log['package_id']) && $log['package_id'] == $id) || !isset($log['package_id']))
                                    <tr>
                                        <td>{{$f_count++}}</td>
                                        <td>{{isset($log['cash_flow'])?$log['cash_flow']:'-'}}</td>
                                        <td>{{isset($log['cash_amount'])?$log['cash_amount']:'-'}}</td>
                                        <td>{{isset($log['is_refund'])?$log['is_refund']:'-'}}</td>
                                        <td>{{isset($log['is_adjustment'])?$log['is_adjustment']:'-'}}</td>
                                        <td>{{isset($log['is_tax'])?$log['is_tax']:'-'}}</td>
                                        <td>{{isset($log['is_cancel'])?$log['is_cancel']:'-'}}</td>
                                        @if($log['action'] == 'Delete')
                                            <td>{{'Yes'}}</td>
                                        @else
                                            <td>{{'-'}}</td>
                                        @endif
                                        <td>{{isset($log['refund_note'])?$log['refund_note']:'-'}}</td>
                                        <td>{{isset($log['payment_mode_id'])?$log['payment_mode_id']:'-'}}</td>
                                        <td>{{isset($log['appointment_type_id'])?$log['appointment_type_id']:'-'}}</td>
                                        <td>{{isset($log['location_id'])?$log['location_id']:'-'}}</td>
                                        <td>{{isset($log['created_by'])?$log['created_by']:'-'}}</td>
                                        @if(isset($log['cash_flow']))
                                            <td>{{isset($log['updated_by'])?$log['updated_by']:'-'}}</td>
                                        @else
                                            <td>{{$log['user_id']}}</td>
                                        @endif
                                        <td>{{isset($log['package_id'])?$log['package_id']:'-'}}</td>
                                        <td>{{isset($log['invoice_id'])?$log['invoice_id']:'-'}}</td>
                                        <td>{{isset($log['created_at'])?$log['created_at'] == $log['created_at_orignal']?'-':$log['created_at']:'-'}}</td>
                                        <td>{{isset($log['updated_at'])?$log['updated_at'] == $log['updated_at_orignal']?'-':$log['updated_at']:'-'}}</td>
                                        @if($log['action'] == 'Delete')
                                            <td>{{'-'}}</td>
                                            <td>{{'-'}}</td>
                                        @else
                                            <td>{{isset($log['created_at_orignal'])?\Carbon\Carbon::parse($log['created_at_orignal'])->format('F j,Y h:i A'):'-'}}</td>
                                            <td>{{isset($log['updated_at_orignal'])?\Carbon\Carbon::parse($log['updated_at_orignal'])->format('F j,Y h:i A'):'-'}}</td>
                                        @endif
                                        <td>{{ isset($log['deleted_at']) ? \Carbon\Carbon::parse($log['deleted_at'])->format('F j, Y h:i A') : '-' }}</td>
                                        @if(isset($log['detail_log']) && count($log['detail_log']))
                                            <td align="center"><a href="javascript:void(0);"
                                                                  onclick="toggle({{$log['id']}})"
                                                                  class="btn-xs dark"><i
                                                            class="fa fa-eye fa-lg"></i></a></td>
                                        @else
                                            <td align="center"><span class=" btn-xs dark"><i
                                                            class="fa fa-eye-slash fa-lg"></i></span></td>
                                        @endif
                                    </tr>
                                    @if(isset($log['detail_log']) && count($log['detail_log']))
                                        <tr id="child-{{ $log['id'] }}" style="display:none;">
                                            <td width="100%" colspan="21" class="table-wrapper-child">
                                                <table id="table" class="table" style="border:1px solid #364150;">
                                                    <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Field Name</th>
                                                        <th>Before</th>
                                                        <th>After</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php $count = 1;?>
                                                    @foreach($log['detail_log'] as $detail)
                                                        <tr>
                                                            <td>{{$count++}}</td>
                                                            <td>{{isset($detail['field_name'])?$detail['field_name']:'-'}}</td>
                                                            <td>{{isset($detail['field_before'])?$detail['field_before']:'-'}}</td>
                                                            <td>{{isset($detail['field_after'])?$detail['field_after']:'-'}}</td>
                                                        </tr>
                                                    @endforeach
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    @endif
                                @endif
                            @endforeach
                            </tbody>
                        </table>
                    @else
                        <tr>
                            <td colspan="4">No Finance log found.</td>
                        </tr>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <!-- End: Demo Datatable 1 -->
@stop

@section('javascript')
    <script>
        function toggle(id) {
            $("#child-" + id).slideToggle(300);
        }
    </script>
    <script src="{{ url('metronic/assets/global/plugins/datatables/datatables.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/datatables/plugins/bootstrap/datatables.bootstrap.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"
            type="text/javascript"></script>
    <!-- END PAGE LEVEL PLUGINS -->
    <!-- BEGIN PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/plugins/select2/js/select2.full.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/scripts/datatable.js') }}" type="text/javascript"></script>
    <!-- END PAGE LEVEL SCRIPTS -->
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}"
            type="text/javascript"></script>
    <script src="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'}}"
            type="text/javascript"></script>
@endsection