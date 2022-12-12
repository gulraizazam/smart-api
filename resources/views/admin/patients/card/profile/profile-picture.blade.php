<form id="save_profile_image" action="{{route('admin.patients.storeimage')}}" method="post">

    <input type="hidden" name="patient_id" value="{{request('id')}}">

    <div class="form-group row">
        <label class="col-xl-3 col-lg-3 col-form-label">Image</label>
        <div class="col-lg-9 col-xl-6">
            <div class="image-input image-input-outline" id="kt_profile_avatar" style="background-image: url(assets/media/users/blank.png)">
                <div class="image-input-wrapper patient_profile_image" style="background-image: url('{{asset('assets/media/logos/avatar.jpg')}}')"></div>
                <label class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow" data-action="change" data-toggle="tooltip" title="" data-original-title="Change avatar">
                    <i class="fa fa-pen icon-sm text-muted"></i>
                    <input type="file" id="file" name="profile_avatar" accept=".png, .jpg, .jpeg">
                    <input type="hidden" name="profile_avatar_remove">
                </label>
                <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow" data-action="cancel" data-toggle="tooltip" title="" data-original-title="Cancel avatar">
                    <i class="ki ki-bold-close icon-xs text-muted"></i>
                </span>
                <span class="btn btn-xs btn-icon btn-circle btn-white btn-hover-text-primary btn-shadow" data-action="remove" data-toggle="tooltip" title="" data-original-title="Remove avatar">
                    <i class="ki ki-bold-close icon-xs text-muted"></i>
                </span>


            </div>

        </div>
    </div>

    <div class="form-group row">
        <div class="col-md-6">
            <button type="button" onclick="savePatientImage();" class="btn btn-success float-right spinner-button">Save</button>
        </div>
    </div>

</form>
