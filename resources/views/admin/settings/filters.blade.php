@php
    $display = 'none;';
    $advance_class = 'fa-caret-right';
if(isset($filters)) {
        if(hasFilter($filters, 'name')
            || hasFilter($filters, 'data')) {
            $display = 'block;';
            $advance_class = 'fa-caret-down';
        }
    }
@endphp

<div class="mt-2 mb-7">

    <div class="row align-items-center">

        <div class="col-lg-12 col-xl-12">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <label>Name:</label>
                    <input type="text" value="{{$filters['setting_name'] ?? ''}}" class="form-control filter-field" placeholder="Name" id="search_name" />
                </div>

                <div class="col-md-3">
                    <label>Data:</label>
                    <input type="text" value="{{$filters['setting_data'] ?? ''}}" class="form-control filter-field" placeholder="Data" id="search_data" />
                </div>
                <div class="col-md-3 mt-10">

                    @include('admin.partials.filter-buttons')
                </div>
            </div>
        </div>

    </div>

</div>
