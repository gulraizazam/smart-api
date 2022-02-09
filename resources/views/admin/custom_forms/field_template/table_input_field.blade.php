<div id="' + field_id + '"
     class="form-group mt-repeater update-question-fields cf_field ui-state-default cf_field_multi_select">
    <span class="cf_field_text_type"> Table Input </span>
    <div class="form-group form-md-line-input cf-question-title">
        <input id="question" name="question" type="text" placeholder="Table Name"
               class="form-control mt-repeater-input-line"/>
    </div>
    <div class="form-group form-md-line-input cf-question-title">
        <input id="rows" name="rows" type="number" placeholder="Number of rows"
               class="form-control mt-repeater-input-line"/>
    </div>
    <div class="mt-repeater-cell form-group form-md-line-input">
        <div class="md-checkbox-list cf-q-option-list">
            @include("admin.custom_forms.field_template.sub.table_input")
        </div>
        <button class="btn green mr add-more-table-input">
            Add Column
        </button>
    </div>
    <button
            class="btn btn-danger del mt-repeater-delete mt-repeater-del-right mt-repeater-btn-inline remove-question-me">
        <i class="fa fa-close"></i>
    </button>
</div>
