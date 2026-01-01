<div class="card card-custom card-stretch gutter-b" style="height: 600px; overflow-y: auto;" id="activities-container">
    <!--begin::Header-->
    <div class="card-header align-items-center border-0 mt-4">
        <h3 class="card-title align-items-start flex-column">
            <span class="font-weight-bolder text-dark">Today's Activities!</span>
            <span class="text-muted mt-3 font-weight-bold font-size-sm" id="totalactivities">{{ $total_activities ?? 0 }} activities</span>
        </h3>
    </div>

    <div class="card-body pt-4">
        @if (isset($recent_activities['unauthorized']))
            <div class="text-center">
                <span>Your are not authorized</span>
            </div>
        @else
            @if (isset($recent_activities['finance_log']) && count($recent_activities['finance_log']) > 0)
                <div class="timeline timeline-6 mt-3" id="activities-timeline">
                    @include('admin.activity-items', ['finance_log' => $recent_activities['finance_log']])
                </div>
                @if ($has_more ?? false)
                <div class="text-center py-3" id="load-more-container">
                    <div id="activities-loader" style="display: none;">
                        <img src="{{ asset('assets/media/loader.gif') }}" style="width: 30px;">
                    </div>
                    <div id="load-more-trigger" data-page="{{ $current_page ?? 1 }}"></div>
                </div>
                @endif
            @else
                <div class="text-center">
                    <span style="color: #000;text-align:center;font-size: 12px;padding: 50px 0px 0px;font-family: Arial; display:block;">No Activity Found</span>
                </div>
            @endif
        @endif
    </div>
</div>
<script>
(function() {
    var activitiesPage = {{ $current_page ?? 1 }};
    var hasMore = {{ ($has_more ?? false) ? 'true' : 'false' }};
    var isLoading = false;
    var container = document.getElementById('activities-container');
    var scrollTimeout = null;
    
    if (container && hasMore) {
        container.addEventListener('scroll', function() {
            // Debounce scroll events
            if (scrollTimeout) clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(function() {
                checkAndLoadMore();
            }, 150);
        });
    }
    
    function checkAndLoadMore() {
        if (isLoading || !hasMore) return;
        
        var scrollTop = container.scrollTop;
        var scrollHeight = container.scrollHeight;
        var clientHeight = container.clientHeight;
        
        // Load more when 100px from bottom
        if (scrollTop + clientHeight >= scrollHeight - 100) {
            loadMoreActivities();
        }
    }
    
    function loadMoreActivities() {
        if (isLoading || !hasMore) return;
        isLoading = true;
        
        var loader = document.getElementById('activities-loader');
        if (loader) loader.style.display = 'block';
        
        activitiesPage++;
        
        $.ajax({
            url: route('admin.home.getactivity'),
            type: 'GET',
            data: { page: activitiesPage, type: 'today' },
            success: function(response) {
                if (response.html) {
                    $('#activities-timeline').append(response.html);
                }
                hasMore = response.has_more;
                if (!hasMore) {
                    $('#load-more-container').hide();
                }
                isLoading = false;
                if (loader) loader.style.display = 'none';
            },
            error: function() {
                isLoading = false;
                hasMore = false; // Stop trying on error
                if (loader) loader.style.display = 'none';
                $('#load-more-container').hide();
            }
        });
    }
})();
</script>
