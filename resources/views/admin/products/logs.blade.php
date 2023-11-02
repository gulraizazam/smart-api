@extends('admin.layouts.master')
@section('title', 'Product Log')
@section('content')

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

        @include('admin.partials.breadcrumb', ['module' => 'Product Log', 'title' => 'Product Log'])

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
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                        width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <rect x="0" y="0" width="24" height="24" />
                                            <rect fill="#000000" opacity="0.3" x="12" y="4" width="3"
                                                height="13" rx="1.5" />
                                            <rect fill="#000000" opacity="0.3" x="7" y="9" width="3"
                                                height="8" rx="1.5" />
                                            <path
                                                d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z"
                                                fill="#000000" fill-rule="nonzero" />
                                            <rect fill="#000000" opacity="0.3" x="17" y="11" width="3"
                                                height="6" rx="1.5" />
                                        </g>
                                    </svg>
                                    <!--end::Svg Icon-->
                                </span>
                            </span>
                            <h3 class="card-label">Product Log</h3>
                        </div>
                    </div>

                    <div class="card-body">
                        <!--begin: Datatable-->
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Activity</th>
                                    <th>Date/Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($records as $data)
                                    @php
                                        $location_name = $data['location'] != 'N/A' ? 'centre' : 'warehouse';
                                        $location_area = $data['location'] != 'N/A' ? $data['location'] : $data['warehouse'];

                                        $to_location_name = $data['to_location'] != 'N/A' ? 'centre' : 'warehouse';
                                        $to_location_area = $data['to_location'] != 'N/A' ? $data['to_location'] : $data['to_warehouse'];

                                        $from_location_name = $data['from_location'] != 'N/A' ? 'centre' : 'warehouse';
                                        $from_location_area = $data['from_location'] != 'N/A' ? $data['from_location'] : $data['from_warehouse'];

                                        $properties = null;
                                        $child_product_id = null;

                                        if (isset($data['properties']['attributes'])) {
                                            $properties = $data['properties']['attributes'];
                                        }

                                        if (isset($data['properties']['attributes']['child_product_id'])) {
                                            $child_product_id = $data['properties']['attributes']['child_product_id'];
                                        }
                                        $timezone = 'Asia/Karachi';
                                    @endphp
                                    <tr>
                                        @switch($data['event'])
                                            @case('product_create')
                                                <td><strong>{{ $data['created_by'] }}</strong> created
                                                    <strong>{{ $data['product_name'] }}</strong> product with
                                                    {{ $properties['quantity'] }} quantity in
                                                    <strong>{{ $location_area }} {{ $location_name }}</strong>.
                                                </td>
                                                <td>{{ Carbon\Carbon::parse($data['created_at'])->setTimezone($timezone)->format('d-M-Y h:i:s a') }}
                                                </td>
                                            @break

                                            @case('product_update')
                                                <td><strong>{{ $data['updated_by'] }}</strong> updated
                                                    <strong>{{ $data['product_name'] }}</strong> product
                                                    <strong>{{ $location_area }} {{ $location_name }}</strong>.
                                                </td>
                                                <td>{{ Carbon\Carbon::parse($data['updated_at'])->setTimezone($timezone)->format('d-M-Y h:i:s a') }}
                                                </td>
                                            @break

                                            @case('product_transfer_create')
                                                @if ($data['product_id'] == $child_product_id)
                                                    <td>
                                                        <strong>{{ $to_location_area }} {{ $to_location_name }}</strong>
                                                        received {{ $properties['quantity'] }}
                                                        <strong>{{ $data['product_name'] }}</strong> products from
                                                        <strong>{{ $from_location_area }} {{ $from_location_name }}</strong> sent
                                                        by <strong>{{ $data['created_by'] }}</strong>.
                                                    </td>
                                                    <td>{{ Carbon\Carbon::parse($data['created_at'])->setTimezone($timezone)->format('d-M-Y h:i:s a') }}
                                                    </td>
                                                @else
                                                    <td><strong>{{ $data['created_by'] }}</strong> transferred
                                                        {{ $properties['quantity'] }}
                                                        <strong>{{ $data['product_name'] }}</strong> products from
                                                        <strong>{{ $from_location_area }} {{ $from_location_name }}</strong> to
                                                        <strong>{{ $to_location_area }} {{ $to_location_name }}</strong>.
                                                    </td>
                                                    <td>{{ Carbon\Carbon::parse($data['created_at'])->setTimezone($timezone)->format('d-M-Y h:i:s a') }}
                                                    </td>
                                                @endif
                                            @break

                                            @case('stock_add')
                                                <td><strong>{{ $data['created_by'] }}</strong> added {{ $properties['quantity'] }}
                                                    products in
                                                    <strong>{{ $data['product_name'] }}</strong> stock.
                                                </td>
                                                <td>{{ Carbon\Carbon::parse($data['updated_at'])->setTimezone($timezone)->format('d-M-Y h:i:s a') }}
                                                </td>
                                            @break

                                            @case('product_sale_price_update')
                                                <td><strong>{{ $data['created_by'] }}</strong> updated the price of
                                                    <strong>{{ $data['product_name'] }}</strong> to
                                                    {{ $properties['sale_price'] }}.
                                                </td>
                                                <td>{{ Carbon\Carbon::parse($data['updated_at'])->setTimezone($timezone)->format('d-M-Y h:i:s a') }}
                                                </td>
                                            @break

                                            @default
                                                <td>Data not found</td>
                                        @endswitch

                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <!--end: Datatable-->
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->

    @push('datatable-js')
        {{-- <script src="{{ asset('assets/js/pages/admin_settings/product_log.js') }}"></script> --}}
    @endpush

@endsection
