{{-- Treatments Section --}}
<div class="section-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Treatments</h4>
        @can('treatments_services')
            <a href="{{ route('admin.treatment.index') }}?patient_id={{ $patientId }}" class="btn btn-primary btn-sm">
                <i class="la la-plus"></i> New Treatment
            </a>
        @endcan
    </div>
    
    {{-- Datatable - uses same ID as main module for shared JS --}}
    <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
</div>

{{-- Include ALL modals from main treatments module for true globalization --}}
@include('admin.appointments.appointment-forms.modals')
