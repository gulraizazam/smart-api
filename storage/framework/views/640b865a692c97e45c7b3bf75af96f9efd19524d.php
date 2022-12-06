<!DOCTYPE html>
<html lang="en">
<!--begin::Head-->
<head>
    <title>Cutera Aesthetics</title>
    <meta charset="utf-8" />
    <meta name="description" content="Smart Aesthetic" />
    <meta name="keywords" content="Smart Aesthetic" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="shortcut icon" href="<?php echo e(asset('favicon.ico')); ?>" />
    <link rel="shortcut icon" href="https://cuteraesthetics.com/wp-content/themes/cutera/assets/img/favicon.png" />
    <!--begin::Fonts-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <!--end::Fonts-->
    <!--begin::Global Stylesheets Bundle(used by all pages)-->
    <link href="<?php echo e(asset('assets/css/auth/plugins.bundle.css')); ?>" rel="stylesheet" type="text/css" />
    <link href="<?php echo e(asset('assets/css/auth/style.bundle.css')); ?>" rel="stylesheet" type="text/css" />
    <!--end::Global Stylesheets Bundle-->
</head>
<!--end::Head-->
<!--begin::Body-->
<body id="kt_body" class="bg-body">
<!--begin::Main-->

<?php echo $__env->yieldContent('content'); ?>

<!--end::Main-->

<!--begin::Javascript-->
<!--begin::Global Javascript Bundle(used by all pages)-->
<script src="<?php echo e(asset('assets/js/auth/plugins.bundle.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/auth/scripts.bundle.js')); ?>"></script>
<!--end::Global Javascript Bundle-->
<!--begin::Page Custom Javascript(used by this page)-->
<script src="<?php echo e(asset('assets/js/auth/general.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/auth/password-reset.js')); ?>"></script>
<!--end::Page Custom Javascript-->
<!--end::Javascript-->

<?php echo $__env->make('admin.partials.messages', ['toaster' => true], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

</body>
<!--end::Body-->
</html>
<?php /**PATH /var/www/html/resources/views/layouts/app.blade.php ENDPATH**/ ?>