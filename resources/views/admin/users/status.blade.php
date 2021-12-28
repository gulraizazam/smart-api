@if($user->active)
    @if(Gate::allows('users_inactive'))
        <form class="" method="post" action="{{route('admin.users.inactive', $user->id)}}" onsubmit="return confirm('Are you sure?');" style="display: inline-block;">
            @method('patch')
            <button class="btn btn-sm btn-warning" type="submit">Inactive</button>
        </form>

    @else
        {{ 'Inactive' }}
    @endif
@else
    @if(Gate::allows('users_active'))

        <form class="" method="post" action="{{route('admin.users.active', $user->id)}}" onsubmit="return confirm('Are you sure?');" style="display: inline-block;">
            @method('patch')
            <button class="btn btn-sm btn-primary" type="submit">Active</button>
        </form>
    @else
        {{ 'Active' }}
    @endif
@endif
