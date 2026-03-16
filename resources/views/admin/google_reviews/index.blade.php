@extends('admin.layouts.master')
@section('title', 'Google Reviews')
@section('content')

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'Google Reviews', 'title' => 'Doctor Google Reviews'])

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
                                    <i class="la la-google text-primary" style="font-size: 1.5rem;"></i>
                                </span>
                            </span>
                            <h3 class="card-label">Doctor Google Reviews</h3>
                        </div>
                        <div class="card-toolbar">
                            <div class="d-flex align-items-center">
                                <label class="mr-2 mb-0 font-weight-bold">Month:</label>
                                <select id="grMonth" class="form-control form-control-sm mr-2" style="width: 120px;">
                                    @for($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}" {{ $m == now()->month ? 'selected' : '' }}>
                                            {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                        </option>
                                    @endfor
                                </select>
                                <label class="mr-2 mb-0 font-weight-bold">Year:</label>
                                <select id="grYear" class="form-control form-control-sm" style="width: 90px;">
                                    @for($y = now()->year; $y >= now()->year - 3; $y--)
                                        <option value="{{ $y }}" {{ $y == now()->year ? 'selected' : '' }}>{{ $y }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-head-custom table-vertical-center" id="grTable">
                                <thead>
                                    <tr>
                                        <th class="pl-4">Doctor</th>
                                        <th>Branches</th>
                                        <th style="width: 140px;">Review Count</th>
                                        <th style="width: 80px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="grTableBody">
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-8">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
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

<script>
    var GR_CONFIG = {
        routes: {
            data: "{{ route('admin.google_reviews.data') }}",
            save: "{{ route('admin.google_reviews.save') }}"
        }
    };
</script>
<script src="{{ asset('assets/js/pages/admin_settings/google_reviews.js') }}"></script>
@endsection
