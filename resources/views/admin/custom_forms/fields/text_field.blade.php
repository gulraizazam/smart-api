<div id="cs_field_{{$field_id}}"
     class="form-group mt-repeater update-question-fields cf_field ui-state-default cf_field_text">
     <div class="drag-button text-center"><i style="color: #000;" class="las la-bars"></i></div>
    <span class="cf_field_text_type"> Short Answer </span>
    <div class="form-group form-md-line-input">
        <input id="question" name="question" type="text" placeholder="Question"
               class="form-control mt-repeater-input-line under-border" value="{{$title}}"/>
    </div>
    <button
            class="btn btn-sm btn-danger del mt-repeater-delete mt-repeater-del-right mt-repeater-btn-inline remove-question-me">
            <i class="las la-times"></i>
    </button>
</div>
