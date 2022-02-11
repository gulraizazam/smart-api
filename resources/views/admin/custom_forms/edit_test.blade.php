@extends('layouts.app-rs')

@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.custom_forms.title')</h1>
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
                <a href="{{ route('admin.custom_forms.index') }}"
                   class="btn dark pull-right">@lang('global.app_back')</a>
            </div>
        </div>
        <div class="portlet-body rs">
            <div class="cf-form">

                <div class="form-group form-md-line-input">
                    <input type="text" class="form-control rs-head custom_form_update_single_value" name="name"
                           placeholder=" Form name">
                </div>

                <div class="form-group form-md-line-input">
                    <textarea class="form-control custom_form_update_single_value" name="description"
                              placeholder="Form Description"></textarea>
                </div>


                <?php $content = array("title" => "question", "options" => array(array("label" => array("text" => "option")))); ?>
                <div id="cf_field_list">


                    <!--text field -->
                    <div class="form-group mt-repeater update-question-fields cf_field ui-state-default cf_field_text">
                        <span class="cf_field_text_type"> Short Answer </span>
                        <div class="form-group form-md-line-input">
                            <input id="question" name="question" type="text" placeholder="Question"
                                   class="form-control mt-repeater-input-line"/>
                        </div>
                        <button
                                class="btn btn-danger del mt-repeater-delete mt-repeater-del-right mt-repeater-btn-inline remove-question-me">
                            <i class="fa fa-close"></i>
                        </button>
                    </div>

                    <!--paragraph field  -->
                    <div class="form-group mt-repeater update-question-fields cf_field ui-state-default cf_field_paragraph">
                        <span class="cf_field_text_type"> Paragraph </span>
                        <div class="form-group form-md-line-input">
                            <input id="question" name="question" type="text" placeholder="Question"
                                   class="form-control mt-repeater-input-line"/>
                        </div>
                        <button class="btn btn-danger del mt-repeater-delete mt-repeater-del-right mt-repeater-btn-inline remove-question-me">
                            <i class="fa fa-close"></i>
                        </button>
                    </div>


                    <!--single select  field  -->
                    <div id="question" class="form-group mt-repeater cf_field ui-state-default cf_field_single_select">

                        <span class="cf_field_text_type"> Single Select </span>
                        <div class="form-group form-md-line-input cf-question-title">
                            <input id="question" name="question" type="text" placeholder="Question"
                                   class="form-control mt-repeater-input-line"/>
                        </div>
                        <div class="mt-repeater-cell form-group form-md-line-input">
                            <div class="form-group form-md-radios mt-repeater">
                                <div class="md-radio-list cf-q-option-list">
                                    <div class="md-radio mt-repeater-cell cf-q-option-list-item">
                                        <label>
                                            <span></span>
                                            <span class="check"></span>
                                            <span class="box"></span> <input id="field" type="text" class="form-control"
                                                                             name="field[]"
                                                                             placeholder=" Option"></label>
                                    </div>
                                </div>
                                <button class="btn green mr mt-repeater-add add-more-radio">
                                    Add Option
                                </button>
                            </div>
                        </div>

                        <button class="btn btn-danger del mt-repeater-delete mt-repeater-del-right mt-repeater-btn-inline">
                            <i class="fa fa-close"></i>
                        </button>
                    </div>


                    <!--Multi select  field  -->
                    <div id="question"
                         class="form-group mt-repeater update-question-fields cf_field ui-state-default cf_field_multi_select">
                        <span class="cf_field_text_type"> Multi Selector </span>
                        <div class="form-group form-md-line-input cf-question-title">
                            <input id="question" name="question" type="text" placeholder="Question"
                                   class="form-control mt-repeater-input-line"/>
                        </div>
                        <div class="mt-repeater-cell form-group form-md-line-input">
                            <div class="md-checkbox-list cf-q-option-list">
                                <div id="field" class="md-checkbox mt-repeater-cell cf-q-option-list-item">
                                    <label>
                                        <span></span>
                                        <span class="check"></span>
                                        <span class="box"></span>
                                        <input id="field1" name="field[]" type="text" class="form-control"
                                               name="option"
                                               placeholder="Option"></label>
                                </div>
                            </div>
                            <button class="btn green mr add-more-check">
                                Add Option
                            </button>
                        </div>
                        <button
                                class="btn btn-danger del mt-repeater-delete mt-repeater-del-right mt-repeater-btn-inline remove-question-me">
                            <i class="fa fa-close"></i>
                        </button>
                    </div>


                    <!--option select  field  -->
                    <div id="question"
                         class="form-group mt-repeater update-question-fields cf_field ui-state-default cf_field_option_select">
                        <span class="cf_field_text_type"> Option List </span>
                        <div class="form-group form-md-line-input">
                            <input id="question" name="question" type="text" placeholder="Question"
                                   class="form-control mt-repeater-input-line"/>
                        </div>
                        <div class="mt-repeater-cell form-group form-md-line-input">
                            <div class="cf-q-option-list">
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

                        <button class="btn btn-danger del mt-repeater-delete mt-repeater-del-right mt-repeater-btn-inline">
                            <i class="fa fa-close"></i>
                        </button>
                    </div>


                    <!--title select  field  -->
                    <div id="question"
                         class="form-group mt-repeater update-question-fields cf_field ui-state-default cf_field_title_description">
                        <span class="cf_field_text_type"> Title </span>
                        <div class="form-group form-md-line-input">

                            <input id="question" name="question" type="text" placeholder="Question"
                                   class="form-control mt-repeater-input-line"/>
                        </div>
                        <button
                                class="btn btn-danger del mt-repeater-delete mt-repeater-del-right mt-repeater-btn-inline remove-question-me">
                            <i class="fa fa-close"></i>
                        </button>
                    </div>
                    <!-- end of list -->
                </div>
                <!-- end of form -->
            </div>
            <div class="cf_nav">
                <nav class="quick-nav rig">
                    <a class="quick-nav-trigger rs-plus" href="javascript:void(0)">
                        <span aria-hidden="true"></span>
                    </a>
                    <ul>
                        <li>
                            <button title="Text Field" onclick="addTextField()">
                                TF
                            </button>
                        </li>
                        <li>
                            <button title="Paragraph Field" onclick="addParagraphField()">
                                PF
                            </button>
                        </li>
                        <li>
                            <button title="single field" onclick="addSingleField()">
                                SF
                            </button>
                        </li>
                        <li>
                            <button title="multiple fields" onclick="addMultiField()">
                                MF
                            </button>
                        </li>
                        <li>
                            <button title="option fields" onclick="addOptionField()">
                                OF
                            </button>
                        </li>
                        <li>
                            <button title="title fields" onclick="addTitleField()">
                                TF
                            </button>
                        </li>

                    </ul>
                    <span aria-hidden="true" class="quick-nav-bg"></span>
                </nav>
            </div>
        </div>
    </div>




@stop

@section('javascript')
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('js/admin/custom_forms/fields.js') }}" type="text/javascript"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script type="application/javascript">


        function addOptionField() {

        }

        function addParagraphField() {

        }

        function addTextField() {

        }


        function addMultiField() {

            data = {
                field_type: FieldType.MULTI_SELECT_FIELD,
            }
            console.log("adding multifields");
            create_field_ajax(data, function (response) {

                console.log(response);
                question_id = response.data.id;
                var addto = "#question_" + question_id;
                multiFieldTemplate = '<div id="question' + question_id + '" class="form-group mt-repeater update-question-fields">\n' +
                    '                    <div class="form-group form-md-line-input">\n' +
                    '                        <input id="question" name="question" type="text" placeholder="Question"\n' +
                    '                               class="form-control mt-repeater-input-line"/>\n' +
                    '                    </div>\n' +
                    '                    <div class="mt-repeater-cell form-group form-md-line-input">\n' +
                    '                        <div class="md-checkbox-list">\n' +
                    '                            <div id="field1" class="md-checkbox mt-repeater-cell">\n' +
                    '                                <label for="checkbox2_1">\n' +
                    '                                    <span></span>\n' +
                    '                                    <span class="check"></span>\n' +
                    '                                    <span class="box"></span>\n' +
                    '                                    <input id="field" name="field[]" type="text" class="form-control"\n' +
                    '                                           name="option"\n' +
                    '                                           placeholder=" Option"></label>\n' +
                    '                            </div>\n' +
                    '                        </div>\n' +
                    '                        <button class="btn green mr add-more">\n' +
                    '                            Add Option\n' +
                    '                        </button>\n' +
                    '                    </div>\n' +
                    '                    <button\n' +
                    '                            class="btn btn-danger del mt-repeater-delete mt-repeater-del-right mt-repeater-btn-inline remove-question-me">\n' +
                    '                        <i class="fa fa-close"></i>\n' +
                    '                    </button>\n' +
                    '                </div>';

                var new_question = $(multiFieldTemplate);
                qulist = $("#questions_field_list").append(new_question);
                fieldHoverBinding();
                fieldCloseButtonBinding();
                fieldChangeUpdateBinding();
                addMoreButtonBinding();

            }, function (xhr, ajaxOptions, thrownError) {
                console.log("no field created");
            });

        }

        function addSingleField() {
            console.log("adding single field");
        }


        function fieldHoverBinding() {
            $(".mt-repeater").click(function () {
                $(".mt-repeater").removeClass("repeateroverlay");
                $(this).addClass("repeateroverlay");
            });
        }

        function fieldCloseButtonBinding() {

            $('.remove-question-me').click(function (e) {
                console.log("removing question");
                e.preventDefault();
                if (confirm("do you want to delete?")) {
                    a = $(this).parent(".update-question-fields").remove();
                } else {
                    console.log("deleting");
                }
            });
        }

        function fieldChangeUpdateBinding() {

            $(".update-question-fields").bind("change", function () {

                console.log(this.innerHTML);
                console.log("===========================================")
                inps = $(this).find("input")
                sa = inps.serializeArray();
                update_form_field(1, sa);
                console.log(sa);
            });

        }

        function addMoreButtonBinding() {

            $(".add-more-check").click(function (e) {
                console.log("adding other field");

                var newIn = '<div class="md-checkbox mt-repeater-cell" id="field"> <label for="checkbox2_1"> <span class="inc"></span> <span class="check"></span> <span class="box"></span> <input type="text" id="field" name="field[]" class="form-control" placeholder=" Option"></label></div>';

                var newInput = $(newIn);
                var removeBtn = '<button class="btn delopt remove-me"> <i class="fa fa-close"></i></button>';
                var removeButton = $(removeBtn);
                $(this).parent().find(".mt-repeater-cell").last().append(removeButton);
                $(this).prev().append(newInput);
                $('.remove-me').click(function (e) {
                    e.preventDefault();
                    $(this).parent(".mt-repeater-cell").remove();
                });
            });
        }

        function addMoreRadioButtonBinding() {

            $(".add-more-radio").click(function (e) {
                console.log("adding radio ");

                var newIn = ' <div class="md-radio mt-repeater-cell cf-q-option-list-item">\n' +
                    '                    <label>\n' +
                    '                        <span></span>\n' +
                    '                        <span class="check"></span>\n' +
                    '                        <span class="box"></span> <input id="field" type="text" class="form-control" name="field[]" placeholder=" Option"></label>\n' +
                    '                </div>';

                var newInput = $(newIn);
                var removeBtn = '<button class="btn delopt remove-me"> <i class="fa fa-close"></i></button>';
                var removeButton = $(removeBtn);
                $(this).parent().find(".mt-repeater-cell").last().append(removeButton);
                $(this).prev().append(newInput);
                $('.remove-me').click(function (e) {
                    e.preventDefault();
                    $(this).parent(".mt-repeater-cell").remove();
                });
            });
        }

        function addMoreOptionButtonBinding() {

            $(".add-more-option").click(function (e) {
                console.log("adding option ");

                var newIn = '<div id="field" class="mt-repeater-cell rs-shrt cf-q-option-list-item">\n' +
                    '                <label class="optlbl">\n' +
                    '                <input type="text" class="form-control optinput" id="field" name="field[]"\n' +
                    '                       placeholder="Short Answer"></label>' +
                    '\n' +
                    '            </div>';

                var newInput = $(newIn);
                var removeBtn = '<button class="btn delopt remove-me"> <i class="fa fa-close"></i></button>';
                var removeButton = $(removeBtn);
                $(this).parent().find(".mt-repeater-cell").last().append(removeButton);
                $(this).prev().append(newInput);
                $('.remove-me').click(function (e) {
                    e.preventDefault();
                    $(this).parent(".mt-repeater-cell").remove();
                });
            });
        }


        $(document).ready(function () {

            /**
             * text_field
             * paragraph_field
             * single_select_field
             * multi_select_field
             * option_field
             * title_description_field
             */

            FieldType = {
                TEXT_FIELD: '1',
                PARAGRAPH_FIELD: '2',
                SINGLE_SELECT_FIELD: '3',
                MULTI_SELECT_FIELD: '4',
                OPTION_FIELD: '5',
                TITLE_DESCRIPTION_FIELD: '6'
            }

            fieldCloseButtonBinding();
            fieldChangeUpdateBinding();
            addMoreButtonBinding();
            addMoreRadioButtonBinding();
            addMoreOptionButtonBinding();

            $('.custom_form_update_single_value').blur(function () {

                let name = this.name;
                let value = this.value;

                console.log("name : " + name + " , value : " + value);
                update_field(this, name, value);
            });

            fieldHoverBinding();
        });





    </script>
    <script>
        $(function () {
            $("#cf_field_list").sortable();
            $("#cf_field_list").disableSelection();
        });
    </script>
@endsection

