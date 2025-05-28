@extends('admin.layouts.master')
@section('title', 'Feedback Report')
@section('content')
<!-- Only include these if you need DataTables -->
{{--
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
--}}

<!--begin::Content-->
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Services Sold Report'])

    <!--begin::Entry-->
    <div class="d-flex flex-column-fluid">
        <!--begin::Container-->
        <div class="container">
            <!--begin::Card-->
            <div class="card card-custom">
                <div class="card-header py-3">
                    <div class="card-title d-flex align-items-center">
                        <span class="card-icon mr-2">
                            <span class="svg-icon svg-icon-md svg-icon-primary">
                                <!-- SVG icon here -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13" rx="1.5" />
                                    <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8" rx="1.5" />
                                    <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5" />
                                    <path d="M5,19 L20,19 C20.5523,19 21,19.4477 21,20 C21,20.5523 20.5523,21 20,21 L4,21 C3.4477,21 3,20.5523 3,20 L3,4 C3,3.4477 3.4477,3 4,3 C4.5523,3 5,3.4477 5,4 L5,19 Z" fill="#000000" />
                                </svg>
                            </span>
                        </span>
                        <h2>Service Sold by Centre: {{ $service->name }}</h2>

                    </div>
                     @if($start_date && $end_date)
                                    <p>From: {{ \Carbon\Carbon::parse($start_date)->format('d M Y') }} To: {{ \Carbon\Carbon::parse($end_date)->format('d M Y') }}</p>
                                @endif
                </div>
                <div class="card-body">
                    <div class="mt-2 mb-7">

                        <canvas id="barChart" style="width: 100%;  height: 400px;"></canvas>
                        @if(count($labels) === 0)
                            <p>No sales data found for this service.</p>
                        @endif
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
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('barChart').getContext('2d');

        const barChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: @json($labels),
                datasets: [{
                    label: 'Total Sold',
                    data: @json($values),
                  backgroundColor: 'rgba(105, 147, 255, 0.85)',
                    borderColor: 'rgba(105, 147, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
                        formatter: Math.round,
                        font: {
                            weight: 'bold'
                        }
                    }
                }
            },
            plugins: [ChartDataLabels]
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

@endsection
