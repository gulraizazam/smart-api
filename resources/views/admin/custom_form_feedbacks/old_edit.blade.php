@extends('layouts.app-rs')
<style type="text/css">
    .cf_label {
        width: 100%;
    }

    .cf_input_question {
        font-size: 18px !important;
        color: #000 !important;
    }

    .cf_input_option {
        padding-top: 10px !important;
    }

    .cf_card {
        padding-bottom: 60px;
    }

    .cf_input_option .md-checkbox label > .box {
        width: 15px;
        height: 15px;
    }

    .rs .md-checkbox label > .box {
        top: 10px;
    }

    .rs .form-group.form-md-line-input .form-control {
        border-bottom: 0;
        font-size: 16px;
        display: inline-block;
    }

    .cf_input_option label {
        width: 90%;
    }

    .cf_main_title {
        padding-bottom: 30px;
    }

    .cf_form_internal_title {
        background: purple;
        font-weight: bold;
        font-size: 18px;
        color: #fff;
    }

    .cf-question-headings {
        color: #000;
        font-weight: 500;
    }

    .cf_form_internal_title {
        background: #35a1d4;
        font-weight: bold;
        font-size: 18px;
        color: #fff;
        display: inline-block;
        padding: 10px;
        position: relative;
    }

    h3.cf_form_internal_title:after {
        content: "";
        width: 0;
        height: 0;
        border-left: 3px solid transparent;
        border-right: 20px solid transparent;
        border-top: 39px solid #35a1d4;
        position: absolute;
        right: -20px;
        top: 0px;
    }

    .cf-input-border {
        border-bottom: 1px solid #ddd !important;
    }
</style>
<link href="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css'}}" rel="stylesheet"
      type="text/css"/>
<link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet"
      type="text/css"/>
@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.custom_form_feedbacks.title')</h1>
    <!-- END PAGE TITLE-->
@endsection

@section('content')
    <div class="portlet light bordered">
        <div class="portlet-title">
            <div class="caption font-green-sharp">
                <i class="icon-pencil font-green-sharp"></i>
                <span class="caption-subject bold uppercase"> @lang('global.app_edit')</span>
            </div>
            <div class="actions">
                <a href="{{ route('admin.custom_form_feedbacks.index') }}"
                   class="btn dark pull-right">@lang('global.app_back')</a>
            </div>
        </div>
        <div class="portlet-body rs">
            <h1 style="color: red;">Form is auto saved. Whenever you change</h1>
            <!-- custom form -->
            <div id="cf_form">
                <h2>Patient Name::{{$patient_name->name}}</h2>
                @include('admin.custom_form_feedbacks.edit_fields.select_patient')

                <div class="form-group form-md-line-input cf_main_title">
                    <div class="form-group form-md-line-input cf_input_question">
                        <h1 class="rs-head">{{$custom_form->form_name}}</h1>
                    </div>
                </div>
                <input id="feedback_id" name="feedback_id" type="hidden" value="{{$custom_form->id}}"/>

                <div class="form-group form-md-line-input cf_main_title">
                    <div class="form-group form-md-line-input cf_input_question">
                        <p>{{$custom_form->form_description}}</p>
                    </div>
                </div>

                <div id="cf_field_list">


                    @foreach($custom_form->form_fields as $field)
                        <?php $content = \App\Helpers\CustomFormHelper::getContentArray($field->content); ?>

                        @if($field->field_type ==1)
                            @include("admin.custom_form_feedbacks.edit_fields.text_field", ['field_id'=>$field->id, 'title'=>$content["title"],"value" => $field->field_value])
                        @elseif($field->field_type ==2)
                            @include("admin.custom_form_feedbacks.edit_fields.paragraph_field", ['field_id'=>$field->id, 'title'=>$content["title"], "value" => $field->field_value])
                        @elseif($field->field_type ==3)
                            @include("admin.custom_form_feedbacks.edit_fields.single_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"], "value" => $field->field_value])
                        @elseif($field->field_type ==4 && is_array($content))
                            @include("admin.custom_form_feedbacks.edit_fields.multi_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"], "value" => $field->field_value])
                        @elseif($field->field_type ==7 && is_array($content))
                            @include("admin.custom_form_feedbacks.edit_fields.table_input_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"], "value" => $field->field_value])
                        @elseif($field->field_type ==5 && is_array($content))
                            @include("admin.custom_form_feedbacks.edit_fields.option_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"], "value" => $field->field_value])
                        @elseif($field->field_type ==6 && is_array($content))
                            @include("admin.custom_form_feedbacks.edit_fields.title_description_field", ["field_id"=>$field->id, 'title'=>$content["title"], "value" => $field->field_value])
                        @endif
                    @endforeach
                </div>

            </div>

        </div>
        @stop

        @section('javascript')
            <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}"
                    type="text/javascript"></script>
            <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}"
                    type="text/javascript"></script>
            <script src="{{ url('js/admin/custom_form_feedbacks/fields.js') }}" type="text/javascript"></script>

            <script type="text/javascript">

                function updatePatient(){
                    $(".update_patient_data").bind("change", function () {

                        patient_id = $("select[name=reference_id]").val();
                        console.log("new id: "  + patient_id);
                        if(parseInt(patient_id) > 0 ){
                            update_feedback({'reference_id':patient_id}, (res)=>{

                            },
                                (xhr, ajaxOptions, thrownError)=>{

                                }
                            );
                        }

                    });
                }
                function fieldChangeUpdateBinding() {

                    $(".update-answer-fields").bind("change", function () {

                        field_id = this.id.split("cs_field_")[1];

                        if (field_id != "") {
                            console.log(this.id);
                            field_type = $(this).find("input#field_type[type=hidden]").val();

                            console.log("field_id : " + field_id);
                            console.log("field_type : " + field_type);
                            data = {};
                            if (field_type == 1) {
                                text_answer = this.querySelector("input[name=answer]").value;
                                data["field_value"] = text_answer;
                            } else if (field_type == 2) {
                                text_answer = this.querySelector("textarea[name=answer]").value;
                                data["field_value"] = text_answer;
                            } else if (field_type == 3) {
                                radio_answer = this.querySelector("input[name=field_option]:checked");
                                if (radio_answer) {
                                    radio_answer = radio_answer.value;
                                } else {
                                    radio_answer = "null";
                                }
                                data["field_value"] = radio_answer;
                            }
                            else if (field_type == 4) {
                                checkbox = this.querySelectorAll("input[name=field_option]:checked")
                                if (checkbox.length) {
                                    checkbox_answer = [];
                                    for (let i = 0; i < checkbox.length; i++) {
                                        checkbox_answer[i] = checkbox[i].value;
                                    }
                                    data["field_value"] = JSON.stringify(checkbox_answer);
                                } else {
                                    data["field_value"] = "null";
                                }

                            }
                            else if (field_type == 7) {

                                // options = fields[i].querySelectorAll("table thead th")
                                rows = this.querySelectorAll("table tbody tr");
                                row_data = [];
                                for(let i=0; i< rows.length; i++){
                                    let row = {};
                                    row.order = i;
                                    row.cols = [];
                                    let cols = rows[i].querySelectorAll("input")
                                    for(let j =0; j < cols.length; j++){
                                        let cell = {};
                                        cell.row = cols[j].getAttribute("row");
                                        cell.col = cols[j].getAttribute("col");
                                        cell.question = cols[j].getAttribute("question");
                                        cell.order = j;
                                        cell.answer = cols[j].value;
                                        row.cols.push(cell);
                                    }
                                    row_data.push(row);
                                }

                                if (row_data.length > 0) {
                                    data["field_value"] = JSON.stringify(row_data);
                                } else {
                                    data["field_value"] = "";
                                }

                            }
                            else if (field_type == 5) {
                                selected_value = this.querySelector("select[name=field_option]").value;
                                data["field_value"] = selected_value;
                            } else {
                                data["field_value"] = "";
                            }


                            console.log("data : ");
                            console.log(data);
                            update_form_field(field_id, data, (response) => {
                                console.log(response);

                            }, (xhr, ajaxOptions, thrownError) => {

                            });
                        }
                    });

                }

                function update_form_field(field_id, data, success_callback, error_callback) {

                    $.ajax({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        url: route('admin.custom_form_feedbacks.update_field', {
                            'feedback_id': $("#feedback_id").val(),
                            'feedback_field_id': field_id
                        }),
                        type: 'POST',
                        data: data,
                        cache: false,
                        success: success_callback,
                        error: error_callback
                    });
                }


                function update_feedback(data, success_callback, error_callback){
                    $.ajax({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        url: '{{route('admin.custom_form_feedbacks.update',['Id'=>$custom_form->id])}}',
                        type: 'PUT',
                        data: data,
                        cache: false,
                        success: success_callback,
                        error: error_callback
                    });
                }

                $(document).ready(function () {
                    fieldChangeUpdateBinding();
                    updatePatient();

                    $(".select2").select2({
                        placeholder:"Select Patient"
                    });
                });
            </script>
            <script src="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'}}"
                    type="text/javascript"></script>
            <script src="{{ url('js/admin/users/ajaxbaseselect2.js') }}" type="text/javascript"></script>

@endsection

