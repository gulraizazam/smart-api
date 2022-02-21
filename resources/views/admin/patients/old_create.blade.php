<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title">@lang('global.app_create')</h4>
</div>
<div class="modal-body">
    <div class="portlet-body form">
        <div class="form-group">
            {!! Form::open(['method' => 'POST', 'id' => 'form-validation', 'route' => ['admin.patients.store']]) !!}
            <div class="form-body">
                <!-- Starts Form Validation Messages -->
            @include('partials.messages')
            <!-- Ends Form Validation Messages -->

                @include('admin.patients.fields')
            </div>
            <div>
                {!! Form::submit(trans('global.app_save'), ['class' => 'btn btn-success']) !!}
            </div>
            {!! Form::close() !!}
        </div>
    </div>
    <script src="{{ url('js/admin/patients/fields.js') }}" type="text/javascript"></script>
</div>
