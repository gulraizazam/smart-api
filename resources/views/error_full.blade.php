@extends('layouts.app')

@section('content')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.app_warning')
    </h1>
    <!-- END PAGE TITLE-->
    <!-- END PAGE HEADER-->
    <div class="portlet light bordered">
        <div class="portlet-title">
            <div class="actions"></div>
        </div>
        <div class="portlet-body">
            <div class="row">
                <div class="col-md-12 page-404">
                    <div class="number font-green"> 404 </div>
                    <div class="details">
                        <h3>Oops! You're lost.</h3>
                        <p>  You try to change record that not belongs to you.
                            <br/>
                            Kindly not try again otherwise account must be restricted by authority.
                    </div>
                </div>
            </div>
            <div class="clearfix"></div>
        </div>
    </div>
@stop