<div id="' + field_id + '"
     class="form-group mt-repeater update-question-fields cf_field ui-state-default cf_field_option_select">
    <span class="cf_field_text_type"> Option List </span>
    <div class="form-group form-md-line-input">
        <input id="question" name="question" type="text" placeholder="Dropdown Type Field"
               class="form-control mt-repeater-input-line"/>
    </div>
    <div class="mt-repeater-cell form-group form-md-line-input">
        <div class="cf-q-option-list">
            @include('admin.custom_forms.field_template.sub.option_select')
        </div>
        <button class="btn green mr mt-repeater-add add-more-option">
            Add Option
        </button>
    </div>

    <button class="btn btn-danger del mt-repeater-delete mt-repeater-del-right mt-repeater-btn-inline remove-question-me">
        <i class="fa fa-close"></i>
    </button>
</div>