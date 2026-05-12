@inject('request', 'Illuminate\Http\Request')
@if($request->get('medium_type') != 'web')
@if($request->get('medium_type') == 'pdf')
@include('partials.pdf_head')
@else
@include('partials.head')
@endif
@endif
<div class="sn-table-holder">
    <div class="sn-report-head">
        <div class="sn-title">
            <h1></h1>
        </div>
    </div>
</div>
<div class="panel-body sn-table-body">
    <div class="bordered">
        <div class="sn-table-head">
            <div class="row">
                <div class="col-md-2">
                    <img style="width: 180px;" src="{{asset('allura-logo1.jpeg')}}">
                </div>
                <div class="col-md-6">&nbsp;</div>
            </div>
            <div class="pt-4 border-top  all-sections section-states">
               
                <div class="col-md-12 mb-3">
                    <h3 class="">{{$centre}}  {{'(From ' . $start_date . ' To '. $end_date. ')'}}</h3>
                    <table class="table border">
                        <thead>
                            <tr class="">
                                <td class="bg-light">Total Scheduled Appointments</td>
                                <td class="bg-light" style="text-align:right;">{{$totalScheduled ?? 0}}</td>
                            </tr>
                            <tr class="">
                                <td class="border-top bg-light">Total Arrived Appointments</td>
                                <td class="border-top bg-light" style="text-align:right;">{{$totalArrived ?? 0}}</td>
                            </tr>
                           
                            <!-- <tr class="">
                                <td class="border-top bg-light">Arrival Ratio</td>
                                <td class="border-top bg-light" style="text-align:right;">
                                    <?php
                                    // if (isset($arrived) && isset($Appointments)) {
                                    //     echo number_format(($arrived / count($Appointments)) * 100, 2) . '%';
                                    // } else {
                                    //     echo '00.00 %';
                                    // }
                                    ?>
                                </td>
                            </tr> -->

                            
                            <tr class="">
                                <td class="border-top bg-light">Arrival Ratio </td>
                                <td class="border-top bg-light" style="text-align:right;">
                                    {{$percentageArrived ?? '0.00%'}}
                                </td>
                            </tr>
                            
                        </thead>
                    </table>
                </div>
                
            </div>
        </div>
    </div>
    <div class="clear clearfix"></div>
    <!-- Liabilities and Assets -->
    <script src="{{ url('assets/js/fake-scroll.js') }}" type="text/javascript"></script>
</div>