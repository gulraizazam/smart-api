/**
 * Google Reviews Admin Page
 * Simple grid with immediate save per doctor
 */
(function () {
    'use strict';

    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    csrfToken = csrfToken ? csrfToken.getAttribute('content') : '';

    function init() {
        bindEvents();
        loadData();
    }

    function bindEvents() {
        document.getElementById('grMonth').addEventListener('change', loadData);
        document.getElementById('grYear').addEventListener('change', loadData);
    }

    function loadData() {
        var month = document.getElementById('grMonth').value;
        var year = document.getElementById('grYear').value;
        var url = GR_CONFIG.routes.data + '?month=' + month + '&year=' + year;

        var tbody = document.getElementById('grTableBody');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-8">Loading...</td></tr>';

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.status && res.data && res.data.grid) {
                renderGrid(res.data.grid, res.data.month, res.data.year);
            } else {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-8">No data available</td></tr>';
            }
        })
        .catch(function (err) {
            console.error('Google Reviews load error:', err);
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-8">Error loading data</td></tr>';
        });
    }

    function renderGrid(grid, month, year) {
        var tbody = document.getElementById('grTableBody');

        if (!grid.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-8">No active doctors found</td></tr>';
            return;
        }

        var html = '';
        grid.forEach(function (row) {
            html += '<tr data-doctor-id="' + row.doctor_id + '">' +
                '<td class="pl-4 font-weight-bold">' + escHtml(row.doctor_name) + '</td>' +
                '<td class="text-muted">' + escHtml(row.locations) + '</td>' +
                '<td>' +
                    '<input type="number" class="form-control form-control-sm gr-count-input" ' +
                    'value="' + row.review_count + '" min="0" style="width: 100px;" ' +
                    'data-doctor-id="' + row.doctor_id + '" ' +
                    'data-month="' + month + '" data-year="' + year + '" ' +
                    'data-original="' + row.review_count + '">' +
                '</td>' +
                '<td><span class="gr-status" id="grStatus_' + row.doctor_id + '"></span></td>' +
                '</tr>';
        });

        tbody.innerHTML = html;

        // Bind change events on inputs (immediate save on blur or Enter)
        document.querySelectorAll('.gr-count-input').forEach(function (input) {
            input.addEventListener('change', function () { saveReview(this); });
            input.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.blur();
                }
            });
        });
    }

    function saveReview(input) {
        var doctorId = input.dataset.doctorId;
        var month = input.dataset.month;
        var year = input.dataset.year;
        var count = parseInt(input.value) || 0;
        var original = parseInt(input.dataset.original) || 0;

        // Skip if value hasn't changed
        if (count === original) return;

        var statusEl = document.getElementById('grStatus_' + doctorId);
        statusEl.innerHTML = '<i class="la la-spinner la-spin text-primary"></i>';

        fetch(GR_CONFIG.routes.save, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                doctor_id: doctorId,
                month: month,
                year: year,
                review_count: count
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.status) {
                statusEl.innerHTML = '<i class="la la-check-circle text-success"></i>';
                input.dataset.original = count;
                setTimeout(function () { statusEl.innerHTML = ''; }, 2000);
            } else {
                statusEl.innerHTML = '<i class="la la-times-circle text-danger"></i>';
                input.value = original;
            }
        })
        .catch(function (err) {
            console.error('Save error:', err);
            statusEl.innerHTML = '<i class="la la-times-circle text-danger"></i>';
            input.value = original;
        });
    }

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
