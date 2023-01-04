@php
    $display = 'none;';
    $advance_class = 'fa-caret-right';
@endphp

<div class="mt-2 mb-7">

    <div class="row mb-6">

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Name:</label>
            <input type="text" value="{{$filters['name'] ?? ''}}" class="form-control filter-field" placeholder="Enter Name" id="search_name" />
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>Featured:</label>
            <select class="form-control filter-field select2" name="payment_type" id="search_payment_type">
                <option value="" {{isset($filters['status']) && $filters['status'] == '' ? 'selected' : ''}}>All</option>
                @foreach(config('constants.payment_type') as $key => $payment_type)
                    <option value="{{$key}}" {{isset($filters['payment_type']) && $filters['payment_type'] == $key ? 'selected' : ''}}>{{$payment_type}}</option>
                @endforeach
            </select>
        </div>

        <div class="col-lg-3 mb-lg-0 mb-6">
            <label>User Type:</label>
            <select class="form-control filter-field select2" name="type" id="search_type">
                <option value="" {{isset($filters['type']) && $filters['type'] == '' ? 'selected' : ''}}>All</option>
                <option value="system" {{isset($filters['type']) && $filters['type'] == 'system' ? 'selected' : ''}}>System</option>
                <option value="application" {{isset($filters['type']) && $filters['type'] == 'application' ? 'selected' : ''}}>Application</option>
            </select>
        </div>
        @if(\Illuminate\Support\Facades\Gate::allows("view_inactive_paymentmodes"))
            <div class="col-lg-3 mb-lg-0 mb-6">
                <label>Status:</label>
                <select class="form-control filter-field select2" name="status" id="search_status">
                    <option value="" {{isset($filters['status']) && $filters['status'] == '' ? 'selected' : ''}}>All</option>
                    <option value="1" {{isset($filters['status']) && $filters['status'] == '1' ? 'selected' : ''}}>Active</option>
                    <option value="0" {{isset($filters['status']) && $filters['status'] == '2' ? 'selected' : ''}}>Inactive</option>
                </select>
            </div>
        @endif
    </div>
    <div class="row">
        <div class="col-md-10">

            @include('admin.partials.filter-buttons')

        </div>
    </div>
</div>


