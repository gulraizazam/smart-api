"use strict";
var KTCardDraggable = function () {
    return {
        //main function to initiate the module
        init: function () {
            let containers = document.querySelectorAll('.services-draggable-zone');
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
                    $('#services-draggable-zone').children('.element-draggable').each(function () {
                        page_id_array.push($(this).attr("id"));
                    });
                    $.ajax({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        url: route('admin.services.sort_save'),
                        method:"POST",
                        data:{item_ids:page_id_array},
                        success:function(data)
                        {
                            toastr.success(data.message);
                        }
                    });
                }, 1)
            });

        }
    };
}();

jQuery(document).ready(function () {
    $.ajax({
        url: route('admin.services.get_sort'),
        method: "get",
        success: function (response) {
            
            response.data.forEach(function (value, index) {
               
                $('#services-draggable-zone').append(dragAbleField(value.id, value.name,value.parent_id));
            });
        }
    });
    KTCardDraggable.init();
});


function dragAbleField(id, title,parent_id) {
    var classatr;
   if(parent_id==0){
    classatr = "card border border-secondary card-custom element-draggable gutter-b parentsrc";
   }else{
    classatr = "card border border-secondary card-custom element-draggable gutter-b draggable ml-5";
   }
    return `
    <div class="`+classatr+`" id="` + id + `">
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
