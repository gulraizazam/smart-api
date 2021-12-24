@if(isset($toastr))
    <script>
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
