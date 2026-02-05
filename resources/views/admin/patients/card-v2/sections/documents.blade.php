{{-- Documents Section --}}
<div class="section-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Documents</h4>
        <a href="javascript:void(0);" onclick="addDocumentForm({{ $patientId }}); $('#modal_add_document_form').modal('show');" class="btn btn-primary btn-sm">
            <i class="la la-plus"></i> Add Document
        </a>
    </div>
    
    {{-- Datatable --}}
    <div class="page-document-form">
        <div class="datatable datatable-bordered datatable-head-custom document-form"></div>
    </div>
</div>

{{-- Add Document Modal --}}
<div class="modal fade" id="modal_add_document_form" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered form-popup" id="documents_form">
        @include('admin.patients.card.documents.create')
    </div>
</div>

{{-- Edit Document Modal --}}
<div class="modal fade" id="modal_edit_document_form" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered form-popup" id="edit_documents_form">
        @include('admin.patients.card.documents.edit')
    </div>
</div>
