{{-- People: split pane (list left, detail right) + segmented role control + outlier filter --}}
<section class="md-section md-section-people" id="mdSectionPeople" data-section="people">
    <div class="md-panel-head md-people-head">
        <div class="md-segmented" id="mdPeopleSegmented">
            <button type="button" class="active" data-role="doctor">Doctors</button>
            <button type="button" data-role="therapist">Therapists</button>
            <button type="button" data-role="consultant">Consultants</button>
        </div>
        <label class="md-check">
            <input type="checkbox" id="mdPeopleOutliers">
            <span>Outliers only (±2σ)</span>
        </label>
    </div>

    <div class="md-split">
        <aside class="md-split-left">
            <div class="md-table-wrap">
                <table class="md-table md-table-compact" id="mdPeopleTable">
                    <thead>
                        <tr>
                            <th>Resource</th>
                            <th>Revenue</th>
                            <th>Conv %</th>
                            <th>Feedback</th>
                        </tr>
                    </thead>
                    <tbody id="mdPeopleTbody"></tbody>
                </table>
            </div>
        </aside>

        <main class="md-split-right" id="mdPeopleDetail">
            <div class="md-empty-state">
                <i class="la la-user"></i>
                <span>Select a person to see their detail</span>
            </div>
        </main>
    </div>
</section>
