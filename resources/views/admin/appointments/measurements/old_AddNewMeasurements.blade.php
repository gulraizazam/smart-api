<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title">@lang('global.app_Submit')</h4>
</div>
<div class="modal-body">
    <div class="portlet-body form">
        <div class="form-group">
            <div class="form-body">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($CustomForms as $customform)
                        <tr>
                            <td>{{$customform->name}}</td>
                            <td><a class="btn btn-xs btn-info" href="{{ route('admin.appointmentmeasurement.fill_form',["form_id"=>$customform->id,"appointment_id"=>$id]) }}">@lang('global.app_submit')</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

