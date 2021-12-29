<div class="card-body">
    <div class="mt-2 mb-7">
        <div class="row align-items-center">

            <div class="col-lg-12 col-xl-12">
                <div class="row align-items-center">
                    <div class="col-md-2">
                        <label>Name:</label>
                        <input type="text" value="{{$filters['name'] ?? ''}}" class="form-control filter-field" placeholder="Name" id="search_name" />
                    </div>

                    <div class="col-md-2">
                        <label>Email:</label>
                        <input type="text" value="{{$filters['email'] ?? ''}}" class="form-control filter-field" placeholder="Email" id="search_email" />
                    </div>

                    <div class="col-md-2">
                        <label>Phone:</label>
                        <input type="text" value="{{$filters['phone'] ?? ''}}" class="form-control filter-field" placeholder="Phone" id="search_phone" />
                    </div>

                    <div class="col-md-2">
                        <label>Center:</label>
                        <select class="form-control filter-field select2" name="location_id" id="search_center" >
                            @foreach($locations as $id => $location)
                                <option value="{{$id}}" {{isset($filters['location_id']) && $filters['location_id'] == $id ? 'selected' : ''}}>{{$location}}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label>Role:</label>
                        <select class="form-control filter-field select2" name="role_id" id="search_role" >
                            @foreach($roles as $id => $role)
                                <option value="{{$id}}" {{isset($filters['role_id']) && $filters['role_id'] == $id ? 'selected' : ''}}>{{$role}}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 mt-10">
                        <div class="input-icon">
                            <button class="btn btn-sm btn-success" id="apply-filters">Search</button>
                            <button class="btn btn-sm btn-danger ml-2" onclick="resetFilters();" id="reset-filters">Reset</button>

                        </div>
                    </div>

                </div>
            </div>

            @php 
             $display = 'none;';
             $advance_class = 'advance-arrow fa fa-caret-right';
            if(hasFilter($filters, 'gender')
              || hasFilter($filters, 'commission')
              || hasFilter($filters, 'status') 
              || hasFilter($filters, 'created_from') 
              || hasFilter($filters, 'created_to')) {
                $display = 'block;';
                $advance_class = 'advance-arrow fa fa-caret-down';
              }
           
            @endphp
          
            <div class="col-lg-12 col-xl-12 mt-10 advance-filters" style="display: {{$display}}">
                <div class="row align-items-center">
                    <div class="col-md-2">
                        <label>Gender:</label>
                        <select class="form-control filter-field select2" id="search_gender" name="gender">
                            <option value="" {{isset($filters['gender']) && $filters['gender'] == '' ? 'selected' : ''}}>All</option>
                            <option value="1" {{isset($filters['gender']) && $filters['gender'] == '1' ? 'selected' : ''}}>Male</option>
                            <option value="2" {{isset($filters['gender']) && $filters['gender'] == '2' ? 'selected' : ''}}>Female</option>

                        </select>
                    </div>

                    <div class="col-md-2">
                        <label>Commission:</label>
                        <input type="text" value="{{$filters['commission'] ?? ''}}" name="commission" class="form-control filter-field" placeholder="Commission" id="search_commission" />
                    </div>

                    <div class="col-md-2">
                        <label>Status:</label>
                        <select class="form-control filter-field select2" name="status" id="search_status">
                            <option value="" {{isset($filters['status']) && $filters['status'] == '' ? 'selected' : ''}}>All</option>
                            <option value="1" {{isset($filters['status']) && $filters['gender'] == '1' ? 'selected' : ''}}>Active</option>
                            <option value="0" {{isset($filters['status']) && $filters['status'] == '2' ? 'selected' : ''}}>Inactive</option>
                        </select>
                    </div>

                    <div class="col-lg-3 mb-lg-0 mb-6">
                        <label>Create at:</label>
                        <div class="input-daterange input-group to-from-datepicker" >
                            <input type="text" value="{{$filters['created_from'] ?? ''}}" id="search_created_from" class="form-control datatable-input" name="created_from" placeholder="From" data-col-index="5">
                            <div class="input-group-append">
                                <span class="input-group-text">
                                    <i class="la la-ellipsis-h"></i>
                                </span>
                            </div>
                            <input type="text" value="{{$filters['created_to'] ?? ''}}" id="search_created_to" class="form-control datatable-input" name="created_to" placeholder="To" data-col-index="5">
                        </div>
                    </div>

                </div>
            </div>

            <div class="col-lg-11 col-xl-11">
                <div class="row align-items-center" style="float: right;">
                    <div class="row">
                        <button class="btn btn-sm btn-default ml-2 mt-10" onclick="advanceFilters();" id="reset-search">
                         <i class="{{$advance_class}}"></i> 
                         Advance
                         </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>