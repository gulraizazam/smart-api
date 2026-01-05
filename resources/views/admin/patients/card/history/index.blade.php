<div class="card-body page-history-form">
    <h4 class="mb-5">Patient Activity Logs</h4>
    
    <div id="activity-timeline-container">
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Loading activity history...</p>
        </div>
    </div>
    
    <div id="activity-timeline" class="timeline-container" style="display: none;">
        <!-- Activities will be loaded here via JS -->
    </div>
    
    <div id="no-activities" class="text-center py-5" style="display: none;">
        <i class="la la-history text-muted" style="font-size: 48px;"></i>
        <p class="mt-2 text-muted">No activity history found for this patient.</p>
    </div>
</div>

<style>
.timeline-container {
    position: relative;
    padding-left: 30px;
}

.timeline-container::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e4e6ef;
}

.timeline-item {
    position: relative;
    padding-bottom: 20px;
    padding-left: 25px;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -24px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #f64e60;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #f64e60;
}

.timeline-item.lead-created::before { background: #8950fc; box-shadow: 0 0 0 2px #8950fc; }
.timeline-item.consultation-booked::before { background: #ffa800; box-shadow: 0 0 0 2px #ffa800; }
.timeline-item.lead-booked::before { background: #1bc5bd; box-shadow: 0 0 0 2px #1bc5bd; }
.timeline-item.package-created::before { background: #3699ff; box-shadow: 0 0 0 2px #3699ff; }
.timeline-item.service-added::before { background: #f64e60; box-shadow: 0 0 0 2px #f64e60; }
.timeline-item.payment-made::before { background: #1bc5bd; box-shadow: 0 0 0 2px #1bc5bd; }
.timeline-item.consultation-converted::before { background: #ffa800; box-shadow: 0 0 0 2px #ffa800; }
.timeline-item.lead-converted::before { background: #8950fc; box-shadow: 0 0 0 2px #8950fc; }
.timeline-item.treatment-booked::before { background: #3699ff; box-shadow: 0 0 0 2px #3699ff; }
.timeline-item.treatment-arrived::before { background: #1bc5bd; box-shadow: 0 0 0 2px #1bc5bd; }

.timeline-time {
    font-size: 12px;
    color: #b5b5c3;
    font-weight: 500;
    margin-bottom: 5px;
}

.timeline-content {
    font-size: 14px;
    color: #3f4254;
    line-height: 1.6;
}

.timeline-content .highlight {
    color: #3699ff;
    font-weight: 500;
}

.timeline-content .highlight-orange {
    color: #ffa800;
    font-weight: 500;
}

.timeline-content .highlight-green {
    color: #1bc5bd;
    font-weight: 500;
}

.timeline-content .highlight-purple {
    color: #8950fc;
    font-weight: 500;
}

.timeline-content .location {
    color: #7e8299;
}
</style>
