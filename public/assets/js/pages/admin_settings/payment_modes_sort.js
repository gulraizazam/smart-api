"use strict";
var KTCardDraggable = function () {
    return {
        //main function to initiate the module
        init: function () {
            let containers = document.querySelectorAll('.draggable-zone');
            if (containers.length === 0) {
                return false;
            }
            let swappable = new Sortable.default(containers, {
                draggable: '.draggable',
                handle: '.draggable .draggable-handle',
                mirror: {
                    //appendTo: selector,
                    appendTo: 'body',
                    constrainDimensions: true
                }
            });
            swappable.on('drag:stop', () => {

                let page_id_array = new Array();
                setTimeout(function () {
                    $('#draggable-zone').children('.element-draggable').each(function (e) {
                        page_id_array.push($(this).attr("id"));
                    });

                   let result = arraysAreIdentical(sortOrder, page_id_array)
                   if (result) {
                       return false;
                   }
                    sortOrder = page_id_array;

                    $.ajax({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        url: route('admin.payment_modes.sort_save'),
                        method:"POST",
                        data:{item_ids:page_id_array},
                        success:function(data)
                        {
                            toastr.success(data.message);
                        }
                    });
                }, 10)
            });

        }
    };
}();

function arraysAreIdentical(arr1, arr2) {
    if (arr1.length !== arr2.length) return false;
    for (var i = 0, len = arr1.length; i < len; i++){
        if (arr1[i] != arr2[i]){
            return false;
        }
    }
    return true;
}

var sortOrder = [];
jQuery(document).ready(function () {
    $.ajax({
        url: route('admin.payment_modes.sort_get'),
        method: "get",
        success: function (response) {
            response.data.forEach(function (value, index) {
                sortOrder.push(value.id)
                $('#draggable-zone').append(dragAbleField(value.id, value.name));
            });
        }
    });
    KTCardDraggable.init();
});


function dragAbleField(id, title) {
    return `
    <div class="card border border-secondary card-custom element-draggable gutter-b draggable" id="` + id + `">
        <div class="card-header draggable-handle">
            <div class="card-toolbar">
                <h3 class="card-label">
                    <a href="#" class="btn btn-icon btn-sm btn-hover-light-primary">
                        <i class="ki ki-menu"></i>
                    </a> ` + title + `
                </h3>
            </div>
        </div>
    </div>
    `;
}
