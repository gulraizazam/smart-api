(function () {
    'use strict';

    let currentRole = 'doctor';

    async function load() {
        try {
            const params = { ...window.MD_FILTERS.toParams(), role: currentRole };
            const resp = await window.MD_API.get(window.MD_CONFIG.routes.people, params);
            if (resp && resp.data) render(resp.data);
        } catch (e) { console.error('people fetch failed', e); }
    }

    function render(data) {
        const tbody = document.getElementById('mdPeopleTbody');
        if (!tbody) return;
        const rows = (data.rows || []).map((r) => `
            <tr data-resource-id="${r.resource_id}" data-resource-type="${r.resource_type}">
                <td>${escapeHtml(r.resource_name)}</td>
                <td>${window.MD_CHARTS.currency(r.revenue)}</td>
                <td>${window.MD_CHARTS.percent(r.conversion_rate)}</td>
                <td>${r.feedback_score ? r.feedback_score.toFixed(1) : '—'}</td>
            </tr>`).join('');
        tbody.innerHTML = rows || '<tr><td colspan="4" class="md-empty">No people in scope</td></tr>';
    }

    function escapeHtml(s) { return String(s).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }

    function wireSegmented() {
        document.querySelectorAll('#mdPeopleSegmented button').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#mdPeopleSegmented button').forEach((b) => b.classList.remove('active'));
                btn.classList.add('active');
                currentRole = btn.dataset.role || 'doctor';
                load();
            });
        });
    }

    document.addEventListener('md:section-ready', (e) => { if (e.detail.section === 'people') { wireSegmented(); load(); } });
    document.addEventListener('md:filter-changed', () => { if (document.getElementById('mdSectionPeople')) load(); });
})();
