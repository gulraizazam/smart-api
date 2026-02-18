<div class="modal-content">
    <div class="modal-header">
        <h2 class="fw-bolder">Generate Membership Codes</h2>
        <div class="btn btn-icon btn-sm btn-active-icon-primary" data-dismiss="modal" style="cursor: pointer;">
            <span class="svg-icon svg-icon-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                    <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                </svg>
            </span>
        </div>
    </div>

    <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
        <form id="generate_codes_form" method="POST">
            @csrf
            
            <div class="alert alert-info mb-5">
                <div class="d-flex align-items-center">
                    <i class="la la-info-circle fs-2x me-3"></i>
                    <div>
                        <strong>How it works:</strong>
                        <p class="mb-0">Enter a start and end code range (e.g., CA6001 to CA7000) to generate 1000 codes automatically.</p>
                    </div>
                </div>
            </div>

            <div class="fv-row mb-7">
                <label class="required fw-bold fs-6 mb-2">Membership Type</label>
                <select name="membership_type_id" id="generate_membership_type_id" class="form-control form-control-solid select2" required>
                    <option value="">Select Membership Type</option>
                </select>
                <small class="text-danger"><b id="generate_membership_type_id_error"></b></small>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="fv-row mb-7">
                        <label class="required fw-bold fs-6 mb-2">Start Code</label>
                        <input type="text" name="start_code" id="generate_start_code" class="form-control form-control-solid" placeholder="e.g., CA6001" required />
                        <small class="text-muted">Format: Prefix + Number (e.g., CA6001)</small>
                        <small class="text-danger d-block"><b id="generate_start_code_error"></b></small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="fv-row mb-7">
                        <label class="required fw-bold fs-6 mb-2">End Code</label>
                        <input type="text" name="end_code" id="generate_end_code" class="form-control form-control-solid" placeholder="e.g., CA7000" required />
                        <small class="text-muted">Must have same prefix as start code</small>
                        <small class="text-danger d-block"><b id="generate_end_code_error"></b></small>
                    </div>
                </div>
            </div>

            <div id="preview_section" class="alert alert-light-primary mb-7" style="display: none;">
                <div class="d-flex align-items-center">
                    <i class="la la-check-circle fs-2x text-primary me-3"></i>
                    <div>
                        <strong>Preview:</strong>
                        <p class="mb-0" id="preview_text"></p>
                        <div class="mt-2">
                            <strong>Sample codes:</strong>
                            <div id="sample_codes" class="mt-1"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning" style="display: none;" id="warning_section">
                <i class="la la-warning"></i>
                <span id="warning_text"></span>
            </div>

            <div class="text-center pt-5">
                <button type="button" class="btn btn-light me-3" data-dismiss="modal">Cancel</button>
                <button type="button" id="preview_codes_btn" class="btn btn-secondary me-3">
                    <i class="la la-eye"></i>
                    Preview
                </button>
                <button type="submit" id="generate_codes_btn" class="btn btn-primary" disabled>
                    <i class="la la-plus"></i>
                    Generate Codes
                </button>
            </div>
        </form>
    </div>
</div>

@push('js')
<script>
$(document).ready(function() {
    // Load membership types
    function loadMembershipTypes() {
        $.ajax({
            url: '{{ route("admin.membershiptypes.datatable") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                pagination: {
                    page: 1,
                    perpage: 100
                }
            },
            success: function(response) {
                if (response.data) {
                    var options = '<option value="">Select Membership Type</option>';
                    response.data.forEach(function(type) {
                        if (type.active == 1 && !type.parent_id) {
                            options += '<option value="' + type.id + '">' + type.name + '</option>';
                        }
                    });
                    $('#generate_membership_type_id').html(options);
                }
            },
            error: function(xhr) {
                console.error('Failed to load membership types:', xhr);
                toastr.error('Failed to load membership types');
            }
        });
    }

    // Preview codes
    $('#preview_codes_btn').click(function() {
        var startCode = $('#generate_start_code').val().trim();
        var endCode = $('#generate_end_code').val().trim();

        if (!startCode || !endCode) {
            toastr.error('Please enter both start and end codes');
            return;
        }

        $.ajax({
            url: '{{ route("admin.membership-codes.preview") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                start_code: startCode,
                end_code: endCode
            },
            success: function(response) {
                if (response.status) {
                    $('#preview_text').text('Will generate ' + response.data.count + ' codes');
                    
                    var sampleHtml = '<code class="me-2">' + response.data.sample_codes.join('</code> <code class="me-2">') + '</code>';
                    $('#sample_codes').html(sampleHtml);
                    
                    $('#preview_section').show();
                    $('#generate_codes_btn').prop('disabled', false);
                    
                    if (response.data.count > 5000) {
                        $('#warning_text').text('Generating ' + response.data.count + ' codes may take a while.');
                        $('#warning_section').show();
                    } else {
                        $('#warning_section').hide();
                    }
                } else {
                    toastr.error(response.message);
                    $('#preview_section').hide();
                    $('#generate_codes_btn').prop('disabled', true);
                }
            },
            error: function() {
                toastr.error('Failed to preview codes');
            }
        });
    });

    // Generate codes form submission
    $('#generate_codes_form').submit(function(e) {
        e.preventDefault();
        
        var membershipTypeId = $('#generate_membership_type_id').val();
        var startCode = $('#generate_start_code').val().trim();
        var endCode = $('#generate_end_code').val().trim();

        if (!membershipTypeId || !startCode || !endCode) {
            toastr.error('Please fill all required fields');
            return;
        }

        var btn = $('#generate_codes_btn');
        var originalText = btn.html();
        btn.html('<i class="la la-spinner la-spin"></i> Please wait...').prop('disabled', true);

        $.ajax({
            url: '{{ route("admin.membership-codes.generate") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                membership_type_id: membershipTypeId,
                start_code: startCode,
                end_code: endCode
            },
            success: function(response) {
                btn.html(originalText).prop('disabled', false);
                
                if (response.status) {
                    toastr.success(response.message);
                    $('#modal_generate_codes').modal('hide');
                    $('#generate_codes_form')[0].reset();
                    $('#preview_section').hide();
                    $('#generate_codes_btn').html('<i class="la la-plus"></i> Generate Codes').prop('disabled', true);
                    
                    // Reload datatable if exists
                    if (typeof KTDatatable !== 'undefined') {
                        KTDatatable.reload();
                    }
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                btn.html(originalText).prop('disabled', false);
                var message = 'Failed to generate codes';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                toastr.error(message);
            }
        });
    });

    // Reset form when modal is closed
    $('#modal_generate_codes').on('hidden.bs.modal', function() {
        $('#generate_codes_form')[0].reset();
        $('#preview_section').hide();
        $('#warning_section').hide();
        $('#generate_codes_btn').html('<i class="la la-plus"></i> Generate Codes').prop('disabled', true);
        $('.text-danger b').text('');
    });

    // Load membership types when modal opens
    $('#modal_generate_codes').on('shown.bs.modal', function() {
        loadMembershipTypes();
    });
});
</script>
@endpush
