@inject('request', 'Illuminate\Http\Request')
@extends('layouts.app')
@section('stylesheets')
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <link href="{{ url('metronic/assets/global/plugins/jquery-nestable/jquery.nestable.css') }}" rel="stylesheet"
          type="text/css"/>
    <!-- END PAGE LEVEL PLUGINS -->
@stop
@section('title')
    <!-- BEGIN PAGE TITLE-->
    <!-- END PAGE TITLE-->
@endsection
@section('content')
    <div class="portlet light bordered">
        <div class="portlet-title">
            <div class="caption font-green-sharp">
                <i class="icon-plus font-green-sharp"></i>
                <span class="caption-subject bold uppercase"> @lang('global.custom_forms.fields.sort')</span>
            </div>
            <div class="actions">
                <a href="{{ route('admin.custom_forms.index') }}"
                   class="btn dark pull-right">@lang('global.app_back')</a>
            </div>
        </div>
        <div class="portlet-body form">
            <div class="portlet-body">
                <div class="dd" id="nestable_list_3">
                    <ul id="data" class="dd-list">
                        @foreach($custom_forms as $custom_form)
                            <li class="dd-item dd3-item" id="{{$custom_form->id}}">
                                <div class="dd-handle dd3-handle"></div>
                                <div class="dd3-content"> {{$custom_form->name}} </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script src="http://code.jquery.com/jquery-1.10.2.js"></script>
    <script src="http://code.jquery.com/ui/1.11.2/jquery-ui.js"></script>
    <script src="http://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/2.3.1/js/bootstrap.min.js"></script>
    <script>
        $(function () {
            $('#data').sortable({
                stop: function () {
                    $.map($(this).find('li'), function (el) {

                        var itemID = el.id;
                        var itemIndex = $(el).index();
                        $.ajax({
                            url: route('admin.custom_forms.sort_save'),
                            type: 'get',
                            dataType: 'json',
                            data: {itemID: itemID, itemIndex: itemIndex},

                            success: function ($myarray) {
                                if ($myarray) {
                                    console.log($myarray.status);
                                }
                            }
                        });

                    });
                }
            });
        });
    </script>
@stop

