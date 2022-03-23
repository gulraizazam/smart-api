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
        position: relative;
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

    #cf_field_list .form-group > label {
        position: relative;
    }

    #cf_form .form-group label.error {
        position: absolute !important;
        bottom: -36px !important;
        color: red !important;
        top: inherit;
        font-size: 16px !important;
    }

    .cf_input_option_item label.error {
        position: absolute !important;
        top: -18px !important;
        left: -28px !important;
        color: red !important;
        top: inherit;
        font-size: 16px !important;
    }
</style>
<link href="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css'}}" rel="stylesheet"
      type="text/css"/>
<link href="{{ url('metronic/assets/global/plugins/select2/css/select2-bootstrap.min.css') }}" rel="stylesheet"
      type="text/css"/>
<link href="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}"
      rel="stylesheet" type="text/css"/>
@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.app_appointmentmeasurementforms')</h1>
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
                <a href="{{ route('admin.appointmentsmedical.medicals',[$appointmentinformation->id]) }}"
                   class="btn dark pull-right">@lang('global.app_back')</a>
            </div>
        </div>
        <div class="portlet-body rs">
            <!-- custom form -->
            <form id="cf_form">
                <div class="form-group form-md-line-input cf_main_title">
                    <div class="form-group form-md-line-input cf_input_question">
                        <h1 class="rs-head">{{$custom_form->name}}</h1>
                    </div>
                </div>
                <input id="form_id" name="form_id" type="hidden" value="{{$custom_form->id}}"/>
                <div class="form-group form-md-line-input cf_main_title">
                    <div class="form-group form-md-line-input cf_input_question">
                        <p>{{$custom_form->description}}</p>
                    </div>
                </div>

                @include('admin.appointments.medicals.fields.select_patient')

                <div id="cf_field_list">
                    @foreach($custom_form->form_fields as $field)
                        <?php $content = \App\Helpers\CustomFormHelper::getContentArray($field->content); ?>

                        @if($field->field_type ==1)
                            @include("admin.custom_form_feedbacks.fields.text_field", ['field_id'=>$field->id, 'title'=>$content["title"],"index"=>$loop->index])
                        @elseif($field->field_type ==2)
                            @include("admin.custom_form_feedbacks.fields.paragraph_field", ['field_id'=>$field->id, 'title'=>$content["title"],"index"=>$loop->index])
                        @elseif($field->field_type ==3)
                            @include("admin.custom_form_feedbacks.fields.single_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"],"index"=>$loop->index])
                        @elseif($field->field_type ==4 && is_array($content))
                            @include("admin.custom_form_feedbacks.fields.multi_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"],"index"=>$loop->index])
                        @elseif($field->field_type ==5 && is_array($content))
                            @include("admin.custom_form_feedbacks.fields.option_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"],"index"=>$loop->index])
                        @elseif($field->field_type ==6 && is_array($content))
                            @include("admin.custom_form_feedbacks.fields.title_description_field", ["field_id"=>$field->id, 'title'=>$content["title"]])
                        @elseif($field->field_type ==7 && is_array($content))
                            @include("admin.custom_form_feedbacks.fields.table_input_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"],"rows"=>$content["rows"],"index"=>$loop->index])
                        @endif
                    @endforeach
                </div>
                <div class="margin-top-10">
                    <button class="btn green">Save Changes</button>
                    <a href="{{ route('admin.appointmentsmedical.medicals',[$appointmentinformation->id]) }}"
                       class="btn default">Cancel </a>
                </div>
            </form>

        </div>
        @stop

        @section('javascript')
            <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}"
                    type="text/javascript"></script>
            <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}"
                    type="text/javascript"></script>
            <script src="{{ url('js/admin/measurements/fields.js') }}" type="text/javascript"></script>

            <script>

            </script>
            <script type="text/javascript">

                $(function () {


                    $("#cf_form").validate();
                    $("#cf_form").submit(function (e) {

                        if ($("#cf_form").valid()) {
                            e.preventDefault();
                            // data
                            data = {};
                            fields = document.querySelectorAll("#cf_field_list> .cf_field_item");

                            /*Start Here*/
                            data['reference_id'] = document.querySelector("select[name='{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_NAME}}']").value;
                            data['date'] = $("#{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_DATE}}").val();
                            data['appointment_id'] = $("#{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_APPOINTMENT}}").val();

                            for (let i = 0; i < fields.length; i++) {
                                field_type = fields[i].querySelector("input#field_type").value;
                                field_id = fields[i].id.split("cs_field_")[1];

                                if (field_type == "{{config("constants.custom_form.field_types.text")}}") {
                                    text_answer = fields[i].querySelector("input.answer").value;
                                    data[field_id] = text_answer;
                                } else if (field_type == "{{config("constants.custom_form.field_types.paragraph")}}") {
                                    text_answer = fields[i].querySelector("textarea.answer").value;
                                    data[field_id] = text_answer;
                                } else if (field_type == "{{config("constants.custom_form.field_types.single")}}") {
                                    radio_answer = fields[i].querySelector("input.field_option:checked");
                                    if (radio_answer) {
                                        radio_answer = radio_answer.value;
                                    } else {
                                        radio_answer = "";
                                    }
                                    data[field_id] = radio_answer;
                                }
                                else if (field_type == "{{config("constants.custom_form.field_types.multiple")}}") {
                                    checkbox = fields[i].querySelectorAll("input.field_option:checked")

                                    if (checkbox.length) {
                                        checkbox_answer = [];
                                        for (let i = 0; i < checkbox.length; i++) {
                                            checkbox_answer[i] = checkbox[i].value;
                                        }
                                        data[field_id] = JSON.stringify(checkbox_answer);
                                    } else {
                                        data[field_id] = "";
                                    }

                                }
                                else if (field_type == "{{config("constants.custom_form.field_types.table_input")}}") {
                                    // options = fields[i].querySelectorAll("table thead th")
                                    rows = fields[i].querySelectorAll("table tbody tr");
                                    row_data = [];
                                    for (let i = 0; i < rows.length; i++) {
                                        let row = {};
                                        row.order = i;
                                        row.cols = [];
                                        let cols = rows[i].querySelectorAll("input")
                                        for (let j = 0; j < cols.length; j++) {
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
                                        data[field_id] = JSON.stringify(row_data);
                                    } else {
                                        data[field_id] = "";
                                    }

                                }
                                else if (field_type == "{{config("constants.custom_form.field_types.option")}}") {
                                    selected_value = fields[i].querySelector("select.field_option").value;
                                    data[field_id] = selected_value;
                                }

                            }

                            fill_form(data, (response) => {
                                alert("Form Submitted Successfully");
                                window.location.href = '{{ route('admin.appointmentsmedical.medicals',[$appointmentinformation->id]) }}';

                            });
                        } else {
                            alert("fill form properly");
                        }
                    });

                    $(".select2").select2({
                        placeholder: "Select Patient"
                    });
                });


                /**
                 * saved filled form on server
                 * @param data
                 * @param success_callback
                 * @param error_callback
                 */

                function fill_form(data, success_callback, error_callback) {
                    $.ajax({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        url: route('admin.appointmentsmedical.submit_form',
                            {
                                'form_id': $("input[type=hidden]#form_id").val(),
                                'appointment_id': $("#appointment_id").val()
                            }),
                        type: 'POST',
                        data: data,
                        cache: false,
                        success: success_callback,
                        error: error_callback
                    });
                }


            </script>
            <script src="{{'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js'}}"
                    type="text/javascript"></script>
            <script src="{{ url('metronic/assets/global/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"
                    type="text/javascript"></script>
            <script>
                $(document).ready(function () {
                    var date = new Date();
                    date.setDate(date.getDate());
                    $('.date_to_rota').datepicker({
                        format: 'yyyy-mm-dd',
                        startDate: date
                    }).on('changeDate', function (ev) {
                        $(this).datepicker('hide');
                    })
                    $('.date_to_rota').datepicker({dateFormat: 'dd-mm-yy'}).datepicker("setDate", new Date());
                });

            </script>
@endsection

