
<?php if(isset($toastr)): ?>
    <script>

        toastr.options = {
            "closeButton": true,
            "newestOnTop": false,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": false,
            "showDuration": "300",
            "hideDuration": "2000",
            "timeOut": "6000",
            "extendedTimeOut": "6000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };


        <?php if(session()->has('success')): ?>
        toastr.success("<?php echo e(session('success')); ?>");
    <?php endif; ?>
    <?php if(session()->has('error')): ?>
        toastr.error("<?php echo e(session('error')); ?>");
    <?php endif; ?>

    <?php if(session()->has('warning')): ?>
        toastr.warning("<?php echo e(session('warning')); ?>");
    <?php endif; ?>
    <?php if(session()->has('info')): ?>
    toastr.info("<?php echo e(session('info')); ?>");
    <?php endif; ?>
</script>
<?php endif; ?>

<?php if(isset($message)): ?>
    <?php if(session()->has('error')): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fa fa-exclamation-circle"></i>
            <b>Alert: </b> <?php echo e(session('error')); ?>

        </div>
    <?php endif; ?>
    <?php if(session()->has('success')): ?>
        <div class="alert alert-success" role="alert">
            <i class="fa fa-check-square-o"></i>
            <b>Success: </b> <?php echo e(session('success')); ?>

        </div>
    <?php endif; ?>
<?php endif; ?>
<?php /**PATH /var/www/cuterav2.test/resources/views/admin/partials/messages.blade.php ENDPATH**/ ?>