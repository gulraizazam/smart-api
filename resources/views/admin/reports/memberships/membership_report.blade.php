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
            <h1>{{ 'Memberships Report' }}</h1>
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

            <div class="table-wrapper all-sections section-detail" id="topscroll">
                <table class="table" id="memberships_table">
                    <thead>
                        <tr>
                            <th>Patient Id</th>
                            <th>Patient Name</th>
                            <th>Location</th>
                            <th>Service Consumed</th>
                            <th>Assigned</th>
                            <th>Membership Type</th>

                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $patient)

                        <tr>
                            <td>{{$patient['user_id']}}</td>
                            <td>{{$patient['user_name']}}</td>
                            <td>{{$patient['location']}}</td>
                            <td>{{$patient['service_status']}}</td>
                            <td>{{$patient['membership_code']}}</td>
                            <td>{{$patient['membership_type']}}</td>

                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="clear clearfix"></div>
    <!-- Liabilities and Assets -->
    <script src="{{ url('assets/js/fake-scroll.js') }}" type="text/javascript"></script>
</div>