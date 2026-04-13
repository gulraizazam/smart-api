<div class="modal fade" id="modal_edit_candidate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Edit Candidate</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-dismiss="modal">
                    <span class="svg-icon svg-icon-1"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/><rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/></svg></span>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 my-5">
                <form id="form_edit_candidate" method="post" action="{{ route('admin.hr.recruitment.update', $candidate) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="required fw-bold fs-6 mb-2">Candidate Name</label>
                            <input type="text" name="name" class="form-control form-control-solid" value="{{ $candidate->name }}" required>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="fw-bold fs-6 mb-2">Email</label>
                            <input type="email" name="email" class="form-control form-control-solid" value="{{ $candidate->email }}">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="fw-bold fs-6 mb-2">Phone</label>
                            <input type="text" name="phone" class="form-control form-control-solid" value="{{ $candidate->phone }}">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="required fw-bold fs-6 mb-2">Designation</label>
                            <select name="designation_id" class="form-control form-control-solid select2" style="width:100%;" required>
                                <option value="">--- Select ---</option>
                                @foreach($designations as $d)
                                    <option value="{{ $d->id }}" {{ $candidate->designation_id == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="required fw-bold fs-6 mb-2">City</label>
                            <select name="city_id" class="form-control form-control-solid select2" style="width:100%;" required>
                                <option value="">--- Select ---</option>
                                @foreach($cities as $c)
                                    <option value="{{ $c->id }}" {{ $candidate->city_id == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="required fw-bold fs-6 mb-2">Branch / Centre</label>
                            <select name="location_id" class="form-control form-control-solid select2" style="width:100%;" required>
                                <option value="">--- Select ---</option>
                                @foreach($locations as $loc)
                                    <option value="{{ $loc->id }}" {{ $candidate->location_id == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="fw-bold fs-6 mb-2">Replace CV</label>
                            <input type="file" name="cv" class="form-control form-control-solid" accept=".pdf,.doc,.docx">
                            <small class="text-muted">PDF, DOC, DOCX. Max 5MB. {{ $candidate->cv_file_path ? 'Leave empty to keep current CV.' : '' }}</small>
                        </div>
                        <div class="col-md-12 mb-4">
                            <label class="fw-bold fs-6 mb-2">Notes</label>
                            <textarea name="notes" class="form-control form-control-solid" rows="3">{{ $candidate->notes }}</textarea>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <button type="reset" class="btn btn-light me-3" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
