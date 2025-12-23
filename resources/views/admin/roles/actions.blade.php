
@if(Gate::allows('roles_edit') || Gate::allows('roles_duplicate') || Gate::allows('roles_destroy'))
    <div class="dropdown dropdown-inline action-dots">
        <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">
            <i class="ki ki-bold-more-hor" aria-hidden="true"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">
            <ul class="navi flex-column navi-hover py-2">
                <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">
                    Choose an action:
                </li>

                @if(Gate::allows('roles_edit'))
                    <li class="navi-item">
                        <a href="{{ route('admin.roles.edit', $role) }}" class="navi-link">
                            <span class="navi-icon"><i class="la la-pencil"></i></span>
                            <span class="navi-text">Edit</span>
                        </a>
                    </li>
                @endif
                @if(Gate::allows('roles_duplicate'))
                    <li class="navi-item">
                        <a href="{{ route('admin.roles.duplicate', $role) }}" class="navi-link">
                            <span class="navi-icon"><i class="la la-copy"></i></span>
                            <span class="navi-text">Duplicate</span>
                        </a>
                    </li>
                @endif
                @if(Gate::allows('roles_destroy'))
                    <li class="navi-item">
                        <a href="javascript:void(0);" onclick="deleteRow('{{$role->id}}');" class="navi-link">
                            <span class="navi-icon"><i class="la la-trash"></i></span>
                            <span class="navi-text">Delete</span>
                        </a>
                        <form id="delete-row-form-{{$role->id}}" action="{{route('admin.roles.destroy', $role)}}" method="post">
                            @csrf
                            <input type="hidden" name="_method" value="delete">
                        </form>
                    </li>
                @endif

            </ul>
        </div>
    </div>
@endif
