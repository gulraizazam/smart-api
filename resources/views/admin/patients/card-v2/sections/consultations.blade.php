{{-- Consultations Section --}}
<div class="section-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Consultations</h4>
        @can('appointments_consultancy')
            <a href="javascript:void(0);" onclick="openNewConsultationWithLocation()" class="btn btn-primary btn-sm">
                <i class="la la-plus"></i> New Consultation
            </a>
        @endcan
    </div>
    
    {{-- Datatable - uses same ID as main module for shared JS --}}
    <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
</div>

{{-- Include ALL modals from main consultations module for true globalization --}}
@include('admin.appointments.appointment-forms.modals')

<script>
function openNewConsultationWithLocation() {
    // Fetch last consultancy location for this patient
    $.ajax({
        url: '{{ route("admin.patients.getLastAppointmentLocation", ["id" => $patientId]) }}',
        type: 'GET',
        data: { appointment_type: 'consultancy' },
        success: function(response) {
            var baseUrl = '{{ route("admin.consultancy.index") }}?tab=consultancy';
            if (response.status && response.data && response.data.location_id) {
                baseUrl += '&location_id=' + response.data.location_id;
            }
            window.location.href = baseUrl;
        },
        error: function() {
            // Fallback - redirect without location_id
            window.location.href = '{{ route("admin.consultancy.index") }}?tab=consultancy';
        }
    });
}
</script>
