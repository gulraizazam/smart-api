<div id="cs_field_{{$field_id}}"
     class="form-group mt-repeater update-question-fields cf_field ui-state-default cf_field_single_select">

    <span class="cf_field_text_type"> Single Select </span>
    <div class="form-group form-md-line-input cf-question-title">
        <input id="question" name="question" type="text" placeholder="Question" value="{{$title}}"
               class="form-control mt-repeater-input-line"/>
    </div>
    <div class="mt-repeater-cell form-group form-md-line-input">
        <div class="form-group form-md-radios mt-repeater">
            <div class="md-radio-list cf-q-option-list">
                @foreach($options as $option)
                <div class="md-radio mt-repeater-cell cf-q-option-list-item">
                    <label>
                        <span></span>
                        <span class="check"></span>
                        <span class="box"></span><input id="field" type="text" class="form-control" name="field[]"
                                                        placeholder=" Option" value="{{$option["label"]}}">
                    </label>
                    <button class="btn delopt remove-me"><i class="fa fa-close"></i></button>
                </div>
                @endforeach
                <div class="md-radio mt-repeater-cell cf-q-option-list-item">
                    <label>
                        <span></span>
                        <span class="check"></span>
                        <span class="box"></span> <input id="field" type="text" class="form-control" name="field[]"
                                                         placeholder=" Option">
                    </label>
                </div>
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