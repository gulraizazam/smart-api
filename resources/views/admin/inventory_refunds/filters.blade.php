<div class="mt-2 mb-7">

    <div class="row mb-6">

       <div class="col-lg-6 mb-lg-0 mb-6">
            <label>Patient:</label>
            <select class="form-control filter-field select2 patient_id" name="search_patient_id"  id="search_patient_id">

            </select>
        </div>

        <div class="col-lg-6 mb-lg-0 mb-6">
            <label>Product:</label>
            <select class="form-control filter-field select2 product_id" name="search_product_id"  id="search_product_id">

            </select>
        </div>
    </div>
    <div class="row">
        <div class="col-md-10">

            @include('admin.partials.filter-buttons')

        </div>
    </div>
</div>


