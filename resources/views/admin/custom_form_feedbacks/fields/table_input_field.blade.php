<div id="cs_field_{{$field_id}}" class="form-group form-md-line-input cf_card cf_field_item">
    <h3 class="cf-question-headings">{{$title}}</h3>
    <input id="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}"
           name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}" type="hidden"
           value="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_TABLE_INPUT}}">
    <div class="cf_input_option table-responsive">
        <table>
        <thead>
        @foreach($options as $option)
        <th>{{$option["label"]}}</th>
        @endforeach
        </thead>
        <tbody>
        @for($i=0; $i< $rows; $i++)
        <tr>
            @foreach($options as $option)
                <td><input row ={{$i}} col={{$loop->index}}  id="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}"
                            name="{{config("constants.custom_form.default_field_name.table_input")}}_{{$index}}" question="{{$option["label"]}}"></td>
            @endforeach
        </tr>
        @endfor
        </tbody>
        </table>
    </div>
</div>