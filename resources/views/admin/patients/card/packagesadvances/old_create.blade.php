<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
    <h4 class="modal-title">@lang('global.app_create')</h4>
</div>
<div class="modal-body">
    <div class="portlet-body form">
        <div class="form-group">
            <div class="form-body">
                <!-- Starts Form Validation Messages -->
            @include('partials.messages')
            <!-- Ends Form Validation Messages -->
                @include('admin.patients.card.packagesadvances.create_fields')
            </div>
        </div>
    </div>
    <script src="{{ url('js/admin/patients/card/packagesadvances/create.js') }}" type="text/javascript"></script>
</div>
