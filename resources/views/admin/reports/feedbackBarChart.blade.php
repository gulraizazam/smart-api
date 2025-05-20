@extends('admin.layouts.master')
@section('title', 'Feedback Report')
@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Feedback Report'])
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
                            <h3 class="card-label">Feedback Report Details</h3>
                        </div>
                    </div>
                   <div class="card-body">
                    <div class="mt-2 mb-7">
                        <h3>Ratings By Service Category</h3>
                        <div class="row justify-content-center align-items-center">

                            <div class="col-lg-12 col-xl-12 d-flex justify-content-center">
                                <div style="width: 450px; height: 450px; display: flex; justify-content: center; align-items: center;">
                                    <canvas id="serviceChart"></canvas>
                                </div>
                            </div>
                        </div>
                <h3>Ratings By Service</h3>
                        <div class="row justify-content-center align-items-center">

                            <div class="col-lg-12 col-xl-12 d-flex justify-content-center">
                                <div style="width: 450px; height: 450px; display: flex; justify-content: center; align-items: center;">
                                    <canvas id="treatmentChart" ></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    const serviceLabels = {!! json_encode($avgByService->pluck('service.name')) !!};
    const serviceData = {!! json_encode($avgByService->pluck('avg_rating')) !!};

    const treatmentLabels = {!! json_encode($avgByTreatment->pluck('treatment.name')) !!};
    const treatmentData = {!! json_encode($avgByTreatment->pluck('avg_rating')) !!};
    const serviceColors = {!! json_encode($avgByService->pluck('service.color')) !!};
    const treatmentColors = {!! json_encode($avgByTreatment->pluck('service.color')) !!};
    const serviceCtx = document.getElementById('serviceChart').getContext('2d');
    new Chart(serviceCtx, {
        type: 'pie',
        data: {
            labels: serviceLabels,
            datasets: [{
                label: 'Average Rating by Service',
                data: serviceData,


                backgroundColor: serviceColors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.label}: ${context.parsed.toFixed(2)}`;
                        }
                    }
                }
            }
        }
    });

    const treatmentCtx = document.getElementById('treatmentChart').getContext('2d');
    new Chart(treatmentCtx, {
        type: 'pie',
        data: {
            labels: treatmentLabels,
            datasets: [{
                label: 'Average Rating by Treatment',
                data: treatmentData,
                backgroundColor: treatmentColors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.label}: ${context.parsed.toFixed(2)}`;
                        }
                    }
                }
            }
        }
    });
</script>

@endsection
