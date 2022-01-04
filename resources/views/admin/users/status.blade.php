@if($user->active)
 @if(Gate::allows('users_active'))

        <form class="" id="active-{{$user->id}}" method="post" action="{{route('admin.users.inactive', $user->id)}}" style="display: inline-block;">
           @csrf

            @method('patch')
            <button onclick="updateStatus('active-{{$user->id}}');" class="btn btn-sm btn-primary" type="button">Active</button>
        </form>
    @else
        <span><span class="label label-lg font-weight-bold label-light-success label-inline">{{ 'Active' }} </span></span>
    @endif

@else
   @if(Gate::allows('users_inactive'))
        <form class="" id="inactive-{{$user->id}}" method="post" action="{{route('admin.users.active', $user->id)}}" style="display: inline-block;">
            @csrf
            @method('patch')
            <button onclick="updateStatus('inactive-{{$user->id}}');" class="btn btn-sm btn-warning" type="button">Inactive</button>
        </form>

    @else
        <span><span class="label label-lg font-weight-bold label-light-danger label-inline">{{ 'Inactive' }} </span> </span>
    @endif
@endif
