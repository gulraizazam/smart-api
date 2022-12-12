@if(Gate::allows('permissions_edit') || Gate::allows('permissions_destroy'))
    <div class="dropdown dropdown-inline action-dots">
    <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">
        <i class="ki ki-bold-more-hor" aria-hidden="true"></i>
        </a>
    <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">
        <ul class="navi flex-column navi-hover py-2">
            <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">
                Choose an action:
            </li>

                @if(Gate::allows('permissions_edit'))
                    <li class="navi-item">
                        <a href="javascript:void(0);" onclick="editRow('{{$permission->id}}', '#modal_add_permission');" class="navi-link">
                            <span class="navi-icon"><i class="la la-pencil"></i></span>
                            <span class="navi-text">Edit</span>
                        </a>
                    </li>
                @endif
                @if(Gate::allows('permissions_destroy'))
                <li class="navi-item">
                    <a href="javascript:void(0);" onclick="deleteRow('{{$permission->id}}');" class="navi-link">
                        <span class="navi-icon"><i class="la la-trash"></i></span>
                        <span class="navi-text">Delete</span>
                    </a>
                    <form id="delete-row-form-{{$permission->id}}" action="{{route('admin.permissions.destroy', $permission)}}" method="post">
                        @csrf
                        <input type="hidden" name="_method" value="delete">
                    </form>
                </li>
            @endif

            </ul>
        </div>
    </div>
@endif
