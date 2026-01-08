<div class="form-group row">

    <div class="col-md-6 mb-5">
        <strong class="mr-10">Name:</strong>
        <span id="patient_name"></span>
    </div>
    <div class="col-md-6 mb-5">
        <strong class="mr-10">Patient ID:</strong>
        <span id="patient_id"></span>
    </div>

    <div class="col-md-6 mb-5">
        <strong class="mr-10">Phone:</strong>
        <span id="patient_phone"></span>
    </div>
    <div class="col-md-6 mb-5">
        <strong class="mr-10">Email:</strong>
        <span id="patient_email"></span>
    </div>

    <div class="col-md-6 mb-5">
        <strong class="mr-10">Gender:</strong>
        <span id="patient_gender"></span>
    </div>

    <div class="col-md-6 mb-5" id="membership_type_row" style="display: none;">
        <strong class="mr-10" style="white-space: nowrap;">Membership Type:</strong>
        <span id="patient_membership" class="text-primary font-weight-bold" style="margin-left:10px;"></span>
    </div>

    <div class="col-md-6 mb-5" id="membership_expiry_row" style="display: none;">
        <strong class="mr-10" style="white-space: nowrap;">Membership Expiry:</strong>
        <span id="patient_membership_expiry" style="margin-left:15px;"></span>
    </div>

    @can('patients_edit')
    <div class="col-12 text-right mt-3">
        <button type="button" class="btn btn-primary profile-edit-btn" id="edit-patient-profile-btn" onclick="editPatientFromProfile();">
            <i class="la la-pencil"></i> Edit Patient
        </button>
    </div>
    @endcan

</div>
