<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">Detail</h2>
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
    <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
        <table class="table ">
            <tbody>
            <tr>
                <th>Full Name</th>
                <td id="full_name"></td>
                <th>Phone</th>
                <td id="phone"></td>
                <th>City</th>
                <td id="city"></td>

            </tr>

            <tr>
                <th>Centre</th>
                <td id="centre"></td>
                <th>Gender</th>
                <td id="gender"></td>
                <th>Lead Source</th>
                <td id="lead_source"></td>

            </tr>
            <tr>
                <th>Lead Status</th>
                <td id="lead_status"></td>
                <th>Active Service</th>
                <td id="activeservice"></td>
                <th>SMS Status</th>
                <td id="sms_status"></td>
            </tr>
            <tr>
                <th>All Service</th>
                <td id="allservices"></td>
                <th>Treatment</th>
                <td id="childservice"></td>
            </tr>
            </tbody>
        </table>

        <hr>
        <!--begin::Services History-->
        <div class="col-md-12">
            <div class="box-header ui-sortable-handle">
                <h3 class="box-title">Services History</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Service</th>
                            <th>Treatment</th>
                            <th>Lead Status</th>
                            <th>Status</th>
                            <th>Created Date</th>
                        </tr>
                    </thead>
                    <tbody id="services_history_table">
                        <tr>
                            <td colspan="6" class="text-center">No services found</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!--end::Services History-->

        <hr>
        <div class="col-md-11">
            <div class="col-md-12">
                <div class="box-header ui-sortable-handle">
                    <h3 class="box-title">Comments</h3>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-11">
                <div class="col-md-12">
                    <div class="portlet-body" id="commentsection">

                    </div>
                </div>
            </div>
        </div>
        <hr>
        @if(Gate::allows('leads_manage'))
            <div class="container" style="width:100%;padding-bottom:5%; ">
                <div class="box-footer">
                    <form id="cment">
                        <div class="col-md-12">
                            <label>Comment</label>
                            <input type="text" name="comment" class="form-control" required/>
                            <br/>
                        </div>
                        <input type="hidden" name="lead_id" id="comment_lead_id" class="form-control" value="" /><br/>
                        <div class="col-md-12">
                            <button type="button" name="Add_comment" id="Add_comment" class="btn btn-success">Comment</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->



