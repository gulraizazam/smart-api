@extends('admin.layouts.master')
@section('title', 'Patient Card - ' . $patient->name)

@push('css')
<style>
    .patient-card-sidebar {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    .patient-card-nav .nav-link {
        color: #3F4254;
        padding: 12px 20px;
        border-left: 3px solid transparent;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .patient-card-nav .nav-link:hover {
        background: #F3F6F9;
        border-left-color: #3699FF;
    }
    .patient-card-nav .nav-link.active {
        background: #E1F0FF;
        border-left-color: #3699FF;
        color: #3699FF;
        font-weight: 600;
    }
    .patient-card-nav .nav-link i {
        width: 20px;
        font-size: 16px;
    }
    .patient-header {
        background: linear-gradient(135deg, #3699FF 0%, #1B5E9E 100%);
        color: #fff;
        padding: 30px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .patient-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background-size: cover;
        background-position: center;
        border: 3px solid #fff;
    }
    .section-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        padding: 20px;
    }
</style>
@endpush

@section('content')
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Patients', 'title' => $patient->name])
    
    <div class="d-flex flex-column-fluid">
        <div class="container">
            <div class="row">
                {{-- Sidebar --}}
                <div class="col-lg-3 col-md-4">
                    <div class="patient-card-sidebar mb-4">
                        {{-- Patient Info --}}
                        <div class="text-center p-4 border-bottom">
                            <div class="patient-avatar mx-auto mb-3" style="background-image: url('{{ $patient->image_src ? route('admin.files.patient_image', ['filename' => $patient->image_src]) : asset('assets/media/logos/avatar.jpg') }}')"></div>
                            <h5 class="mb-1">{{ $patient->name }}</h5>
                            <span class="text-muted">C-{{ $patient->id }}</span>
                            <div class="mt-2">
                                @if($membership && $membership->active==1)
                                    @php
                                        $membershipName = $membership->membershipType->name ?? 'Active';
                                        $isGold =  $membership->membershipType->name === 'Gold Membership';
                                    @endphp
                                    <span class="badge" style="background-color: {{ $isGold ? '#B8860B' : '#3699FF' }}; color: #fff;">{{ $membershipName }}</span>
                                @elseif($membership && $membership->active==0)
                                    @php
                                        $membershipName = $membership->membershipType->name ?? 'Membership';
                                    @endphp
                                    <span class="badge badge-secondary">{{ $membershipName }} - Expired</span>
                                @endif
                            </div>
                        </div>
                        
                        {{-- Navigation --}}
                        <nav class="patient-card-nav">
                            <a href="{{ route('admin.patients.card', ['id' => $patientId, 'section' => 'profile']) }}" 
                               class="nav-link {{ $section == 'profile' ? 'active' : '' }}">
                                <i class="la la-user"></i> Profile
                            </a>
                            <a href="{{ route('admin.patients.card', ['id' => $patientId, 'section' => 'consultations']) }}" 
                               class="nav-link {{ $section == 'consultations' ? 'active' : '' }}">
                                <i class="la la-calendar"></i> Consultations
                            </a>
                            <a href="{{ route('admin.patients.card', ['id' => $patientId, 'section' => 'treatments']) }}" 
                               class="nav-link {{ $section == 'treatments' ? 'active' : '' }}">
                                <i class="la la-medkit"></i> Treatments
                            </a>
                            <a href="{{ route('admin.patients.card', ['id' => $patientId, 'section' => 'plans']) }}" 
                               class="nav-link {{ $section == 'plans' ? 'active' : '' }}">
                                <i class="la la-list-alt"></i> Plans
                            </a>
                            <a href="{{ route('admin.patients.card', ['id' => $patientId, 'section' => 'invoices']) }}" 
                               class="nav-link {{ $section == 'invoices' ? 'active' : '' }}">
                                <i class="la la-file-invoice-dollar"></i> Invoices
                            </a>
                            <a href="{{ route('admin.patients.card', ['id' => $patientId, 'section' => 'refunds']) }}" 
                               class="nav-link {{ $section == 'refunds' ? 'active' : '' }}">
                                <i class="la la-undo"></i> Refunds
                            </a>
                            <a href="{{ route('admin.patients.card', ['id' => $patientId, 'section' => 'documents']) }}" 
                               class="nav-link {{ $section == 'documents' ? 'active' : '' }}">
                                <i class="la la-file"></i> Documents
                            </a>
                            <a href="{{ route('admin.patients.card', ['id' => $patientId, 'section' => 'activity']) }}" 
                               class="nav-link {{ $section == 'activity' ? 'active' : '' }}">
                                <i class="la la-history"></i> Activity Log
                            </a>
                        </nav>
                    </div>
                </div>
                
                {{-- Main Content --}}
                <div class="col-lg-9 col-md-8">
                    @include('admin.patients.card-v2.sections.' . $section)
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Include modals based on section --}}
@if($section == 'consultations')
    @include('admin.appointments.appointment-forms.modals')
@endif

@endsection

@push('datatable-js')
    {{-- Section-specific scripts --}}
    @if($section == 'consultations')
        <script>
            // Pass data to JS - patientId triggers patient-specific filtering in datatable.js
            var patientId = {{ $patientId }};
            window.listingPerms = {
                contact: @json(Gate::allows('contact')),
                canManage: @json(Gate::allows('appointments_consultancy')),
            };
            // Note: 'permissions' is already declared in row-details.js, will be set from API response
            
            // Stub functions - not needed in patient card context but called by datatable.js
            function getUserCity() { /* Not needed - patient card doesn't use city filters */ }
            function getUserCentre() { /* Not needed - patient card doesn't use centre filters */ }
            function setFilters() { /* Not needed - patient card doesn't use filters */ }
            function applyFilters() { /* Not needed */ }
            function resetFilters() { /* Not needed */ }
            function resetAllFilters() { /* Not needed */ }
        </script>
        {{-- Use the SAME JS files as main consultations module --}}
        <script src="{{ asset('assets/js/pages/appointment/consultation-columns.js') }}"></script>
        <script src="{{ asset('assets/js/pages/appointment/consultation-common.js') }}"></script>
        <script src="{{ asset('assets/js/pages/appointment/datatable.js') }}"></script>
        <script src="{{ asset('assets/js/pages/appointment/invoice.js') }}"></script>
        <script src="{{ asset('assets/js/pages/appointment/common.js') }}"></script>
        {{-- Form validation for edit modal --}}
        <script src="{{ asset('assets/js/pages/crud/forms/validation/appointment/validation.js') }}"></script>
    @endif
    
    @if($section == 'treatments')
        <script>
            // Pass data to JS - patientId triggers patient-specific filtering in treatmentDatatable.js
            var patientId = {{ $patientId }};
            window.listingPerms = {
                contact: @json(Gate::allows('contact')),
                canManage: @json(Gate::allows('treatments_services')),
            };
            // Note: 'permissions' is already declared in row-details.js, will be set from API response
            
            // Stub functions - not needed in patient card context but called by treatmentDatatable.js
            function getUserCity() { /* Not needed - patient card doesn't use city filters */ }
            function getUserCentre() { /* Not needed - patient card doesn't use centre filters */ }
            function setFilters() { /* Not needed - patient card doesn't use filters */ }
            function applyFilters() { /* Not needed */ }
            function resetFilters() { /* Not needed */ }
            function resetAllFilters() { /* Not needed */ }
        </script>
        {{-- Use the SAME JS files as main treatments module --}}
        <script src="{{ asset('assets/js/pages/appointment/treatment-columns.js') }}"></script>
        <script src="{{ asset('assets/js/pages/appointment/treatmentDatatable.js') }}"></script>
        <script src="{{ asset('assets/js/pages/appointment/invoice.js') }}"></script>
        <script src="{{ asset('assets/js/pages/appointment/common.js') }}"></script>
        {{-- Form validation for edit modal --}}
        <script src="{{ asset('assets/js/pages/crud/forms/validation/appointment/validation.js') }}"></script>
    @endif
    
    @if($section == 'plans')
        <script>
            // Pass data to JS - these variables trigger patient-specific filtering in create-plan.js
            window.isPatientCardContext = true;
            window.patientCardPatientId = {{ $patientId }};
            // Note: 'permissions' is already declared in row-details.js, will be set from API response
            
            // Stub functions - not needed in patient card context but called by create-plan.js
            function getUserCity() { /* Not needed - patient card doesn't use city filters */ }
            function getUserCentre() { /* Not needed - patient card doesn't use centre filters */ }
            function setFilters() { /* Not needed - patient card doesn't use filters */ }
            function applyFilters() { /* Not needed */ }
            function resetFilters() { /* Not needed */ }
            function resetAllFilters() { /* Not needed */ }
            
            // Set plansDatatable reference after datatable is initialized
            jQuery(document).ready(function() {
                setTimeout(function() {
                    if (typeof datatable !== 'undefined') {
                        window.plansDatatable = datatable;
                    }
                }, 500);
            });
        </script>
        {{-- Use the SAME JS files as main plans module --}}
        <script src="{{ asset('assets/js/pages/appointments/referred-by-patient-search.js') }}"></script>
        <script src="{{ asset('assets/js/pages/patients/plan-form.js') }}"></script>
        <script src="{{ asset('assets/js/pages/admin_settings/create-plan.js') }}?v={{ filemtime(public_path('assets/js/pages/admin_settings/create-plan.js')) }}"></script>
        <script src="{{ asset('assets/js/pages/admin_settings/create-bundle.js') }}"></script>
        <script src="{{ asset('assets/js/pages/admin_settings/edit-bundle.js') }}"></script>
        <script src="{{ asset('assets/js/pages/admin_settings/create-membership.js') }}"></script>
        <script src="{{ asset('assets/js/pages/crud/forms/validation/admin_settings/refunds.js') }}"></script>
    @endif
    
    @if($section == 'invoices')
        <script>
            // Pass data to JS - patientCardID is used by invoice-form.js
            var patientCardID = {{ $patientId }};
            // patientDatatable object to store datatable instances
            var patientDatatable = {};
            
            // Stub functions
            function getUserCity() { /* Not needed */ }
            function getUserCentre() { /* Not needed */ }
            function setFilters() { /* Not needed */ }
            function applyFilters() { /* Not needed */ }
            function resetFilters() { /* Not needed */ }
            function resetAllFilters() { /* Not needed */ }
        </script>
        {{-- Use patient card invoice JS --}}
        <script src="{{ asset('assets/js/pages/patients/invoice-form.js') }}"></script>
        {{-- Datatable initialization --}}
        <script src="{{ asset('assets/js/pages/crud/ktdatatable/advanced/row-details.js') }}"></script>
        <script>
            // Initialize invoices datatable with correct selector
            jQuery(document).ready(function() {
                if (typeof KTPatientDatatable !== 'undefined' && typeof table_url !== 'undefined') {
                    KTPatientDatatable.init('.invoice-form');
                }
            });
        </script>
    @endif
    
    @if($section == 'refunds')
        <script>
            // Pass data to JS - patientCardID is used by refund-form.js
            var patientCardID = {{ $patientId }};
            // patientDatatable object to store datatable instances
            var patientDatatable = {};
            
            // Stub functions
            function getUserCity() { /* Not needed */ }
            function getUserCentre() { /* Not needed */ }
            function setFilters() { /* Not needed */ }
            function applyFilters() { /* Not needed */ }
            function resetFilters() { /* Not needed */ }
            function resetAllFilters() { /* Not needed */ }
        </script>
        {{-- Use patient card refund JS --}}
        <script src="{{ asset('assets/js/pages/patients/refund-form.js') }}"></script>
        {{-- Datatable initialization --}}
        <script src="{{ asset('assets/js/pages/crud/ktdatatable/advanced/row-details.js') }}"></script>
        <script>
            // Initialize refunds datatable with correct selector
            jQuery(document).ready(function() {
                if (typeof KTPatientDatatable !== 'undefined' && typeof table_url !== 'undefined') {
                    KTPatientDatatable.init('.refund-form');
                }
            });
        </script>
    @endif
    
    @if($section == 'documents')
        <script>
            // Pass data to JS - patientCardID is used by document-form.js
            var patientCardID = {{ $patientId }};
            // patientDatatable object to store datatable instances
            var patientDatatable = {};
            
            // Stub functions
            function getUserCity() { /* Not needed */ }
            function getUserCentre() { /* Not needed */ }
            function setFilters() { /* Not needed */ }
            function applyFilters() { /* Not needed */ }
            function resetFilters() { /* Not needed */ }
            function resetAllFilters() { /* Not needed */ }
        </script>
        {{-- Use patient card document JS --}}
        <script src="{{ asset('assets/js/pages/patients/document-form.js') }}"></script>
        {{-- Datatable initialization --}}
        <script src="{{ asset('assets/js/pages/crud/ktdatatable/advanced/row-details.js') }}"></script>
        <script>
            // Initialize documents datatable with correct selector
            jQuery(document).ready(function() {
                if (typeof KTPatientDatatable !== 'undefined' && typeof table_url !== 'undefined') {
                    KTPatientDatatable.init('.document-form');
                }
            });
        </script>
    @endif
    
    @if($section == 'activity')
        <script>
            // Pass data to JS - patientCardID is used by history-form.js
            var patientCardID = {{ $patientId }};
        </script>
        {{-- Use patient card history JS --}}
        <script src="{{ asset('assets/js/pages/patients/history-form.js') }}"></script>
        <script>
            // Load activity history on page load
            jQuery(document).ready(function() {
                if (typeof loadPatientHistory === 'function') {
                    loadPatientHistory();
                }
            });
        </script>
    @endif
@endpush

@push('js')
    @if($section == 'consultations')
        <script>
            // Initialize datatable - this runs AFTER row-details.js loads
            jQuery(document).ready(function() {
                if (typeof KTDatatable !== 'undefined' && typeof table_url !== 'undefined') {
                    KTDatatable.init();
                }
            });
        </script>
    @endif
    
    @if($section == 'treatments')
        <script>
            // Initialize datatable - this runs AFTER row-details.js loads
            jQuery(document).ready(function() {
                if (typeof KTDatatable !== 'undefined' && typeof table_url !== 'undefined') {
                    KTDatatable.init();
                }
            });
        </script>
    @endif
    
    @if($section == 'plans')
        <script>
            // Initialize datatable - this runs AFTER row-details.js loads
            jQuery(document).ready(function() {
                if (typeof KTDatatable !== 'undefined' && typeof table_url !== 'undefined') {
                    KTDatatable.init();
                }
            });
        </script>
    @endif
    
@endpush
