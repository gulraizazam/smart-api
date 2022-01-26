
<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title"><strong>@lang('global.app_create')</strong></h4>
</div>
<div class="modal-body">
    <div class="portlet-body form">
        <div class="form-group">
            {!! Form::open(['method' => 'POST', 'id' => 'form-validation', 'route' => ['admin.resourcerotas.store']]) !!}

            <div class="form-body">
                <!-- Starts Form Validation Messages -->
                @include('partials.messages')
                <!-- Ends Form Validation Messages -->
                @include('admin.resourcerotas.fields_create')
            </div>

            <div>
                {!! Form::submit(trans('global.app_save'), ['class' => 'btn btn-success']) !!}
            </div>
            {!! Form::close() !!}
        </div>
        <script src="{{ url('metronic/assets/global/scripts/app.min.js') }}" type="text/javascript"></script>
        <script src="{{url('js/admin/resourcerotas/create.js')}}" type="text/javascript"></script>
        <script src="{{ url('js/admin/resourcerotas/fields.js') }}" type="text/javascript"></script>
        <script type="text/javascript">
            $('.select2').select2({ width: '100%' });
        </script>
    </div>
</div>
