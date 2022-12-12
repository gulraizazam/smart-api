<style type="text/css">
    .wrap-row-table{
        width: 100%;
        margin-bottom: 20px;
    }
    .wrap-row-table thead > tr > th {
        padding: 9px 14px;
        color: #fff;
        text-align: left;
        text-transform: uppercase;
        background-color: #364150;
        border-top:0px;
        font-size: 11px;
    }
    .wrap-row-table tbody tr {
        background-color: rgba(0, 0, 0, 0.05);
    }
    .wrap-row-table tbody tr:nth-of-type(odd) {
        background-color: #fff;
    }
    .wrap-row-table  tbody > tr > td {
        padding: 6px 14px;
        vertical-align: middle;
        text-align: left;
        font-size: 10px;
        min-width: 150px;
        color: #364150;
    }
    .form-group_tab{
        page-break-before: always;
    }
    @media print {
        .wrap-row-table{
            width: 100%;
            margin-bottom: 20px;
        }
        .wrap-row-table thead > tr > th {
            padding: 9px 14px;
            color: #fff;
            text-align: left;
            text-transform: uppercase;
            background-color: #364150;
            border-top:0px;
            font-size: 11px;
        }
        .wrap-row-table tbody tr {
            background-color: rgba(0, 0, 0, 0.05);
        }
        .wrap-row-table tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.05);
        }
        .wrap-row-table  tbody > tr > td {
            padding: 6px 14px;
            vertical-align: middle;
            text-align: left;
            font-size: 12px;
            min-width: 150px;
            color: #364150;
        }
    }
</style>
<div id="cs_field_{{$field_id}}" class="form-group form-group_tab form-md-line-input cf_card cf_field_item update-answer-fields">
    <h3 class="cf-question-headings" style="padding-bottom:22px;">{{$title}}</h3>

    <table class="wrap-row-table">
            <thead>
                <tr>
                    @foreach($options as $option)
                        <th>{{$option["label"]}}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
            @if (isset($rows))
                @foreach($rows as $row)
                    <tr>
                        @foreach($row["cols"] as $col)
                            <td>{{$col["answer"]}}</td>
                        @endforeach
                    </tr>

                @endforeach
            @endif
            </tbody>
    </table>
</div>
