<!--begin::Card-->
<div class="card card-custom example example-compact">
    <div class="card-header">
        <h3 class="card-title">Basic Validation</h3>
        <div class="card-toolbar">
            <div class="example-tools justify-content-center">
                <span class="example-toggle" data-toggle="tooltip" title="" data-original-title="View code"></span>
            </div>
        </div>
    </div>
    <!--begin::Form-->
    <form class="form fv-plugins-bootstrap " id="permissions-form" action="{{route('admin.roles.store')}}">


        <div class="card card-custom gutter-b example example-compact">


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
                    <h3 class="card-label">Create</h3>
                </div>
                <div class="col-md-10">
                    <a href="{{route('admin.roles.index')}}" class="btn btn-sm btn-primary mt-3" style="float: right;"><i class="fa fa-arrow-left"></i>Back</a>
                </div>
            </div>

            <div class="card-body">
                <div class="form-group row ">
                    <div class="fv-row col-md-6 my-md-0">
                        <label>Name <span class="text text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Name"/>
                    </div>

                    <div class="fv-row col-md-6 my-md-0">
                        <label>Commission <span class="text text-danger">*</span></label>
                        <input style="width: 95%;" type="number" name="commission" min="0" max="100" class="form-control" placeholder="Commission" />
                        <div class="input-group-append percentage-align">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>

                    {{-- <div class="col-md-2">
                         <button class="btn btn-success mt-10 ml-10">Save</button>
                     </div>--}}

                </div>
            </div>

        </div>

        <div class="card-footer">
            <div class="row">
                <div class="col-lg-9 ml-lg-auto">
                    <button type="submit" class="btn btn-primary font-weight-bold mr-2" name="submitButton">Validate</button>
                    <button type="reset" class="btn btn-light-primary font-weight-bold">Cancel</button>
                </div>
            </div>
        </div>
    </form>
    <!--end::Form-->
</div>
<!--end::Card-->
