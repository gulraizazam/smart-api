
@push('js')

    <script>

        $(function () {
            $(".user-setting").click(function () {
                $(".user-popup").slideToggle();
            });

            $(document).on('click', function(e) {
                var container = $(".user-setting");
                if (!$(e.target).closest(container).length) {
                    $(".user-popup").hide();
                }
            });
        });

    </script>
@endpush
