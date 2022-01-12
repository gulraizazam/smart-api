@extends('admin.layouts.master')

@section('content')

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'Sort Payment Modes', 'title' => 'Sort Payment Modes'])

    <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon">
                                <span class="svg-icon svg-icon-md svg-icon-primary">
                                    <!--begin::Svg Icon | path:assets/media/svg/icons/Shopping/Chart-bar1.svg-->
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                         width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <rect x="0" y="0" width="24" height="24"/>
                                            <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13"
                                                  rx="1.5"/>
                                            <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8"
                                                  rx="1.5"/>
                                            <path
                                                d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z"
                                                fill="#000000" fill-rule="nonzero"/>
                                            <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6"
                                                  rx="1.5"/>
                                        </g>
                                    </svg>
                                    <!--end::Svg Icon-->
                                </span>
                            </span>
                            <h3 class="card-label">Sort Payment Modes</h3>
                        </div>
                        <div class="card-toolbar">
                            <!--begin::Dropdown-->
                            <a href="{{route('admin.payment_modes.index')}}" class="btn btn-primary">
                                <i class="fa fa-arrow-left"></i> Back
                            </a>
                            <!--end::Button-->
                        </div>
                    </div>
                    <div class="row mr-2 ml-2 mt-5">
                        <div class="col-lg-12 draggable-zone" id="draggable-zone">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
        <!--end::Container-->
    </div>
    <!--end::Entry-->
    </div>
    <!--end::Content-->

    @push('js')
        <!--begin::Page Vendors(used by this page)-->
        <script src="{{asset('assets/plugins/custom/draggable/draggable.bundle.js?v=7.2.9')}}"></script>
        <!--end::Page Vendors-->
        <script src="{{asset('assets/js/pages/admin_settings/payment_modes_sort.js')}}"></script>
    @endpush

@endsection
