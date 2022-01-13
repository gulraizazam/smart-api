<span class="switch switch-icon">
    <label>
        <input type="checkbox" checked="checked" name="select">
        <span></span>
    </label>
</span>

@if($town->active)

    @if(Gate::allows('towns_inactive'))
    
        {!! Form::open(array(
        'style' => 'display: inline-block;',
        'method' => 'PATCH',
        'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
        'route' => ['admin.towns.inactive', $town->id])) !!}
        {!! Form::submit(trans('global.app_inactive'), array('class' => 'btn btn-xs btn-warning')) !!}
        {!! Form::close() !!}
    @else
        {{ 'Active' }}
    @endif
@else
    @if(Gate::allows('towns_active'))
        {!! Form::open(array(
        'style' => 'display: inline-block;',
        'method' => 'PATCH',
        'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
        'route' => ['admin.towns.active', $town->id])) !!}
        {!! Form::submit(trans('global.app_active'), array('class' => 'btn btn-xs btn-primary')) !!}
        {!! Form::close() !!}
    @else
        {{ 'Inactive' }}
    @endif
@endif