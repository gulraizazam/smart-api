<div class="card-body page-document-form">
    <!--begin::Search Form-->
@include('admin.patients.card.documents.filters')
<!--end::Search Form-->

    <!--begin: Datatable-->
    <div class="datatable datatable-bordered datatable-head-custom document-form"></div>
    <!--end: Datatable-->

    <div class="modal fade" id="modal_add_document_form" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="documents_form">

            @include('admin.patients.card.documents.create')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_edit_document_form" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="edit_documents_form">

            @include('admin.patients.card.documents.edit')

        </div>
        <!--end::Modal dialog-->
    </div>


</div>
