<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title"><strong>@lang('global.app_edit')</strong></h4>
</div>
<div class="modal-body">
    <div class="portlet-body form">
        <div class="form-group">
            {!! Form::model($resourceRota, ['method' => 'PUT', 'id' => 'form-validation', 'route' => ['admin.resourcerotas.update', $resourceRota->id]]) !!}
            <div class="form-body">
                <!-- Starts Form Validation Messages -->
            @include('partials.messages')
            <!-- Ends Form Validation Messages -->

                @include('admin.resourcerotas.fields_update')
            </div>
            <div>
                {!! Form::submit(trans('global.app_update'), ['class' => 'btn btn-success']) !!}
            </div>
            {!! Form::close() !!}
        </div>
        <script src="{{ url('js/admin/resourcerotas/fields.js') }}" type="text/javascript"></script>
        <script src="{{ url('metronic/assets/global/scripts/app.min.js') }}" type="text/javascript"></script>
        <script src="{{url('js/admin/resourcerotas/update.js')}}" type="text/javascript"></script>
    </div>
</div>


