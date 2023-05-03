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
            <h1>{{ 'Staff Wise Arrival Report' }}</h1>
        </div>

    </div>
</div>
<div class="panel-body sn-table-body">
    <div class="bordered">
        <div class="sn-table-head">

            <div class="row">
                <div class="col-md-2">
                    <img style="width: 180px;" src="{{asset('logo_final.png')}}">
                </div>
                <div class="col-md-6">&nbsp;</div>
            </div>
            <div class="pt-4 border-top  all-sections section-states" >
                @if(isset($Appointments) && count($Appointments) > 0 )
                <div class="col-md-12 mb-3">
                    <h3 class="">{{$user ? $user : $Appointments[0]->location->name ?? ''}}</h3>
                    <table class="table border">
                        <thead>
                            <tr class="">
                                <td class="bg-light">Total Scheduled Appointments</td>
                                <td class="bg-light" style="text-align:right;">{{count($Appointments) ?? 0}}</td>
                            </tr>
                            <tr class="">
                                <td class="border-top bg-light"> Arrived</td>
                                <td class="border-top bg-light" style="text-align:right;">{{$arrived ?? 0}}</td>
                            </tr>
                                <tr class="">
                                    <td class="border-top bg-light" >Arrival Ratio</td>
                                    <td class="border-top bg-light" style="text-align:right;">
                                        <?php
                                        if (isset($arrived) && isset($Appointments)) {
                                            echo number_format(($arrived / count($Appointments)) * 100, 2) . '%';
                                        } else {
                                            echo '00.00 %';
                                        }
                                        ?>
                                    </td>
                                </tr>
                        </thead>
                    </table>
                </div>
                @else
                <div class="col-md-12 mb-3">
                    <h3 class="">{{$user ? $user : $Appointments[0]->location->name ?? ''}}</h3>
                    <table class="table border">
                        <thead>
                            <tr class="">
                                <td class="bg-light">Total Scheduled Appointments</td>
                                <td class="bg-light" style="text-align:right;">{{count($Appointments) ?? 0}}</td>
                            </tr>
                            <tr class="">
                                <td class="border-top bg-light"> Arrived</td>
                                <td class="border-top bg-light" style="text-align:right;">{{$arrived ?? 0}}</td>
                            </tr>
                                <tr class="">
                                    <td class="border-top bg-light" >Arrival Ratio</td>
                                    <td class="border-top bg-light" style="text-align:right;">
                                        <?php
                                        if (count($Appointments) > 0) {
                                            if (isset($arrived) && isset($Appointments)) {
                                                echo number_format(($arrived / count($Appointments)) * 100, 2) . '%';
                                            } else {
                                                echo '00.00 %';
                                            }
                                        } else{
                                            echo '00.00 %';
                                        }
                                        ?>
                                    </td>
                                </tr>
                        </thead>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>
    <div class="clear clearfix"></div>
    <!-- Liabilities and Assets -->
    <script src="{{ url('assets/js/fake-scroll.js') }}" type="text/javascript"></script>
</div>

