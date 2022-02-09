<div id="' + field_id + '"
     class="form-group mt-repeater update-question-fields cf_field ui-state-default cf_field_single_select">

    <span class="cf_field_text_type"> Single Select </span>
    <div class="form-group form-md-line-input cf-question-title">
        <input id="question" name="question" type="text" placeholder="MCQ Field"
               class="form-control mt-repeater-input-line"/>
    </div>
    <div class="mt-repeater-cell form-group form-md-line-input">
        <div class="form-group form-md-radios mt-repeater">
            <div class="md-radio-list cf-q-option-list">
                @include("admin.custom_forms.field_template.sub.single_select")
            </div>
            <button class="btn green mr mt-repeater-add add-more-radio">
                Add Option
            </button>
        </div>
    </div>

    <button class="btn btn-danger del mt-repeater-delete mt-repeater-del-right mt-repeater-btn-inline remove-question-me">
        <i class="fa fa-close"></i>
    </button>
</div>