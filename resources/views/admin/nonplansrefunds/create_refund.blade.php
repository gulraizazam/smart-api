<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title">@lang('global.app_create')</h4>
</div>
<div class="modal-body">
    <div class="portlet-body form">
        @if($refundable_amount != 0)
            <div class="form-group">
                {!! Form::open(['method' => 'POST', 'id' => 'form-validation', 'route' => ['admin.nonplansrefunds.store']]) !!}
                <div class="form-body">
                    <!-- Starts Form Validation Messages -->
                @include('partials.messages')
                <!-- Ends Form Validation Messages -->

                    @include('admin.nonplansrefunds.fields_refunds')
                </div>
                <div>
                    {!! Form::submit(trans('global.app_save'), ['class' => 'btn btn-success']) !!}
                </div>
                {!! Form::close() !!}
            </div>
        @else
            <h2>Insufficient amount to refund</h2>
        @endif
    </div>
    <script src="{{ url('js/admin/nonplansrefunds/fields.js') }}" type="text/javascript"></script>
</div>
