function reInit(elem, title) {
    $(elem).select2({
        placeholder: title
    });

    KTPermissionValidation.init();
}
