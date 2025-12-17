@extends('admin.layouts.master')


@section('content')

@push('css')
    <link href="{{asset('assets/css/components.min.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{asset('assets/css/layout.min.css')}}" rel="stylesheet" type="text/css" />
@endpush


   <!--begin::Content-->
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Custom Form', 'title' => 'Custom Form'])
    <!--begin::Entry-->
    <div class="d-flex flex-column-fluid">
    <!--begin::Container-->
        <div class="container">
            <!--begin::Card-->
            <div class="card card-custom">
                <div class="card-header py-3">
                    <div class="card-title">
                        <span class="card-icon">
                            <span class="svg-icon svg-icon-md svg-icon-primary">
                            <!--begin::Svg Icon | path:assets/media/svg/icons/Shopping/Chart-bar1.svg-->
                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <rect x="0" y="0" width="24" height="24" />
                                    <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13" rx="1.5" />
                                    <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8" rx="1.5" />
                                    <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero" />
                                    <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5" />
                                </g>
                            </svg>
                            <!--end::Svg Icon-->
                            </span>
                        </span>
                        <h3 class="card-label">Custom Form</h3>
                    </div>
                    <div class="card-toolbar">
                        <div class="card-toolbar">
                            <!--begin::Dropdown-->
                            <a href="{{ route('admin.custom_forms.index') }}" class="btn btn-sm btn-dark" >
                            <i class="la la-arrow-left"></i>
                            Back
                            </a>
                            &nbsp; &nbsp;
                            <a  target="_blank" href="{{ route('admin.custom_form_feedbacks.preview_form', $custom_form)}}" class="btn btn-sm btn-primary" >
                            <i class="la la-plus"></i>
                            Preview
                            </a>
                            <!--end::Button-->
                        </div>
                        <!--end::Button-->
                    </div>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <div class="row">
                        <input id="form_id" name="form_id" type="hidden" value="{{$custom_form->id}}">
                        <div class="col-md-12">
                        <!-- Form Name -->
                        <div class="form-group form-md-line-input">
                            <input type="text" class="form-control rs-head custom_form_update_single_value under-border large_font" name="name"
                                placeholder=" Form name" value="{{$custom_form->name}}">
                        </div>
                        <div>
                            <!-- Form Description -->
                            <div class="col-md-12">
                                <div class="form-group form-md-line-input">
                                    <textarea class="form-control custom_form_update_single_value under-border" name="description"
                                    placeholder="Form Description">{{$custom_form->description}}</textarea>
                                </div>
                            </div>
                        </div>
                        
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
                        <!-- start of custom form navigation -->
                        <div class="cf_nav">
                            @include("admin.custom_forms.partials.nav")
                        </div>
                        <!-- end of custom form navigation -->
                    </div>
                </div>
            </div>
            <!--end::Card-->
            </div>
    </div>
    <!--end::Card-->
</div>
<!--end::Container-->
</div>
<!--end::Entry-->
</div>
<!--end::Content-->

        

        @push('js')
        
        <script src="{{asset('assets/js/quick-nav.js')}}"></script>
        <script src="{{asset('assets/js/jquery-ui.js')}}"></script>

        <script type="application/javascript">


/**
 *add text field
 *
 **/

function addTextField() {

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
        url: '{{route('admin.custom_forms.create_field', $custom_form)}}',
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
  
        e.preventDefault();
        $(this).parent(".cf-q-option-list-item").remove();
        $(".update-question-fields").change();
    });
}

function fieldCloseButtonBinding() {

    
}

function fieldChangeUpdateBinding() {

    $(".update-question-fields").bind("change", function () {
        field_id = this.id.split("cs_field_")[1];

        if (field_id != "") {

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
        var removeBtn = '<button class="btn delopt remove-me"> <i class="la la-close"></i></button>';
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
        var removeBtn = '<button class="btn delopt remove-me"> <i class="la la-close"></i></button>';
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


        var newIn = '{!!str_replace(array("\n\r", "\n", "\r"), '', view("admin.custom_forms.field_template.sub.single_select"))!!}';

        var newInput = $(newIn);
        var removeBtn = '<button class="btn delopt remove-me"> <i class="la la-close"></i></button>';
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
        var removeBtn = '<button class="btn delopt remove-me "> <i class="la la-close"></i></button>';
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
        url: '{{route('admin.custom_forms.form_update', $custom_form)}}',
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
                url: '{{route('admin.custom_forms.sort_fields', $custom_form)}}',
                success: function (data) {
                }
            });
        }
    });
   
    $("#cf_field_list").disableSelection();



    $(document).on('click', '.remove-question-me', function (e) {

        field_id = $(this).parent(".update-question-fields").attr("id");
        id = field_id.split("cs_field_")[1];
        if (id) {
            e.preventDefault();
            let $this = $(this);

            swal.fire({
                title: 'do you want to delete?',
                type: 'danger',
                icon: 'info',
                buttonsStyling: false,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
                showCancelButton: true,
                cancelButtonClass: 'btn btn-primary font-weight-bold',
                confirmButtonClass: 'btn btn-danger font-weight-bold'
                }).then(function(result) {
                    
                    if (result.value) {
                        delete_form_field(id, {"field_id": id}, (res) => {

                        }, (xhr, ajaxOption, throwErro) => {

                        });

                        $this.parent(".update-question-fields").remove();
                    }
            });

    
        }

    });


});
</script>
        

@endpush

@endsection