<div class="card card-custom mt-5">
    <div class="card-header border-0">
        <div class="card-title">
            <h3 class="card-label">
                <i class="la la-sticky-note text-primary mr-2"></i>
                Internal Notes
                <small class="text-muted ml-2">Staff comments</small>
            </h3>
        </div>
    </div>
    <div class="card-body pt-0">
        <!-- Add Note Form -->
        <div class="mb-4">
            <div class="d-flex">
                <textarea id="new_note_text" class="form-control" rows="2" placeholder="Add a note about this patient..." style="resize: none;"></textarea>
                <button type="button" class="btn btn-primary ml-3" onclick="addPatientNote();" style="height: fit-content; align-self: center;">
                    <i class="la la-plus"></i> Add
                </button>
            </div>
        </div>
        
        <!-- Notes List -->
        <div id="patient_notes_list">
            <div class="text-center text-muted py-4">
                <i class="la la-spinner la-spin"></i> Loading notes...
            </div>
        </div>
    </div>
</div>

<!-- Edit Note Modal -->
<div class="modal fade" id="edit_note_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Note</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit_note_id">
                <textarea id="edit_note_text" class="form-control" rows="4" placeholder="Enter note..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveEditedNote();">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<style>
    .note-item {
        border-left: 3px solid #E4E6EF;
        padding: 12px 15px;
        margin-bottom: 10px;
        background: #F9FAFB;
        border-radius: 0 8px 8px 0;
        transition: all 0.2s ease;
    }
    .note-item:hover {
        background: #F3F6F9;
    }
    .note-item.pinned {
        border-left-color: #FFA800;
        background: #FFF8E1;
    }
    .note-item .note-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .note-item .note-meta {
        font-size: 12px;
        color: #B5B5C3;
    }
    .note-item .note-content {
        color: #3F4254;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .note-item .note-actions {
        opacity: 0;
        transition: opacity 0.2s;
    }
    .note-item:hover .note-actions {
        opacity: 1;
    }
    .note-item .pin-badge {
        font-size: 10px;
        padding: 2px 6px;
    }
</style>

<script>
// Current user info for permission checks
var currentUserId = {{ Auth::id() }};
var isSuperAdmin = {{ Auth::user()->hasRole('Super Admin') || Gate::allows('users_manage') ? 'true' : 'false' }};

// Load patient notes
function loadPatientNotes() {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: '/api/patients/' + patientCardID + '/notes',
        type: 'GET',
        success: function(response) {
            if (response.status && response.data) {
                renderNotes(response.data);
            } else {
                $('#patient_notes_list').html('<div class="text-center text-muted py-4">No notes yet</div>');
            }
        },
        error: function() {
            $('#patient_notes_list').html('<div class="text-center text-danger py-4">Failed to load notes</div>');
        }
    });
}

// Check if current user can edit/delete a note
function canEditNote(noteCreatorId) {
    return isSuperAdmin || (currentUserId == noteCreatorId);
}

// Render notes list
function renderNotes(notes) {
    if (!notes || notes.length === 0) {
        $('#patient_notes_list').html('<div class="text-center text-muted py-4"><i class="la la-sticky-note" style="font-size: 32px;"></i><p class="mt-2">No notes yet. Add the first note above.</p></div>');
        return;
    }
    
    let html = '';
    notes.forEach(function(note) {
        let pinnedClass = note.is_pinned ? 'pinned' : '';
        let pinnedBadge = note.is_pinned ? '<span class="badge badge-warning pin-badge mr-2"><i class="la la-thumbtack"></i> Pinned</span>' : '';
        let pinIcon = note.is_pinned ? 'la-thumbtack text-warning' : 'la-thumbtack text-muted';
        let pinTitle = note.is_pinned ? 'Unpin note' : 'Pin note';
        let createdAt = moment(note.created_at).format('MMM DD, YYYY h:mm A');
        let creatorName = note.creator ? note.creator.name : 'Unknown';
        let noteCreatorId = note.created_by;
        let canEdit = canEditNote(noteCreatorId);
        
        html += '<div class="note-item ' + pinnedClass + '" data-note-id="' + note.id + '">';
        html += '    <div class="note-header">';
        html += '        <div class="note-meta">';
        html += '            ' + pinnedBadge;
        html += '            <i class="la la-user mr-1"></i><strong>' + creatorName + '</strong>';
        html += '            <span class="mx-2">•</span>';
        html += '            <i class="la la-clock mr-1"></i>' + createdAt;
        html += '        </div>';
        html += '        <div class="note-actions">';
        html += '            <button type="button" class="btn btn-sm btn-icon btn-light-warning" onclick="togglePinNote(' + note.id + ');" title="' + pinTitle + '">';
        html += '                <i class="la ' + pinIcon + '"></i>';
        html += '            </button>';
        if (canEdit) {
            html += '            <button type="button" class="btn btn-sm btn-icon btn-light-primary ml-1" onclick="openEditNoteModal(' + note.id + ', `' + escapeHtml(note.note).replace(/`/g, '\\`') + '`);" title="Edit note">';
            html += '                <i class="la la-pencil"></i>';
            html += '            </button>';
            html += '            <button type="button" class="btn btn-sm btn-icon btn-light-danger ml-1" onclick="deletePatientNote(' + note.id + ');" title="Delete note">';
            html += '                <i class="la la-trash"></i>';
            html += '            </button>';
        }
        html += '        </div>';
        html += '    </div>';
        html += '    <div class="note-content">' + escapeHtml(note.note) + '</div>';
        html += '</div>';
    });
    
    $('#patient_notes_list').html(html);
}

// Add new note
function addPatientNote() {
    let noteText = $('#new_note_text').val().trim();
    if (!noteText) {
        toastr.warning('Please enter a note');
        return;
    }
    
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: '/api/patients/' + patientCardID + '/notes',
        type: 'POST',
        data: { note: noteText },
        success: function(response) {
            if (response.status) {
                toastr.success('Note added successfully');
                $('#new_note_text').val('');
                loadPatientNotes();
            } else {
                toastr.error(response.message || 'Failed to add note');
            }
        },
        error: function(xhr) {
            let message = 'Failed to add note';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            toastr.error(message);
        }
    });
}

// Delete note
function deletePatientNote(noteId) {
    swal.fire({
        title: 'Are you sure?',
        text: 'This note will be permanently deleted.',
        icon: 'warning',
        buttonsStyling: false,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        showCancelButton: true,
        cancelButtonClass: 'btn btn-secondary font-weight-bold',
        confirmButtonClass: 'btn btn-danger font-weight-bold'
    }).then(function(result) {
        if (result.value) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: '/api/patients/' + patientCardID + '/notes/' + noteId,
                type: 'DELETE',
                success: function(response) {
                    if (response.status) {
                        toastr.success('Note deleted');
                        loadPatientNotes();
                    } else {
                        toastr.error(response.message || 'Failed to delete note');
                    }
                },
                error: function(xhr) {
                    let message = 'Failed to delete note';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    toastr.error(message);
                }
            });
        }
    });
}

// Open edit note modal
function openEditNoteModal(noteId, noteText) {
    $('#edit_note_id').val(noteId);
    $('#edit_note_text').val(noteText);
    $('#edit_note_modal').modal('show');
}

// Save edited note
function saveEditedNote() {
    let noteId = $('#edit_note_id').val();
    let noteText = $('#edit_note_text').val().trim();
    
    if (!noteText) {
        toastr.warning('Please enter a note');
        return;
    }
    
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: '/api/patients/' + patientCardID + '/notes/' + noteId,
        type: 'PUT',
        data: { note: noteText },
        success: function(response) {
            if (response.status) {
                toastr.success('Note updated successfully');
                $('#edit_note_modal').modal('hide');
                loadPatientNotes();
            } else {
                toastr.error(response.message || 'Failed to update note');
            }
        },
        error: function(xhr) {
            let message = 'Failed to update note';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            toastr.error(message);
        }
    });
}

// Toggle pin status
function togglePinNote(noteId) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: '/api/patients/' + patientCardID + '/notes/' + noteId + '/toggle-pin',
        type: 'POST',
        success: function(response) {
            if (response.status) {
                toastr.success(response.message);
                loadPatientNotes();
            } else {
                toastr.error(response.message || 'Failed to update note');
            }
        },
        error: function() {
            toastr.error('Failed to update note');
        }
    });
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    let div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Load notes when profile tab is shown
$(document).ready(function() {
    // Load notes after patient data is loaded
    // Check every 200ms until patientCardID is available, max 10 attempts
    var attempts = 0;
    var checkInterval = setInterval(function() {
        attempts++;
        if (typeof patientCardID !== 'undefined' && patientCardID) {
            clearInterval(checkInterval);
            loadPatientNotes();
        } else if (attempts >= 10) {
            clearInterval(checkInterval);
            $('#patient_notes_list').html('<div class="text-center text-muted py-4">Unable to load notes</div>');
        }
    }, 200);
});
</script>
