{{-- Profile Section --}}
<div class="section-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Personal Information</h4>
        @if($permissions['edit'])
            <button type="button" class="btn btn-primary btn-sm" onclick="editPatientProfile('{{ route('admin.patients.edit', ['id' => $patientId]) }}', {{ $patientId }})">
                <i class="la la-pencil"></i> Edit
            </button>
        @endif
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <table class="table table-borderless">
                <tr>
                    <td class="text-muted" width="40%">Patient ID</td>
                    <td><strong>C-{{ $patient->id }}</strong></td>
                </tr>
                <tr>
                    <td class="text-muted">Name</td>
                    <td>{{ $patient->name }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Email</td>
                    <td>{{ $patient->email ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Phone</td>
                    <td>
                        @if($permissions['contact'])
                            {{ $patient->phone ?? '-' }}
                        @else
                            ***********
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Gender</td>
                    <td>
                        @if($patient->gender == 1)
                            Male
                        @elseif($patient->gender == 2)
                            Female
                        @else
                            -
                        @endif
                    </td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table table-borderless">
                <tr>
                    <td class="text-muted" width="40%">Membership</td>
                    <td>
                        @if($membership && $membership->active==1)
                            @php
                                $membershipName = $membership->membershipType->name ?? 'Active';
                                $isGold =  $membership->membershipType->name === 'Gold Membership';
                            @endphp
                            <span class="badge" style="background-color: {{ $isGold ? '#B8860B' : '#3699FF' }}; color: #fff;">{{ $membershipName }}</span>
                            <small class="text-muted d-block">ID: {{ $membership->code }}</small>
                            @if($membership->end_date)
                                <small class="text-muted">Exp: {{ \Carbon\Carbon::parse($membership->end_date)->format('M d, Y') }}</small>
                            @endif
                        @elseif($membership && $membership->active==0)
                            @php
                                $membershipName = $membership->membershipType->name ?? 'Membership';
                            @endphp
                            <span class="badge badge-secondary">{{ $membershipName }} - Expired</span>
                            <small class="text-muted d-block">ID: {{ $membership->code }}</small>
                            @if($membership->end_date)
                                <small class="text-muted">Expired On: {{ \Carbon\Carbon::parse($membership->end_date)->format('M d, Y') }}</small>
                            @endif
                        @else
                            <span class="text-muted"> - </span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Status</td>
                    <td>
                        @if($patient->active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-danger">Inactive</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Created At</td>
                    <td>{{ $patient->created_at ? \Carbon\Carbon::parse($patient->created_at)->format('M d, Y h:i A') : '-' }}</td>
                </tr>
            </table>
        </div>
    </div>
</div>

{{-- Quick Stats --}}
<div class="row mt-4">
    <div class="col-md-3">
        <a href="{{ route('admin.patients.card', ['id' => $patientId, 'section' => 'consultations']) }}" class="text-decoration-none">
            <div class="section-card text-center" style="cursor: pointer;">
                <h3 class="text-primary mb-1" id="stat-consultations">-</h3>
                <span class="text-muted">Consultations</span>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('admin.patients.card', ['id' => $patientId, 'section' => 'treatments']) }}" class="text-decoration-none">
            <div class="section-card text-center" style="cursor: pointer;">
                <h3 class="text-success mb-1" id="stat-treatments">-</h3>
                <span class="text-muted">Treatments</span>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('admin.patients.card', ['id' => $patientId, 'section' => 'plans']) }}" class="text-decoration-none">
            <div class="section-card text-center" style="cursor: pointer;">
                <h3 class="text-warning mb-1" id="stat-plans">-</h3>
                <span class="text-muted">Plans</span>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('admin.patients.card', ['id' => $patientId, 'section' => 'invoices']) }}" class="text-decoration-none">
            <div class="section-card text-center" style="cursor: pointer;">
                <h3 class="text-info mb-1" id="stat-invoices">-</h3>
                <span class="text-muted">Invoices</span>
            </div>
        </a>
    </div>
</div>

{{-- Edit Patient Modal --}}
<div class="modal fade" id="modal_edit_patients" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered form-popup" id="edit_patients">
        @include('admin.patients.edit')
    </div>
</div>

@push('js')
<script>
    // Load stats
    $(document).ready(function() {
        $.ajax({
            url: '{{ route("admin.patients.tabCounts", ["id" => $patientId]) }}',
            type: 'GET',
            success: function(response) {
                if (response.status && response.data) {
                    $('#stat-consultations').text(response.data.consultations || 0);
                    $('#stat-treatments').text(response.data.treatments || 0);
                    $('#stat-plans').text(response.data.plans || 0);
                    $('#stat-invoices').text(response.data.invoices || 0);
                }
            }
        });
    });
    
    // Edit patient profile function
    function editPatientProfile(url, id) {
        $("#modal_edit_patients").modal("show");
        $("#modal_edit_patients_form").attr("action", route('admin.patients.update', { id: id }));

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: url,
            type: "GET",
            cache: false,
            success: function (response) {
                setEditPatientData(response);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                toastr.error('Failed to load patient data');
            }
        });
    }
    
    // Set edit data for patient
    function setEditPatientData(response) {
        let genders = response.data.gender;
        let patient = response.data.patient;

        let gender_option = '<option value="">Select Gender</option>';
        Object.entries(genders).forEach(function (gender) {
            gender_option += '<option value="' + gender[0] + '">' + gender[1] + '</option>';
        });

        $("#edit_gender_id").html(gender_option);
        $("#edit_name").val(patient.name);
        $("#edit_email").val(patient.email);
        $("#edit_old_phone").val(patient.phone);

        @if($permissions['contact'])
            $("#edit_phone").val(patient.phone);
        @else
            $("#edit_phone").val("***********").attr("readonly", true);
        @endif

        $("#edit_gender_id").val(patient.gender);
    }
    
    // Handle form submission via AJAX
    $(document).on('submit', '#modal_edit_patients_form', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var url = form.attr('action');
        var formData = form.serialize();
        
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: url,
            type: 'PUT',
            data: formData,
            success: function(response) {
                if (response.status) {
                    toastr.success(response.message || 'Patient updated successfully');
                    $("#modal_edit_patients").modal("hide");
                    // Reload page to show updated data
                    location.reload();
                } else {
                    toastr.error(response.message || 'Failed to update patient');
                }
            },
            error: function(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    Object.keys(errors).forEach(function(key) {
                        toastr.error(errors[key][0]);
                    });
                } else {
                    toastr.error('Failed to update patient');
                }
            }
        });
    });
</script>
@endpush
