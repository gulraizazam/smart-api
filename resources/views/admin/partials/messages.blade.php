
@if(isset($toastr))
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


        @if(session()->has('success'))
        toastr.success("{{session('success')}}");
    @endif
    @if(session()->has('error'))
        toastr.error("{{session('error')}}");
    @endif

    @if(session()->has('warning'))
        toastr.warning("{{session('warning')}}");
    @endif
    @if(session()->has('info'))
    toastr.info("{{session('info')}}");
    @endif
</script>
@endif

@if(isset($message))
    @if(session()->has('error'))
        <div class="alert alert-danger" role="alert">
            <i class="fa fa-exclamation-circle"></i>
            <b>Alert: </b> {{session('error')}}
        </div>
    @endif
    @if(session()->has('success'))
        <div class="alert alert-success" role="alert">
            <i class="fa fa-check-square-o"></i>
            <b>Success: </b> {{session('success')}}
        </div>
    @endif
@endif
