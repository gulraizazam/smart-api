(function () {
    'use strict';
    let lastKey = null;
    let lastKeyAt = 0;

    function jumpTo(section) {
        const url = window.MD_CONFIG.routes.section_urls[section];
        if (url) window.location.href = url;
    }

    document.addEventListener('keydown', (e) => {
        // Ignore typing in inputs
        const tag = (e.target.tagName || '').toLowerCase();
        if (['input', 'textarea', 'select'].includes(tag)) return;

        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            document.getElementById('mdPalette')?.removeAttribute('hidden');
            document.getElementById('mdPaletteInput')?.focus();
            return;
        }
        if (e.key === 'd') { window.jQuery?.('#mdDateRange').select2('open'); return; }
        if (e.key === '/') {
            e.preventDefault();
            document.getElementById('mdPaletteInput')?.focus();
            return;
        }

        // g-chord: g then o/p
        const now = Date.now();
        if (lastKey === 'g' && now - lastKeyAt < 1500) {
            const map = { o: 'overview', p: 'people' };
            if (map[e.key]) {
                e.preventDefault();
                jumpTo(map[e.key]);
                lastKey = null;
                return;
            }
        }
        if (e.key === 'g') {
            lastKey = 'g';
            lastKeyAt = now;
            return;
        }
        lastKey = null;
    });
})();
