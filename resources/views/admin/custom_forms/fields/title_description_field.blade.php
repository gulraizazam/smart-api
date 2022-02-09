<div id="cs_field_{{$field_id}}"
     class="form-group mt-repeater update-question-fields cf_field ui-state-default cf_field_title_description">

    <span class="cf_field_text_type"> Title </span>

    <div class="form-group form-md-line-input">
        <input id="question" name="question" type="text" placeholder="Question"
               class="form-control mt-repeater-input-line" value="{{$title}}"/>
    </div>

    <button class="btn btn-danger del mt-repeater-delete mt-repeater-del-right mt-repeater-btn-inline remove-question-me">
        <i class="fa fa-close"></i>
    </button>
</div>
