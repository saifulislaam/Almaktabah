/**
 * AlMaktabah — admin.js
 * Chart.js rendering for the dashboard/analytics pages, the mobile
 * sidebar toggle, live "visitors online now" polling, and bulk
 * select-all/row-action helpers for the book/user tables.
 */
(function () {
    'use strict';

    /* ---------------- Mobile sidebar ---------------- */
    const sidebar = document.querySelector('.admin-sidebar');
    document.querySelectorAll('[data-admin-menu-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () { if (sidebar) sidebar.classList.toggle('open'); });
    });

    /* ---------------- Live visitor count polling ---------------- */
    const liveEl = document.querySelector('[data-live-visitors]');
    if (liveEl) {
        function poll() {
            fetch(window.ALMAKTABAH.baseUrl + 'api/visitor-count.php')
                .then(function (r) { return r.json(); })
                .then(function (d) { liveEl.textContent = d.count; })
                .catch(function () {});
        }
        poll();
        setInterval(poll, 8000);
    }

    /* ---------------- Bulk select-all ---------------- */
    document.querySelectorAll('[data-select-all]').forEach(function (master) {
        const table = master.closest('table');
        master.addEventListener('change', function () {
            table.querySelectorAll('[data-row-select]').forEach(function (cb) { cb.checked = master.checked; });
            toggleBulkBar(table);
        });
    });
    document.querySelectorAll('[data-row-select]').forEach(function (cb) {
        cb.addEventListener('change', function () { toggleBulkBar(cb.closest('table')); });
    });
    function toggleBulkBar(table) {
        const bar = table.parentElement.querySelector('.bulk-bar');
        if (!bar) return;
        const anyChecked = table.querySelectorAll('[data-row-select]:checked').length > 0;
        bar.style.display = anyChecked ? 'flex' : 'none';
        const countEl = bar.querySelector('[data-bulk-count]');
        if (countEl) countEl.textContent = table.querySelectorAll('[data-row-select]:checked').length;
    }

    /* Collect checked ids into a hidden input before submitting a bulk-action form */
    document.querySelectorAll('[data-bulk-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            const table = document.querySelector(form.getAttribute('data-bulk-form'));
            const ids = Array.from(table.querySelectorAll('[data-row-select]:checked')).map(function (cb) { return cb.value; });
            form.querySelector('input[name="ids"]').value = ids.join(',');
        });
    });

    /* ---------------- Confirm-before-destructive-action ---------------- */
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!window.confirm(el.getAttribute('data-confirm'))) e.preventDefault();
        });
    });

    /* ---------------- Charts (Chart.js via CDN) ---------------- */
    if (window.ADMIN_CHARTS) {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.3/chart.umd.min.js';
        script.onload = renderCharts;
        document.head.appendChild(script);
    }

    function chartTheme() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark' ||
                     (document.documentElement.getAttribute('data-theme') === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        return {
            grid: dark ? 'rgba(237,230,214,0.08)' : 'rgba(35,49,58,0.08)',
            text: dark ? '#9FB3B9' : '#5B6B72',
            primary: dark ? '#7FB8C9' : '#1B4A5A',
            accent: '#C89B5C',
        };
    }

    function renderCharts() {
        const t = chartTheme();
        Chart.defaults.font.family = 'Manrope, sans-serif';
        Chart.defaults.color = t.text;

        const trafficEl = document.getElementById('chart-traffic');
        if (trafficEl && window.ADMIN_CHARTS.traffic) {
            new Chart(trafficEl, {
                type: 'line',
                data: {
                    labels: window.ADMIN_CHARTS.traffic.labels,
                    datasets: [{
                        label: 'Visitors', data: window.ADMIN_CHARTS.traffic.values,
                        borderColor: t.primary, backgroundColor: t.primary + '22',
                        tension: 0.35, fill: true, pointRadius: 0,
                    }]
                },
                options: { plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { grid: { color: t.grid }, beginAtZero: true } } }
            });
        }

        const categoryEl = document.getElementById('chart-categories');
        if (categoryEl && window.ADMIN_CHARTS.categories) {
            new Chart(categoryEl, {
                type: 'doughnut',
                data: {
                    labels: window.ADMIN_CHARTS.categories.labels,
                    datasets: [{ data: window.ADMIN_CHARTS.categories.values, backgroundColor: ['#1B4A5A', '#C89B5C', '#1E5C4F', '#8E6529', '#2D6577', '#B5473F', '#DDBB85', '#5B6B72'] }]
                },
                options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } }
            });
        }

        const uploadsEl = document.getElementById('chart-uploads');
        if (uploadsEl && window.ADMIN_CHARTS.uploads) {
            new Chart(uploadsEl, {
                type: 'bar',
                data: { labels: window.ADMIN_CHARTS.uploads.labels, datasets: [{ label: 'Uploads', data: window.ADMIN_CHARTS.uploads.values, backgroundColor: t.accent, borderRadius: 4 }] },
                options: { plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { grid: { color: t.grid }, beginAtZero: true, ticks: { precision: 0 } } } }
            });
        }

        const growthEl = document.getElementById('chart-growth');
        if (growthEl && window.ADMIN_CHARTS.growth) {
            new Chart(growthEl, {
                type: 'line',
                data: { labels: window.ADMIN_CHARTS.growth.labels, datasets: [{ label: 'New users', data: window.ADMIN_CHARTS.growth.values, borderColor: t.accent, backgroundColor: 'transparent', tension: 0.35, pointRadius: 0 }] },
                options: { plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { grid: { color: t.grid }, beginAtZero: true, ticks: { precision: 0 } } } }
            });
        }
    }
})();
