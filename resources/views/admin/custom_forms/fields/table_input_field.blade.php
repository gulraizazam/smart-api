<div id="cs_field_{{$field_id}}"
     class="form-group mt-repeater update-question-fields cf_field ui-state-default cf_field_multi_select">
    <span class="cf_field_text_type"> Table Input </span>
    <div class="form-group form-md-line-input cf-question-title">
        <input id="question" name="question" type="text" placeholder="Question"
               class="form-control mt-repeater-input-line" value="{{$title}}"/>
    </div>
    <div class="form-group form-md-line-input cf-question-title">
        <input id="rows" name="rows" type="number" placeholder="Number of rows" value="{{$rows}}"
               class="form-control mt-repeater-input-line"/>
    </div>
    <div class="mt-repeater-cell form-group form-md-line-input">
        <div class="md-checkbox-list cf-q-option-list">
            @foreach($options as $option)
            <div id="field" class="md-checkbox mt-repeater-cell cf-q-option-list-item">
                <label>
                    <span></span>
                    <span class="check"></span>
                    <span style="top:6px"><i class="fa fa-columns"></i></span>
                    <input id="field1" name="field[]" type="text" class="form-control" placeholder="Column Name"
                           value="{{$option["label"]}}">
                </label>
                <button class="btn delopt remove-me"><i class="fa fa-close"></i></button>
            </div>
            @endforeach
            <div id="field" class="md-checkbox mt-repeater-cell cf-q-option-list-item">
                <label>
                    <span></span>
                    <span class="check"></span>
                    <span style="top:6px"><i class="fa fa-columns"></i></span>
                    <input id="field1" name="field[]" type="text" class="form-control" placeholder="Column Name">
                </label>
            </div>
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
