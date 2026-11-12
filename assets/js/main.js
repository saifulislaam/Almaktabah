/**
 * AlMaktabah — main.js
 * Theme toggle, mobile drawer + mobile search, dropdowns, live search
 * suggestions, wishlist/favorite-category toggles, and toast helper.
 * Vanilla JS, no build step — this file is loaded directly by <script>.
 */
(function () {
    'use strict';

    const root = document.documentElement;

    /* ---------------- Theme ---------------- */
    function applyTheme(theme) {
        root.setAttribute('data-theme', theme);
        try { localStorage.setItem('almaktabah_theme', theme); } catch (e) {}
        if (window.ALMAKTABAH.loggedIn) {
            fetch(window.ALMAKTABAH.baseUrl + 'api/update-theme.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'theme=' + encodeURIComponent(theme) + '&csrf_token=' + encodeURIComponent(window.ALMAKTABAH.csrf)
            }).catch(function () {});
        }
    }

    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const current = root.getAttribute('data-theme');
            const isDark = current === 'dark' || (current === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            applyTheme(isDark ? 'light' : 'dark');
        });
    });

    document.querySelectorAll('[data-theme-option]').forEach(function (el) {
        el.addEventListener('click', function () {
            applyTheme(el.getAttribute('data-theme-option'));
            document.querySelectorAll('[data-theme-option]').forEach(function (o) { o.classList.remove('selected'); });
            el.classList.add('selected');
        });
    });

    /* ---------------- Mobile drawer (sidebar) ---------------- */
    const drawer = document.querySelector('.app-sidebar');
    const overlay = document.querySelector('.drawer-overlay');
    function closeDrawer() {
        if (drawer) drawer.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
    }
    document.querySelectorAll('[data-menu-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (drawer) drawer.classList.toggle('open');
            if (overlay) overlay.classList.toggle('open');
        });
    });
    if (overlay) overlay.addEventListener('click', closeDrawer);

    /* ---------------- Mobile search bar toggle ---------------- */
    const mobileSearchBar = document.querySelector('.mobile-search-bar');
    document.querySelectorAll('[data-mobile-search-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!mobileSearchBar) return;
            closeDrawer();
            mobileSearchBar.classList.add('open');
            const input = mobileSearchBar.querySelector('input');
            if (input) input.focus();
        });
    });
    document.querySelectorAll('[data-mobile-search-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { mobileSearchBar.classList.remove('open'); });
    });
    document.addEventListener('click', function (e) {
        if (mobileSearchBar && mobileSearchBar.classList.contains('open') &&
            !mobileSearchBar.contains(e.target) && !e.target.closest('[data-mobile-search-toggle]')) {
            mobileSearchBar.classList.remove('open');
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && mobileSearchBar) mobileSearchBar.classList.remove('open');
    });

    /* ---------------- Generic dropdown (notifications, user menu) ---------------- */
    document.querySelectorAll('[data-dropdown-trigger]').forEach(function (trigger) {
        const panelSelector = trigger.getAttribute('data-dropdown-trigger');
        const panel = document.querySelector(panelSelector);
        if (!panel) return;
        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            const wasOpen = panel.classList.contains('open');
            document.querySelectorAll('.notif-panel.open, .user-dropdown.open, .lang-panel.open').forEach(function (p) { p.classList.remove('open'); });
            if (!wasOpen) panel.classList.add('open');
        });
    });
    document.addEventListener('click', function () {
        document.querySelectorAll('.notif-panel.open, .user-dropdown.open, .lang-panel.open').forEach(function (p) { p.classList.remove('open'); });
    });

    /* ---------------- Live search suggestions ---------------- */
    let searchTimer = null;
    document.querySelectorAll('[data-live-search]').forEach(function (input) {
        const box = input.closest('form').parentElement.querySelector('.search-suggestions');
        if (!box) return;

        input.addEventListener('input', function () {
            const q = input.value.trim();
            clearTimeout(searchTimer);
            if (q.length < 2) { box.classList.remove('open'); box.innerHTML = ''; return; }
            searchTimer = setTimeout(function () { runSuggest(q, box); }, 250);
        });
        input.addEventListener('focus', function () {
            if (box.innerHTML.trim() !== '') box.classList.add('open');
        });
    });
    document.addEventListener('click', function (e) {
        document.querySelectorAll('.search-suggestions.open').forEach(function (box) {
            if (!box.parentElement.contains(e.target)) box.classList.remove('open');
        });
    });

    function runSuggest(query, box) {
        fetch(window.ALMAKTABAH.baseUrl + 'api/search-suggest.php?q=' + encodeURIComponent(query))
            .then(function (r) { return r.json(); })
            .then(function (data) { renderSuggestions(data, box, query); })
            .catch(function () {});
    }

    function renderSuggestions(data, box, query) {
        let html = '';
        if (data.books && data.books.length) {
            html += '<div class="sugg-group"><div class="sugg-label">Books</div>';
            data.books.forEach(function (b) {
                html += '<a class="sugg-item" href="' + window.ALMAKTABAH.baseUrl + 'book.php?id=' + b.id + '&slug=' + b.slug + '">' +
                    (b.cover_image ? '<img src="' + window.ALMAKTABAH.coverUrl + '/' + b.cover_image + '">' : '<span class="cover-fallback"></span>') +
                    '<span><span class="sugg-title">' + escapeHtml(b.title) + '</span><span class="sugg-sub">' + escapeHtml(b.author_name || '') + '</span></span></a>';
            });
            html += '</div>';
        }
        if (data.authors && data.authors.length) {
            html += '<div class="sugg-group"><div class="sugg-label">Authors</div>';
            data.authors.forEach(function (a) {
                html += '<a class="sugg-item" href="' + window.ALMAKTABAH.baseUrl + 'author.php?id=' + a.id + '&slug=' + a.slug + '"><span class="sugg-title">' + escapeHtml(a.name) + '</span></a>';
            });
            html += '</div>';
        }
        if (data.categories && data.categories.length) {
            html += '<div class="sugg-group"><div class="sugg-label">Categories</div>';
            data.categories.forEach(function (c) {
                html += '<a class="sugg-item" href="' + window.ALMAKTABAH.baseUrl + 'category.php?id=' + c.id + '&slug=' + c.slug + '"><span class="sugg-title">' + escapeHtml(c.name) + '</span></a>';
            });
            html += '</div>';
        }
        if (!html) html = '<div class="notif-empty">No matches yet — try a different search.</div>';
        box.innerHTML = html;
        box.classList.add('open');
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    /* ---------------- Wishlist toggle (AJAX) ---------------- */
    document.querySelectorAll('[data-wishlist-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!window.ALMAKTABAH.loggedIn) { window.location.href = window.ALMAKTABAH.baseUrl + 'login.php'; return; }
            const bookId = btn.getAttribute('data-wishlist-toggle');
            fetch(window.ALMAKTABAH.baseUrl + 'api/toggle-wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'book_id=' + encodeURIComponent(bookId) + '&csrf_token=' + encodeURIComponent(window.ALMAKTABAH.csrf)
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data.success) {
                    btn.classList.toggle('active', data.on_wishlist);
                    showToast(data.on_wishlist ? 'Added to your wishlist' : 'Removed from your wishlist');
                }
            }).catch(function () {});
        });
    });

    /* ---------------- Favorite category follow toggle ---------------- */
    document.querySelectorAll('[data-follow-category]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!window.ALMAKTABAH.loggedIn) { window.location.href = window.ALMAKTABAH.baseUrl + 'login.php'; return; }
            const catId = btn.getAttribute('data-follow-category');
            fetch(window.ALMAKTABAH.baseUrl + 'api/toggle-follow-category.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'category_id=' + encodeURIComponent(catId) + '&csrf_token=' + encodeURIComponent(window.ALMAKTABAH.csrf)
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data.success) {
                    btn.textContent = data.following ? 'Following' : 'Follow';
                    btn.classList.toggle('btn-primary', data.following);
                    btn.classList.toggle('btn-outline', !data.following);
                }
            });
        });
    });

    /* ---------------- Star rating input ---------------- */
    document.querySelectorAll('.star-input').forEach(function (group) {
        const hidden = group.parentElement.querySelector('input[name="rating"]');
        const buttons = group.querySelectorAll('button');
        buttons.forEach(function (b, idx) {
            b.addEventListener('click', function (e) {
                e.preventDefault();
                hidden.value = idx + 1;
                buttons.forEach(function (bb, i) { bb.classList.toggle('active', i <= idx); });
            });
        });
    });

    /* ---------------- Toast ---------------- */
    function showToast(message) {
        let stack = document.querySelector('.toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            document.body.appendChild(stack);
        }
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.textContent = message;
        stack.appendChild(toast);
        setTimeout(function () { toast.remove(); }, 3200);
    }
    window.almaktabahToast = showToast;

    /* ---------------- Flash messages -> toast ---------------- */
    document.querySelectorAll('[data-flash]').forEach(function (el) {
        showToast(el.getAttribute('data-flash'));
    });

    /* ---------------- Visitor heartbeat (feeds admin "active now") ---------------- */
    function sendHeartbeat() {
        fetch(window.ALMAKTABAH.baseUrl + 'api/track-visitor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'page=' + encodeURIComponent(location.pathname)
        }).catch(function () {});
    }
    sendHeartbeat();
    setInterval(sendHeartbeat, 45000);

    /* ---------------- Modal open/close ---------------- */
    document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const modal = document.querySelector(btn.getAttribute('data-modal-open'));
            if (modal) modal.classList.add('open');
        });
    });
    document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.closest('.modal-overlay').classList.remove('open');
        });
    });
})();
