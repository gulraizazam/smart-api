@php
    $display = 'none;';
    $advance_class = 'fa-caret-right';
if(isset($filters)) {
        if(hasFilter($filters, 'gender')
            || hasFilter($filters, 'commission')
            || hasFilter($filters, 'status')
            || hasFilter($filters, 'created_from')
            || hasFilter($filters, 'created_to')) {
            $display = 'block;';
            $advance_class = 'fa-caret-down';
        }
    }

@endphp

<div class="mt-2 mb-7">

    <div class="row align-items-center">

        <div class="advance-search col-md-12 col-lg-12 col-xl-12">
            <div class="row align-items-center mr-2" style="float: right;">
                <div class="row">
                    <button class="btn btn-sm btn-default ml-2 mt-10" onclick="advanceFilters();">
                        <i class="advance-arrow fa {{$advance_class}}"></i>
                        Advance
                    </button>
                </div>
            </div>
        </div>


        <div class="col-lg-12 col-xl-12">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <label>Name:</label>
                    <input type="text" value="{{$filters['name'] ?? ''}}" class="form-control filter-field" placeholder="Name" id="search_name" />
                </div>

                <div class="col-md-3">
                    <label>Status:</label>
                    <select class="form-control filter-field select2" name="status" id="search_status">
                        <option value="" {{isset($filters['status']) && $filters['status'] == '' ? 'selected' : ''}}>All</option>
                        <option value="1" {{isset($filters['status']) && $filters['gender'] == '1' ? 'selected' : ''}}>Active</option>
                        <option value="0" {{isset($filters['status']) && $filters['status'] == '2' ? 'selected' : ''}}>Inactive</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label>Phone:</label>
                    <input type="text" value="{{$filters['phone'] ?? ''}}" class="form-control filter-field" placeholder="Phone" id="search_phone" />
                </div>

                <div class="col-md-3">
                    <label>Role:</label>
                    <select class="form-control filter-field select2" name="role_id" id="search_role" >
                        @foreach($roles as $id => $role)
                            <option value="{{$id}}" {{isset($filters['role_id']) && $filters['role_id'] == $id ? 'selected' : ''}}>{{$role}}</option>
                        @endforeach
                    </select>
                </div>

            </div>
        </div>

        <div class="col-lg-12 col-xl-12 mt-10 advance-filters" style="display: {{$display}}">
            <div class="row align-items-center">

                <div class="col-md-3">
                    <label>Center:</label>
                    <select class="form-control filter-field select2" name="location_id" id="search_center" >
                        @foreach($locations as $id => $location)
                            <option value="{{$id}}" {{isset($filters['location_id']) && $filters['location_id'] == $id ? 'selected' : ''}}>{{$location}}</option>
                        @endforeach
                    </select>
                </div>


                <div class="col-md-3">
                    <label>Gender:</label>
                    <select class="form-control filter-field select2" id="search_gender" name="gender">
                        <option value="" {{isset($filters['gender']) && $filters['gender'] == '' ? 'selected' : ''}}>All</option>
                        <option value="1" {{isset($filters['gender']) && $filters['gender'] == '1' ? 'selected' : ''}}>Male</option>
                        <option value="2" {{isset($filters['gender']) && $filters['gender'] == '2' ? 'selected' : ''}}>Female</option>

                    </select>
                </div>


                <div class="col-md-3">

                    <label>Commission:</label>
                    <div class="input-group">
                        <input type="number" class="form-control filter-field" value="{{$filters['commission'] ?? ''}}" name="commission" placeholder="Commission" id="search_commission" >
                        <div class="input-group-append">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <label>Email:</label>
                    <input type="text" value="{{$filters['email'] ?? ''}}" class="form-control filter-field" placeholder="Email" id="search_email" />
                </div>

                <div class="col-lg-6 mb-6" style="margin-top: 35px;">
                    <label>Create at:</label>
                    <div class="input-daterange input-group to-from-datepicker" >
                        <input type="text" value="{{$filters['created_from'] ?? ''}}" id="search_created_from" class="form-control filter-field datatable-input" name="created_from" placeholder="From" data-col-index="5">
                        <div class="input-group-append">
                            <span class="input-group-text">
                                <i class="la la-ellipsis-h"></i>
                            </span>
                        </div>
                        <input type="text" value="{{$filters['created_to'] ?? ''}}" id="search_created_to" class="form-control filter-field datatable-input" name="created_to" placeholder="To" data-col-index="5">
                    </div>
                </div>

            </div>
        </div>

        <div class="col-md-10 mt-10">

            @include('admin.partials.filter-buttons')

        </div>

    </div>

</div>
