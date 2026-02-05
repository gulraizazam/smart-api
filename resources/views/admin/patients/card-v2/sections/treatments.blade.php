{{-- Treatments Section --}}
<div class="section-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Treatments</h4>
        @can('treatments_services')
            <a href="javascript:void(0);" onclick="openNewTreatmentWithLocation()" class="btn btn-primary btn-sm">
                <i class="la la-plus"></i> New Treatment
            </a>
        @endcan
    </div>
    
    {{-- Datatable - uses same ID as main module for shared JS --}}
    <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
</div>

{{-- Include ALL modals from main treatments module for true globalization --}}
@include('admin.appointments.appointment-forms.modals')

<script>
function openNewTreatmentWithLocation() {
    // Fetch last treatment location for this patient
    $.ajax({
        url: '{{ route("admin.patients.getLastAppointmentLocation", ["id" => $patientId]) }}',
        type: 'GET',
        data: { appointment_type: 'treatment' },
        success: function(response) {
            var baseUrl = '{{ route("admin.treatment.index") }}?patient_id={{ $patientId }}&tab=treatment';
            if (response.status && response.data && response.data.location_id) {
                baseUrl += '&location_id=' + response.data.location_id;
            }
            window.location.href = baseUrl;
        },
        error: function() {
            // Fallback - redirect without location_id
            window.location.href = '{{ route("admin.treatment.index") }}?patient_id={{ $patientId }}&tab=treatment';
        }
    });
}
</script>
