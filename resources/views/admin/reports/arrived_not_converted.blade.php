@extends('admin.layouts.master')
@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    @push('css')
        <style>
            .table-wrapper {
                overflow-x: scroll;
            }
            .sn-report-head{
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                padding: 8px 15px 10px;
            }
            .sn-report-head {
                background-color: #02203d;
                color: #fff;
            }
            .sn-white-btn {
                background-color: #35a1d4 !important;
                border: #35a1d4 !important;
                color: #fff !important;
            }
            .sn-white-btn > i {
                color: #fff !important;;
            }
            .shdoc-header {
                background: rgba(54, 65, 80, 1) !important;
                color: #fff !important;
                font-weight: bold !important;
            }
        </style>
    @endpush
    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Operation Reports'])
    <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">
                <!--begin::Card-->
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon">
                                <span class="svg-icon svg-icon-md svg-icon-primary">
                                    <!--begin::Svg Icon | path:assets/media/svg/icons/Shopping/Chart-bar1.svg-->
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <rect x="0" y="0" width="24" height="24" />
                                            <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13" rx="1.5" />
                                            <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8" rx="1.5" />
                                            <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero" />
                                            <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5" />
                                        </g>
                                    </svg>
                                    <!--end::Svg Icon-->
                                </span>
                            </span>
                            <h3 class="card-label">Arrived But Not Converted</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mt-2 mb-7">
                            <div class="row align-items-center">
                                <div class="col-lg-12 col-xl-12">
                                    <div class="row align-items-center">
                                        <div class="clear clearfix"></div>
                                            <div style="overflow: hidden; width: 100%;" id="content"></div>
                                                <div class="panel-body sn-table-body">
                                                    <div class="bordered">
                                                        <div class="sn-table-head">
                            
                                                            <div class="table-wrapper all-sections section-detail" id="topscroll">
                                                                <table class="table" id="arrived_patients_table">
                                                                    <thead>
                                                                    <tr>
                                                                        <th>ID</th>
                                                                        <th>Patient Name</th>
                                                                        <th>Phone</th>
                                                                        <th>Service</th>
                                                                        <th>Doctor</th>
                                                                        <th>Centre</th>
                                                                        <th>Scheduled Date</th>
                                                                    </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach($patients as $patient)
                                                                        <?php
                                                                        $doct = \App\Models\User::whereId($patient->doctor_id)->first();
                                                                        $service = \App\Models\Services::whereId($patient->service_id)->first();
                                                                        $loc = \App\Models\Locations::whereId($patient->location_id)->first();
                                                                        ?>
                                                                        <tr>
                                                                            <td>{{$patient->id}}</td>
                                                                            <td>{{$patient->name}}</td>
                                                                            <td>{{$patient->phone}}</td>
                                                                            <td>{{$service->name}}</td>
                                                                            <td>{{$doct->name}}</td>
                                                                            <td>{{$loc->name}}</td>
                                                                            <td>{{$patient->scheduled_date}}</td> 
                                                                        </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>

                                                        </div>
                                                    </div>
                                                    <div class="clear clearfix"></div>
                                                    <script src="{{ url('assets/js/fake-scroll.js') }}" type="text/javascript"></script>
    
                                                </div>
                                            </div>
                                        </div>

                                    </div>

                                </div>

                            </div>
                        </div>
               
                    </div>
                </div>
            </div>
            @include('admin.settings.edit')
            @push('datatable-js')
                <script src="{{asset('assets/js/pages/admin_settings/settings.js')}}"></script>
                <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
                <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
                <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
                <script>
                    $("#arrived_patients_table").DataTable({
                        dom: 'Bfrtip',
                        buttons: [
                            'copyHtml5',
                            'excelHtml5',
                            'csvHtml5',
                            'pdfHtml5'
                        ]
                    });
                </script>
            @endpush
@endsection