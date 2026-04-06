@extends('admin.layouts.master')
@section('title', 'Service Detail')
@section('content')

    @push('css')
        <link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.8/dist/trix.css">
        <style>
            .info-label {
                font-weight: 600;
                color: #3F4254;
                margin-bottom: 5px;
            }
            .info-value {
                color: #7E8299;
                margin-bottom: 15px;
            }
            .color-box {
                display: inline-block;
                width: 100px;
                height: 40px;
                border-radius: 4px;
                border: 1px solid #E4E6EF;
            }
            .service-description {
                background: #F3F6F9;
                padding: 15px;
                border-radius: 6px;
                min-height: 100px;
            }
            .child-service-item {
                padding: 10px;
                border-left: 3px solid #3699FF;
                background: #F3F6F9;
                margin-bottom: 10px;
                border-radius: 4px;
            }
            @media (max-width: 768px) {
                .card-header {
                    flex-wrap: wrap !important;
                    gap: 8px;
                }
                .card-toolbar {
                    width: 100%;
                }
                .card-toolbar .btn {
                    width: 100%;
                    text-align: center;
                }
            }
        </style>
    @endpush

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

        @include('admin.partials.breadcrumb', ['module' => 'Services', 'title' => 'Service Detail'])

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
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <rect x="0" y="0" width="24" height="24" />
                                            <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13" rx="1.5" />
                                            <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8" rx="1.5" />
                                            <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero" />
                                            <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5" />
                                        </g>
                                    </svg>
                                </span>
                            </span>
                            <h3 class="card-label">Service Details: {{ $service->name }}</h3>
                        </div>

                        <div class="card-toolbar">
                            <a href="{{ route('admin.services.index') }}" class="btn btn-secondary">
                                <i class="la la-arrow-left"></i>
                                Back to List
                            </a>
                        </div>
                    </div>

                    <div class="card-body">

                        <!-- Service Information -->
                        <div class="row mb-5">
                            <div class="col-md-12">
                                <div class="card card-custom bg-light">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="info-label">Service Name</div>
                                                <div class="info-value">{{ $service->name }}</div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="info-label">Status</div>
                                                <div class="info-value">
                                                    @if($service->active)
                                                        <span class="badge badge-success">Active</span>
                                                    @else
                                                        <span class="badge badge-danger">Inactive</span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="info-label">Duration</div>
                                                <div class="info-value">{{ $service->duration ? $service->duration . ' minutes' : 'N/A' }}</div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="info-label">Price</div>
                                                <div class="info-value">{{ number_format($service->price, 2) }}</div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="info-label">Color</div>
                                                <div class="info-value">
                                                    <div class="color-box" style="background-color: {{ $service->color }};"></div>
                                                    <span class="ml-3">{{ $service->color }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        @if($service->description)
                        <div class="row">
                            <div class="col-md-12">
                                <h4 class="mb-4">Description</h4>
                                <div class="service-description">
                                    {!! $service->description !!}
                                </div>
                            </div>
                        </div>
                        @endif

                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->

@endsection
