<div id="cs_field_{{$field_id}}" class="form-group form-md-line-input cf_card cf_field_item update-answer-fields">
    <h3 class="cf-question-headings">{{$title}}</h3>
    <div class="cf_input_option table-responsive">
        <?php $rows = json_decode($value, true); ?>
            <table>
                <thead>
                @foreach($options as $option)
                    <th>{{$option["label"]}}</th>
                @endforeach
                </thead>
                <tbody>
                @foreach($rows as $row)
                    <tr>
                        @foreach($row["cols"] as $col)
                            <td><input row ={{$loop->parent->index}} col={{$loop->index}} readonly question="{{$col["question"]}}" value="{{$col["answer"]}}"></td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
    </div>
</div>