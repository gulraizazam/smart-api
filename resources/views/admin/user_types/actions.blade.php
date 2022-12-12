@if(Gate::allows('user_types_edit'))
    <a href="javascript:void(0);" onclick="editRow('{{$usertype->id}}', '#modal_add_user_type');" class="btn btn-sm btn-primary">
    <span class="navi-icon"><i class="la la-pencil"></i></span>
    <span class="navi-text">Edit</span>
</a>
@endif
