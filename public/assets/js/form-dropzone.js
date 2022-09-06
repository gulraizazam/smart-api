Dropzone.autoDiscover = false;

var myDropzoneTheFirst = new Dropzone(
    '#a-form-element', {
        // Prevents Dropzone from uploading dropped files immediately
        autoProcessQueue: false,
        maxFilesize: 10, // MB
        parallelUploads: 10,
        uploadMultiple: true,
        addRemoveLinks: true,

        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        init: function() {
            var submitButton = document.querySelector("#submit-all-1")
            myDropzone = this; // closure

            submitButton.addEventListener("click", function() {
                myDropzone.processQueue(); // Tell Dropzone to process all queued files.
            });
            // You might want to show the submit button only when
            // files are dropped here:
            this.on("addedfile", function() {
                // Show submit button here and/or inform user to click it.
            });
            this.on("complete", function (file) {
                if (this.getUploadingFiles().length === 0 && this.getQueuedFiles().length === 0) {
                    toastr.success("Image uploaded successfully!");
                    reInitTable();
                }
            });


        }
    }
);


