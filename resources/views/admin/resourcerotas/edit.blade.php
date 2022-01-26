<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder rota-title">Add Rota</h2>
        <!--end::Modal title-->
        <!--begin::Close-->
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" data-kt-users-modal-action="close">
            <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
            <span class="svg-icon svg-icon-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                    <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                </svg>
            </span>
            <!--end::Svg Icon-->
        </div>
        <!--end::Close-->
    </div>
    <!--end::Modal header-->
    <!--begin::Modal body-->
    <div class="modal-body scroll-y mx-5 mx-xl-15">
        <!--begin::Form-->
        <form id="modal_edit_resourcerotas_form" method="post" action="">
            <!--begin::Scroll-->

            @method('put')

            <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_resourcerotas_scroll">

                <div class="form-group">

                    <div class="row">

                        <div class="fv-row col-md-4 mt-5"><strong class="font-size-h6-sm">Resource Name:</strong></div>
                        <div class="fv-row col-md-8 mt-5"><strong class="font-size-h5-sm" id="resource-name"></strong></div>

                        <div class="fv-row col-md-4 mt-5"><strong class="font-size-h6-sm">City:</strong></div>
                        <div class="fv-row col-md-8 mt-5"><strong class="font-size-h5-sm"  id="city-name"></strong></div>

                        <div class="fv-row col-md-4 mt-5"><strong class="font-size-h6-sm">Centre:</strong></div>
                        <div class="fv-row col-md-8 mt-5"><strong class="font-size-h5-sm"  id="centre-name"></strong></div>

                        <div class="fv-row col-md-4 mt-5"><strong class="font-size-h6-sm">Rota Start Date:</strong></div>
                        <div class="fv-row col-md-8 mt-5"><strong class="font-size-h5-sm"  id="rota-start-date"></strong></div>

                    </div>

                    <div class="row">
                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">From <span class="text text-danger">*</span></label>
                            <input type="text" id="edit_start" class="form-control current-datepicker" name="start">
                        </div>

                        <div class="fv-row col-md-6 mt-5">
                            <label class="required fw-bold fs-6 mb-2 pl-0">To <span class="text text-danger">*</span></label>
                            <input type="text" id="edit_end" class="form-control current-datepicker" name="end">
                        </div>

                    </div>


                    <div class="row mt-10 hideonmbl">
                        <div class="col col-md-1"><strong>On</strong></div>
                        <div class="col col-md-3"><strong>Days</strong></div>
                        <div class="col col-md-2"><strong>From</strong></div>
                        <div class="col col-md-2"><strong>To</strong></div>
                        <div class="col col-md-2"><strong>From Break</strong></div>
                        <div class="col col-md-2"><strong>To Break</strong></div>

                    </div>

                    <div class="row mt-10 hideonmbl" id="edit_mondayOperation_1">

                        <div class="fv-row col-md-4">

                            <label class="checkbox">
                                <span class="checkbox edit_check_final_1">
                                    <input id="edit_mondayElement_1" checked type="checkbox" name="mondaychecked" class="mr-2">
                                    <span></span>
                                </span>
                                &nbsp;&nbsp;<strong class="position-absolute ml-10 font-size-h6-sm">Monday</strong>
                            </label>


                            <div class="ml-2">
                                <label class="checkbox">
                                    <input type="checkbox" id="edit_copy_all_1" name="copy_all" value='' style="display: none;">
                                    <strong style="font-size: 12px;border-bottom: 1px solid #333; cursor: pointer;margin-left: 100px;
    margin-top: -18px;">Copy As All</strong>
                                    <span style="display: none;"></span>
                                </label>
                            </div>

                        </div>

                        <div class="fv-row col-md-2">
                            {!! Form::text('time_f_monday', old('time_f_monday'), ['id' => 'edit_monday_from', 'class' => 'form-control current-timepicker timepicker edit_mondaytime_1 mondayfrom_1' ]) !!}
                        </div>
                        <div class="fv-row col-md-2">

                            {!! Form::text('time_to_monday', old('time_to_monday'), ['id' => 'edit_monday_to', 'class' => 'form-control current-timepicker timepicker edit_mondaytime_1 mondayto_1']) !!}
                        </div>
                        <div class="fv-row col-md-2">

                            {!! Form::text('break_from_monday', old('break_from_monday'), ['id' => 'edit_break_monday_from', 'class' => 'form-control timepicker edit_monday_breake_time break_mondayfrom']) !!}
                        </div>
                        <div class="fv-row col-md-2">

                            {!! Form::text('break_to_monday', old('break_to_monday'), ['id' => 'edit_break_monday_to', 'class' => 'form-control timepicker edit_monday_breake_time break_mondayto']) !!}
                        </div>

                    </div>

                    <div class="row mt-10 hideonmbl" id="edit_tuesdayOperation_1">

                        <div class="fv-row col-md-4">
                            <label class="checkbox">
                                <span class="checkbox edit_check_final_1">
                                    <input id="edit_tuesdayElement_1" checked type="checkbox" name="tuesdaychecked" class="mr-2">
                                    <span></span>
                                </span>
                                &nbsp;&nbsp;<strong class="position-absolute ml-10 font-size-h6-sm">Tuesday</strong>
                            </label>
                        </div>

                        <div class="fv-row col-md-2">
                            {!! Form::text('time_f_tuesday', old('time_f_tuesday'), ['id' => 'edit_time_f_tuesday', 'class' => 'form-control timepicker current-timepicker time_to_Rota_1 edit_tuesdaytime_1 ftime_1' ]) !!}
                        </div>
                        <div class="fv-row col-md-2">

                            {!! Form::text('time_to_tuesday', old('time_to_tuesday'), ['id' => 'edit_time_to_tuesday', 'class' => 'form-control timepicker current-timepicker time_to_Rota_1 edit_tuesdaytime_1 ttime_1' ]) !!}
                        </div>
                        <div class="fv-row col-md-2">
                            {!! Form::text('break_from_tuesday', old('break_from_tuesday'), ['id' => 'edit_break_from_tuesday', 'class' => 'form-control timepicker breaktime edit_tuesdaytime_break f_time_break']) !!}
                        </div>
                        <div class="fv-row col-md-2">
                            {!! Form::text('break_to_tuesday', old('break_to_tuesday'), ['id' => 'edit_break_to_tuesday', 'class' => 'form-control timepicker breaktime edit_tuesdaytime_break t_time_break']) !!}
                        </div>


                    </div>

                    <div class="row mt-10 hideonmbl" id="edit_wednesdayOperation_1">

                        <div class="fv-row col-md-4">
                            <label class="checkbox">
                                <span class="checkbox edit_check_final_1">
                                    <input id="edit_wednesdayElement_1" type="checkbox" name="wednesdaychecked" checked class="mr-2">
                                    <span></span>
                                </span>
                                &nbsp;&nbsp;<strong class="position-absolute ml-10 font-size-h6-sm">Wednesday</strong>
                            </label>
                        </div>

                        <div class="fv-row col-md-2">
                            {!! Form::text('time_f_wednesday', old('time_f_wednesday'), ['id' => 'edit_time_f_wednesday', 'class' => 'form-control timepicker current-timepicker time_to_Rota_1 edit_wednesdaytime_1 ftime_1']) !!}
                        </div>
                        <div class="fv-row col-md-2">

                            {!! Form::text('time_to_wednesday', old('time_to_wednesday'), ['id' => 'edit_time_to_wednesday', 'class' => 'form-control timepicker current-timepicker time_to_Rota_1 edit_wednesdaytime_1 ttime_1']) !!}
                        </div>
                        <div class="fv-row col-md-2">

                            {!! Form::text('break_from_wednesday', old('break_from_wednesday'), ['id' => 'edit_break_from_wednesday', 'class' => 'form-control timepicker breaktime edit_wednesdaytime_break f_time_break']) !!}
                        </div>
                        <div class="fv-row col-md-2">

                            {!! Form::text('break_to_wednesday', old('break_to_wednesday'), ['id' => 'edit_break_to_wednesday', 'class' => 'form-control timepicker breaktime edit_wednesdaytime_break t_time_break']) !!}
                        </div>

                    </div>

                    <div class="row mt-10 hideonmbl" id="edit_thursdayOperation_1">

                        <div class="fv-row col-md-4">
                            <label class="checkbox">
                                <span class="checkbox edit_check_final_1">
                                    <input id="edit_thursdayElement_1" type="checkbox" name="thursdaychecked" checked class="mr-2">
                                    <span></span>
                                </span>
                                &nbsp;&nbsp;<strong class="position-absolute ml-10 font-size-h6-sm">Thursday</strong>
                            </label>
                        </div>

                        <div class="fv-row col-md-2">
                            {!! Form::text('time_f_thursday', old('time_f_thursday'), ['id' => 'edit_time_f_thursday', 'class' => 'form-control timepicker current-timepicker time_to_Rota_1 edit_thursdaytime_1 ftime_1' ]) !!}
                        </div>
                        <div class="fv-row col-md-2">
                            {!! Form::text('time_to_thursday', old('time_to_thursday'), ['id' => 'edit_time_to_thursday', 'class' => 'form-control timepicker current-timepicker time_to_Rota_1 edit_thursdaytime_1 ttime_1']) !!}
                        </div>
                        <div class="fv-row col-md-2">
                            {!! Form::text('break_from_thursday', old('break_from_thursday'), ['id' => 'edit_break_from_thursday', 'class' => 'form-control timepicker timepicker-no-seconds breaktime edit_thursdaytime_break f_time_break']) !!}
                        </div>
                        <div class="fv-row col-md-2">
                            {!! Form::text('break_to_thursday', old('break_to_thursday'), ['id' => 'edit_break_to_thursday', 'class' => 'form-control timepicker timepicker-no-seconds breaktime edit_thursdaytime_break t_time_break']) !!}
                        </div>

                    </div>

                    <div class="row mt-10 hideonmbl" id="edit_fridayOperation_1">

                        <div class="fv-row col-md-4">
                            <label class="checkbox">
                                <span class="checkbox edit_check_final_1">
                                    <input id="edit_fridayElement_1" type="checkbox" name="fridaychecked" checked class="mr-2">
                                    <span></span>
                                </span>
                                &nbsp;&nbsp;<strong class="position-absolute ml-10 font-size-h6-sm">Friday</strong>
                            </label>
                        </div>

                        <div class="fv-row col-md-2">
                            {!! Form::text('time_f_friday', old('time_f_friday'), ['id' => 'edit_time_f_friday', 'class' => 'form-control timepicker current-timepicker time_to_Rota_1 edit_fridaytime_1 ftime_1']) !!}
                        </div>
                        <div class="fv-row col-md-2">
                            {!! Form::text('time_to_friday', old('time_to_friday'), ['id' => 'edit_time_to_friday', 'class' => 'form-control timepicker current-timepicker time_to_Rota_1 edit_fridaytime_1 ttime_1']) !!}
                        </div>
                        <div class="fv-row col-md-2">
                            {!! Form::text('break_from_friday', old('break_from_friday'), ['id' => 'edit_break_from_friday', 'class' => 'form-control timepicker breaktime edit_fridaytime_break f_time_break']) !!}
                        </div>
                        <div class="fv-row col-md-2">
                            {!! Form::text('break_to_friday', old('break_to_friday'), ['id' => 'edit_break_to_friday', 'class' => 'form-control timepicker breaktime edit_fridaytime_break t_time_break']) !!}
                        </div>

                    </div>

                    <div class="row mt-10 hideonmbl" id="edit_saturdayOperation_1">

                        <div class="fv-row col-md-4">
                            <label class="checkbox">
                                <span class="checkbox edit_check_final_1">
                                    <input id="edit_saturdayElement_1" type="checkbox" name="saturdaychecked" checked class="mr-2">
                                    <span></span>
                                </span>
                                &nbsp;&nbsp;<strong class="position-absolute ml-10 font-size-h6-sm">Saturday</strong>
                            </label>
                        </div>

                        <div class="fv-row col-md-2">
                            {!! Form::text('time_f_saturday', old('time_f_saturday'), ['id' => 'edit_time_f_saturday', 'class' => 'form-control timepicker current-timepicker time_to_Rota_1 edit_saturdaytime_1 ftime_1']) !!}
                        </div>
                        <div class="fv-row col-md-2">
                            {!! Form::text('time_to_saturday', old('time_to_saturday'), ['id' => 'edit_time_to_saturday', 'class' => 'form-control timepicker current-timepicker time_to_Rota_1 edit_saturdaytime_1 ttime_1']) !!}
                        </div>
                        <div class="fv-row col-md-2">
                            {!! Form::text('break_from_saturday', old('break_from_saturday'), ['id' => 'edit_break_from_saturday', 'class' => 'form-control timepicker breaktime edit_saturdaytime_break f_time_break']) !!}
                        </div>
                        <div class="fv-row col-md-2">
                            {!! Form::text('break_to_saturday', old('break_to_saturday'), ['id' => 'edit_break_to_saturday', 'class' => 'form-control timepicker breaktime edit_saturdaytime_break t_time_break']) !!}
                        </div>

                    </div>

                    <div class="row mt-10 hideonmbl" id="edit_sundayOperation_1">

                        <div class="fv-row col-md-4">
                            <label class="checkbox">
                                <span class="checkbox edit_check_final_1">
                                    <input id="edit_sundayElement_1" type="checkbox" name="sundaychecked" checked class="mr-2">
                                    <span></span>
                                </span>
                                &nbsp;&nbsp;<strong class="position-absolute ml-10 font-size-h6-sm">Sunday</strong>
                            </label>
                        </div>

                        <div class="fv-row col-md-2">
                            {!! Form::text('time_f_sunday', old('time_f_sunday'), ['id' => 'edit_time_f_sunday', 'class' => 'form-control timepicker current-timepicker time_to_Rota_1 sundaytime_1 ftime_1']) !!}
                        </div>
                        <div class="fv-row col-md-2">

                            {!! Form::text('time_to_sunday', old('time_to_sunday'), ['id' => 'edit_time_to_sunday', 'class' => 'form-control timepicker current-timepicker time_to_Rota_1 sundaytime_1 ttime_1']) !!}
                        </div>
                        <div class="fv-row col-md-2">

                            {!! Form::text('break_from_sunday', old('break_from_sunday'), ['id' => 'edit_break_from_sunday', 'class' => 'form-control timepicker breaktime sundaytime_break f_time_break']) !!}
                        </div>
                        <div class="fv-row col-md-2">

                            {!! Form::text('break_to_sunday', old('break_to_sunday'), ['id' => 'edit_break_to_sunday', 'class' => 'form-control timepicker breaktime sundaytime_break t_time_break']) !!}
                        </div>

                    </div>


                    <div class="row doctor_section d-none" id="Rota_type_operation">
                        <div class="col-md-4"></div>
                        <div class="fv-row col-md-4 mt-5">
                            <label class="checkbox is_consultancy_1">
                                <input type="hidden" name="is_consultancy" value="0"/>
                                <input id="edit_is_consultancy_1" type="checkbox" name="is_consultancy" value="1" checked class="mr-2">
                                <span></span>
                                &nbsp;&nbsp;<strong class="position-absolute ml-10 font-size-h6-sm">Consultancy</strong>
                            </label>
                        </div>

                        <div class="fv-row col-md-4 mt-5">
                            <label class="checkbox is_treatment_1">
                                <input type="hidden" name="is_treatment" value="0"/>
                                <input  id="edit_is_treatment_1" type="checkbox" name="is_treatment" value="1" checked class="mr-2">
                                <span></span>
                                &nbsp;&nbsp;<strong class="position-absolute ml-10 font-size-h6-sm">Treatment</strong>
                            </label>
                        </div>
                    </div>

                </div>

            </div>
            <!--end::Scroll-->
            <!--begin::Actions-->
            <hr>
            <div class="text-center">
                <button type="reset" class="btn btn-light me-3 popup-close" data-kt-users-modal-action="cancel">Cancel</button>
                <button type="submit" class="btn btn-primary spinner-button">
                    <span class="indicator-label">Submit</span>
                </button>
            </div>
            <!--end::Actions-->
        </form>
        <!--end::Form-->
    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->



