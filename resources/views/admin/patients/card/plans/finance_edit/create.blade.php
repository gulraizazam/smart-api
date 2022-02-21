<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true" id="closeBtn"></button>
    <h4 class="modal-title">@lang('global.app_edit')</h4>
</div>
<div class="modal-body">
    <div class="portlet-body form">
        <div class="form-group">
            {!! Form::open(['method' => 'PUT', 'id' => 'finance-form-validation', 'route' => ['admin.packages.edit_cash.store']]) !!}
            <div class="form-body">
                <!-- Starts Form Validation Messages -->
                <div class="alert alert-danger display-hide" id="alert-danger">
                    <button class="close" data-close="alert"></button>
                    Please check below.
                </div>
                <div class="alert alert-success display-hide" id="alert-success">
                    <button class="close" data-close="alert"></button>
                    Form is being submit!
                </div>
                <!-- Ends Form Validation Messages -->
                @include('admin.patients.card.plans.finance_edit.fields')
            </div>
            <div id="modal-footer">
                <button type="button" class="btn default" data-dismiss="modal">Close</button>
                {!! Form::submit(trans('global.app_save'), ['id' => 'finance_save_btn', 'class' => 'btn btn-success']) !!}
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>
<script src="{{ url('js/admin/packages/finance/finance_edit.js') }}" type="text/javascript"></script>

