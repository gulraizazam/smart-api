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
                <a target="_blank"
                   href="{{ route('admin.custom_form_feedbacks.preview_form',["form_id"=>$custom_form->id]) }}"
                   class="btn dark pull-right">@lang('global.app_preview')</a>
                <a href="{{ route('admin.custom_forms.index') }}"
                   class="btn dark pull-right">@lang('global.app_back')</a>
            </div>
        </div>
        <div class="portlet-body rs">
            <!-- custom form -->
            <div id="cf_form">

                <!-- Form Name -->
                <div class="form-group form-md-line-input">
                    <input type="text" class="form-control rs-head custom_form_update_single_value" name="name"
                           placeholder=" Form name" value="{{$custom_form->name}}">
                </div>

                <input id="form_id" name="form_id" type="hidden" value="{{$custom_form->id}}">
                <!-- Form Description -->

                <div class="form-group form-md-line-input">
                    <textarea class="form-control custom_form_update_single_value" name="description"
                              placeholder="Form Description">{{$custom_form->description}}</textarea>
                </div>

                <!-- field list -->

                <div id="cf_field_list">

                    @foreach($custom_form->form_fields as $field)
                        <?php $content = \App\Helpers\CustomFormHelper::getContentArray($field->content); ?>

                        @if($field->field_type ==1)
                            @include("admin.custom_forms.fields.text_field", ['field_id'=>$field->id, 'title'=>$content["title"]])
                        @elseif($field->field_type ==2)
                            @include("admin.custom_forms.fields.paragraph_field", ['field_id'=>$field->id, 'title'=>$content["title"]])
                        @elseif($field->field_type ==3)
                            @include("admin.custom_forms.fields.single_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"]])
                        @elseif($field->field_type ==4 && is_array($content))
                            @include("admin.custom_forms.fields.multi_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"]])
                        @elseif($field->field_type ==5 && is_array($content))
                            @include("admin.custom_forms.fields.option_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"]])
                        @elseif($field->field_type ==6 && is_array($content))
                            @include("admin.custom_forms.fields.title_description_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"]])
                        @elseif($field->field_type ==7 && is_array($content))
                            @include("admin.custom_forms.fields.table_input_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"], "rows"=>$content["rows"]])
                        @endif

                    @endforeach

                </div>
            </div>
            <!-- end of custom form -->

            <!-- start of custom form navigation -->
            <div class="cf_nav">
                @include("admin.custom_forms.partials.nav")
            </div>
            <!-- end of custom form navigation -->

        </div>

    </div>
@stop

@push('js')

    <script type="application/javascript">


        /**
         *add text field
         *
         **/

        function addTextField() {
            alert("sdasa");

            data = {
                field_type: FieldType.TEXT_FIELD,
            };

            create_field_ajax(data, (response) => {
                field_id = response.data.id;
                field_id = DefaultFieldType.FIELD_PREFIX + field_id;
                fieldTemplate = '{!!str_replace(array("\n\r", "\n", "\r"), '', view("admin.custom_forms.field_template.text_field"))!!}';
                let new_field = $(fieldTemplate);
                $(DefaultFieldType.FIELD_LIST_SELECTOR).append(new_field);
                fieldHoverBinding();
                fieldCloseButtonBinding();
                fieldChangeUpdateBinding();
                addMoreButtonBinding();

            }, (xhr, ajaxOptions, thrownError) => {

            });

        }

        /**
         *add Paragraph field
         *
         **/


        function addParagraphField() {
            data = {
                field_type: FieldType.PARAGRAPH_FIELD,
            };

            create_field_ajax(data, (response) => {
                field_id = response.data.id;
                field_id = DefaultFieldType.FIELD_PREFIX + field_id;
                fieldTemplate = '{!!str_replace(array("\n\r", "\n", "\r"), '', view("admin.custom_forms.field_template.paragraph_field"))!!}';
                let new_field = $(fieldTemplate);
                $(DefaultFieldType.FIELD_LIST_SELECTOR).append(new_field);
                fieldHoverBinding();
                fieldCloseButtonBinding();
                fieldChangeUpdateBinding();
                addMoreButtonBinding();

            }, (xhr, ajaxOptions, thrownError) => {

            });
        }


        /**
         *add single select field
         *
         **/

        function addSingleField() {

            data = {
                field_type: FieldType.SINGLE_SELECT_FIELD,
            }

            create_field_ajax(data, (response) => {
                field_id = response.data.id;
                field_id = DefaultFieldType.FIELD_PREFIX + field_id;
                fieldTemplate = '{!!str_replace(array("\n\r", "\n", "\r"), '', view("admin.custom_forms.field_template.single_select_field"))!!}';
                let new_field = $(fieldTemplate);
                $(DefaultFieldType.FIELD_LIST_SELECTOR).append(new_field);
                fieldHoverBinding();
                fieldCloseButtonBinding();
                fieldChangeUpdateBinding();
                addMoreRadioButtonBinding();

            }, (xhr, ajaxOptions, thrownError) => {

            });


        }

        /**
         *add multi select field
         *
         **/
        function addMultiField() {
            data = {
                field_type: FieldType.MULTI_SELECT_FIELD,
            }

            create_field_ajax(data, (response) => {
                field_id = response.data.id;
                field_id = DefaultFieldType.FIELD_PREFIX + field_id;
                multiFieldTemplate = '{!!str_replace(array("\n\r", "\n", "\r"), '', view("admin.custom_forms.field_template.multi_select_field"))!!}';
                let new_field = $(multiFieldTemplate);
                $(DefaultFieldType.FIELD_LIST_SELECTOR).append(new_field);
                fieldHoverBinding();
                fieldCloseButtonBinding();
                fieldChangeUpdateBinding();
                addMoreButtonBinding();

            }, (xhr, ajaxOptions, thrownError) => {

            });

        }

        /**
         *add Table input field
         *
         **/
        function addTableInputField() {
            data = {
                field_type: FieldType.TABLE_INPUT_FIELD,
            }

            create_field_ajax(data, (response) => {
                field_id = response.data.id;
                field_id = DefaultFieldType.FIELD_PREFIX + field_id;
                fieldTemplate = '{!!str_replace(array("\n\r", "\n", "\r"), '', view("admin.custom_forms.field_template.table_input_field"))!!}';
                let new_field = $(fieldTemplate);
                $(DefaultFieldType.FIELD_LIST_SELECTOR).append(new_field);
                fieldHoverBinding();
                fieldCloseButtonBinding();
                fieldChangeUpdateBinding();
                addMoreTableInputBinding();

            }, (xhr, ajaxOptions, thrownError) => {

            });

        }

        /**
         * Add option field at the end
         * */
        function addOptionField() {
            data = {
                field_type: FieldType.OPTION_FIELD,
            };

            create_field_ajax(data, (response) => {
                field_id = response.data.id;
                field_id = DefaultFieldType.FIELD_PREFIX + field_id;
                fieldTemplate = '{!!str_replace(array("\n\r", "\n", "\r"), '', view("admin.custom_forms.field_template.option_select_field"))!!}';
                let new_field = $(fieldTemplate);
                $(DefaultFieldType.FIELD_LIST_SELECTOR).append(new_field);
                fieldHoverBinding();
                fieldCloseButtonBinding();
                fieldChangeUpdateBinding();
                addMoreOptionButtonBinding();

            }, (xhr, ajaxOptions, thrownError) => {

            });
        }

        /**
         * Add title and descriptio
         * */
        function addTitleField() {
            data = {
                field_type: FieldType.TITLE_DESCRIPTION_FIELD,
            };

            create_field_ajax(data, (response) => {
                field_id = response.data.id;
                field_id = DefaultFieldType.FIELD_PREFIX + field_id;
                fieldTemplate = '{!!str_replace(array("\n\r", "\n", "\r"), '', view("admin.custom_forms.field_template.title_description_field"))!!}';
                let new_field = $(fieldTemplate);
                $(DefaultFieldType.FIELD_LIST_SELECTOR).append(new_field);
                fieldHoverBinding();
                fieldCloseButtonBinding();
                fieldChangeUpdateBinding();
                addMoreButtonBinding();

            }, (xhr, ajaxOptions, thrownError) => {

            });
        }


        /**
         * crate form field ajax request
         **/
        function create_field_ajax(data, success_callback, error_callback) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: '{{route('admin.custom_forms.create_field', ['id'=>$custom_form->id])}}',
                type: 'POST',
                data: data,
                cache: false,
                success: success_callback,
                error: error_callback
            });
        }


        function fieldHoverBinding() {
            $(".mt-repeater").click(function () {
                $(".mt-repeater").removeClass("repeateroverlay");
                $(this).addClass("repeateroverlay");
            });
        }

        function fieldItemRemoveBinding() {
            $('.remove-me').click(function (e) {
                console.log("I am in remove me function");
                e.preventDefault();
                $(this).parent(".cf-q-option-list-item").remove();
                $(".update-question-fields").change();
            });
        }

        function fieldCloseButtonBinding() {

            $('.remove-question-me').click(function (e) {
                console.log("removing question");
                field_id = $(this).parent(".update-question-fields").attr("id");
                id = field_id.split("cs_field_")[1];
                if (id) {
                    console.log("field_id deleting : ");
                    console.log(id);
                    e.preventDefault();

                    if (confirm("do you want to delete?")) {
                        delete_form_field(id, {"field_id": id}, (res) => {

                        }, (xhr, ajaxOption, throwErro) => {

                        });
                    }
                }

                $(this).parent(".update-question-fields").remove();

            });
        }

        function fieldChangeUpdateBinding() {

            $(".update-question-fields").bind("change", function () {
                field_id = this.id.split("cs_field_")[1];

                if (field_id != "") {
                    console.log(this.id);
                    fields_data = $(this).find("input")
                    data = fields_data.serializeArray();
                    update_form_field(field_id, data, (response) => {

                    }, (xhr, ajaxOptions, thrownError) => {

                    });
                }
            });

        }

        function addMoreButtonBinding() {

            $(".add-more-check").click(function (e) {
                var newIn = '{!!str_replace(array("\n\r", "\n", "\r"), '', view("admin.custom_forms.field_template.sub.multi_select"))!!}';
                var newInput = $(newIn);
                var removeBtn = '<button class="btn delopt remove-me"> <i class="fa fa-close"></i></button>';
                var removeButton = $(removeBtn);
                $(this).parent().find(".cf-q-option-list-item").last().append(removeButton);
                $(this).prev().append(newInput);
                $('.remove-me').click(function (e) {
                    e.preventDefault();
                    $(".update-question-fields").change();
                    $(this).parent(".cf-q-option-list-item").remove();
                });
            });
        }

        function addMoreTableInputBinding() {

            $(".add-more-table-input").click(function (e) {
                var newIn = '{!!str_replace(array("\n\r", "\n", "\r"), '', view("admin.custom_forms.field_template.sub.table_input"))!!}';
                var newInput = $(newIn);
                var removeBtn = '<button class="btn delopt remove-me"> <i class="fa fa-close"></i></button>';
                var removeButton = $(removeBtn);
                $(this).parent().find(".cf-q-option-list-item").last().append(removeButton);
                $(this).prev().append(newInput);
                $('.remove-me').click(function (e) {
                    e.preventDefault();
                    $(".update-question-fields").change();
                    $(this).parent(".cf-q-option-list-item").remove();
                });
            });
        }

        function addMoreRadioButtonBinding() {

            $(".add-more-radio").click(function (e) {
                console.log("adding radio ");

                var newIn = '{!!str_replace(array("\n\r", "\n", "\r"), '', view("admin.custom_forms.field_template.sub.single_select"))!!}';

                var newInput = $(newIn);
                var removeBtn = '<button class="btn delopt remove-me"> <i class="fa fa-close"></i></button>';
                var removeButton = $(removeBtn);
                $(this).parent().find(".cf-q-option-list-item").last().append(removeButton);
                $(this).prev().append(newInput);
                $('.remove-me').click(function (e) {
                    e.preventDefault();
                    $(".update-question-fields").change();
                    $(this).parent(".cf-q-option-list-item").remove();
                });
            });
        }

        function addMoreOptionButtonBinding() {

            $(".add-more-option").click(function (e) {
                var newIn = '{!!str_replace(array("\n\r", "\n", "\r"), '', view("admin.custom_forms.field_template.sub.option_select"))!!}';
                var newInput = $(newIn);
                var removeBtn = '<button class="btn delopt remove-me "> <i class="fa fa-close"></i></button>';
                var removeButton = $(removeBtn);
                $(this).parent().find(".cf-q-option-list-item").last().append(removeButton);
                $(this).prev().append(newInput);
                $('.remove-me').click(function (e) {
                    e.preventDefault();
                    $(".update-question-fields").change();
                    $(this).parent(".cf-q-option-list-item").remove();
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
                TITLE_DESCRIPTION_FIELD: '6',
                TABLE_INPUT_FIELD: '7'
            }

            DefaultFieldType = {
                "FIELD_PREFIX": "cs_field_",
                "FIELD_LIST_SELECTOR": "#cf_field_list"
            }

            fieldCloseButtonBinding();
            fieldChangeUpdateBinding();
            addMoreButtonBinding();
            addMoreRadioButtonBinding();
            addMoreOptionButtonBinding();
            addMoreTableInputBinding();
            fieldHoverBinding();
            fieldItemRemoveBinding();

            $('.custom_form_update_single_value').blur(function () {
                let name = this.name;
                let value = this.value;
                update_field(this, name, value);
            });

        });

        /**
         * Delete request
         * @param field_id
         * @param data
         * @param success_callback
         * @param error_callback
         */
        function delete_form_field(field_id, data, success_callback, error_callback) {

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: route('admin.custom_forms.delete_field', {'form_id': $("#form_id").val(), 'field_id': field_id}),
                type: 'POST',
                data: data,
                cache: false,
                success: success_callback,
                error: error_callback
            });
        }


        function update_field(that, name, value) {
            let data = {}
            data[name] = value;
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: '{{route('admin.custom_forms.form_update', ['id'=>$custom_form->id])}}',
                type: 'POST',
                data: data,
                cache: false,
                success: function (response) {

                },
                error: function (xhr, ajaxOptions, thrownError) {
                }
            });
        }

        function update_form_field(field_id, data, success_callback, error_callback) {

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: route('admin.custom_forms.update_field', {'form_id': $("#form_id").val(), 'field_id': field_id}),
                type: 'POST',
                data: data,
                cache: false,
                success: success_callback,
                error: error_callback
            });
        }


    </script>
    <script>
        $(function () {
            $("#cf_field_list").sortable({
                axis: 'y',
                stop: function (event, ui) {
                    var data = $(this).sortable('serialize');
                    $.ajax({
                        data: data,
                        type: 'get',
                        url: '{{route('admin.custom_forms.sort_fields',["id" => $custom_form->id])}}',
                        success: function (data) {
                        }
                    });
                }
            });
            $("#cf_field_list").disableSelection();
        });
    </script>
@endpush

