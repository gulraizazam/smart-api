<div id="cs_field_{{$field_id}}" class="form-group form-md-line-input cf_card cf_field_item update-answer-fields">
    <h3 class="cf-question-headings">{{$title}}</h3>
    {{--<div class="cf_input_option table-responsive">--}}
        <?php $rows = json_decode($value, true); ?>
            {{--<table width="100%" class="table table-default">--}}
                {{--<thead>--}}
                {{--@foreach($options as $option)--}}
                    {{--<th>{{$option["label"]}}</th>--}}
                {{--@endforeach--}}
                {{--</thead>--}}
                {{--<tbody>--}}
                {{--@foreach($rows as $row)--}}
                    {{--<tr >--}}
                        {{--@foreach($row["cols"] as $col)--}}
                            {{--<td row ={{$loop->parent->index}} col={{$loop->index}}><p>{{$col["answer"]}}</p></td>--}}
                        {{--@endforeach--}}
                    {{--</tr>--}}
                {{--@endforeach--}}
                {{--</tbody>--}}
            {{--</table>--}}
    <div class="wrap-row">
            <div class="row-wrap row-head">
                @foreach($options as $option)
                <div class="col"><p>{{$option["label"]}}</p></div>
                @endforeach
            </div>
            @foreach($rows as $row)
            <div class="row-wrap">
                @foreach($row["cols"] as $col)
                    <div class="col"><p>{{$col["answer"]}}</p></div>
                @endforeach
            </div>
            @endforeach
    </div>
    {{--</div>--}}
</div>