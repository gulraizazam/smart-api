<div id="cs_field_{{$field_id}}"
     class="form-group mt-repeater update-question-fields cf_field ui-state-default cf_field_option_select">
    <span class="cf_field_text_type"> Option List </span>
    <div class="form-group form-md-line-input">
        <input id="question" name="question" type="text" placeholder="Question"
               class="form-control mt-repeater-input-line" value="{{$title}}"/>
    </div>
    <div class="mt-repeater-cell form-group form-md-line-input">
        <div class="cf-q-option-list">
            @foreach($options as $option)
            <div id="field" class="mt-repeater-cell rs-shrt cf-q-option-list-item">
                <label class="optlbl">
                    <input type="text" class="form-control optinput" id="field" name="field[]"
                           placeholder="Short Answer" value="{{$option["label"]}}">
                </label>
                <button class="btn delopt remove-me"><i class="fa fa-close"></i></button>
            </div>
            @endforeach
            <div id="field" class="mt-repeater-cell rs-shrt cf-q-option-list-item">
                <label class="optlbl">
                    <input type="text" class="form-control optinput" id="field" name="field[]"
                           placeholder="Short Answer">
                </label>
            </div>
        </div>
        <button class="btn green mr mt-repeater-add add-more-option">
            Add Option
        </button>
    </div>

    <button class="btn btn-danger del mt-repeater-delete mt-repeater-del-right mt-repeater-btn-inline remove-question-me">
        <i class="fa fa-close"></i>
    </button>
</div>