/**
 * AlMaktabah — reader.js
 * Renders PDF (pdf.js), EPUB (epub.js) and TXT files in-browser, matching
 * the site's own light/dark theme, with page-turn controls, font-size /
 * night-mode adjustments, and reading-progress sync back to the server.
 * DOC/DOCX/PPT/PPTX/MOBI/ODT/RTF are not rendered in-browser — the PHP
 * template shows the "download to view" fallback for those instead.
 */
(function () {
    'use strict';
    const cfg = window.READER_CONFIG;
    if (!cfg) return;

    const bodyEl = document.querySelector('.reader-body');
    let currentPage = cfg.lastPage || 1;
    let totalPages = 1;
    let saveTimer = null;

    function saveProgress(page, total) {
        if (!cfg.loggedIn) return;
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            fetch(cfg.baseUrl + 'api/save-progress.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'book_id=' + cfg.bookId + '&page=' + page + '&total=' + (total || 0) + '&csrf_token=' + encodeURIComponent(cfg.csrf)
            }).catch(function () {});
        }, 800);
    }

    function updateIndicator(page, total) {
        const el = document.querySelector('.page-indicator');
        if (el) el.textContent = total ? ('Page ' + page + ' of ' + total) : ('Page ' + page);
    }

    /* ================= PDF ================= */
    function initPdf() {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
        script.onload = function () {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            renderPdf();
        };
        document.head.appendChild(script);
    }

    let pdfDoc = null, pdfScale = 1.2;
    function renderPdf() {
        window.pdfjsLib.getDocument(cfg.fileUrl).promise.then(function (doc) {
            pdfDoc = doc;
            totalPages = doc.numPages;
            currentPage = Math.min(Math.max(1, currentPage), totalPages);
            renderPdfPage(currentPage);
        }).catch(function (err) {
            showReaderError('This PDF could not be loaded for online reading. Please use the download button instead.');
            console.error(err);
        });
    }

    function renderPdfPage(num) {
        pdfDoc.getPage(num).then(function (page) {
            const viewport = page.getViewport({ scale: pdfScale });
            const canvas = document.getElementById('pdf-canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            page.render({ canvasContext: ctx, viewport: viewport }).promise.then(function () {
                currentPage = num;
                updateIndicator(currentPage, totalPages);
                saveProgress(currentPage, totalPages);
                bodyEl.scrollTop = 0;
            });
        });
    }

    function pdfPrev() { if (pdfDoc && currentPage > 1) renderPdfPage(currentPage - 1); }
    function pdfNext() { if (pdfDoc && currentPage < totalPages) renderPdfPage(currentPage + 1); }
    function pdfZoom(delta) { pdfScale = Math.min(3, Math.max(0.5, pdfScale + delta)); if (pdfDoc) renderPdfPage(currentPage); }
    function pdfGoTo(num) { if (pdfDoc && num >= 1 && num <= totalPages) renderPdfPage(num); }

    /* ================= EPUB ================= */
    let epubRendition = null;
    function initEpub() {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/epubjs/dist/epub.min.js';
        script.onload = function () { renderEpub(); };
        document.head.appendChild(script);
    }

    function renderEpub() {
        const book = window.ePub(cfg.fileUrl);
        epubRendition = book.renderTo('epub-viewer', { width: '100%', height: '100%', flow: 'paginated' });
        book.ready.then(function () {
            return book.locations.generate(1200);
        }).then(function () {
            totalPages = book.locations.length() || 1;
            const startCfi = cfg.lastEpubCfi || undefined;
            epubRendition.display(startCfi);
        });
        epubRendition.on('relocated', function (location) {
            const pct = book.locations.percentageFromCfi ? book.locations.percentageFromCfi(location.start.cfi) : 0;
            currentPage = Math.max(1, Math.round(pct * totalPages));
            updateIndicator(currentPage, totalPages);
            saveProgress(currentPage, totalPages);
        });
        applyEpubTheme();
    }

    function applyEpubTheme() {
        if (!epubRendition) return;
        const dark = document.documentElement.getAttribute('data-theme') === 'dark' ||
                     (document.documentElement.getAttribute('data-theme') === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        epubRendition.themes.default({
            body: { background: dark ? '#16333F' : '#FDFBF5', color: dark ? '#EDE6D6' : '#23313A' }
        });
    }

    function epubPrev() { if (epubRendition) epubRendition.prev(); }
    function epubNext() { if (epubRendition) epubRendition.next(); }
    function epubFontSize(px) { if (epubRendition) epubRendition.themes.fontSize(px + 'px'); }

    /* ================= TXT ================= */
    function initTxt() {
        fetch(cfg.fileUrl).then(function (r) { return r.text(); }).then(function (text) {
            const el = document.getElementById('txt-viewer');
            el.textContent = text;
            totalPages = 1;
            updateIndicator(1, 1);
            bodyEl.addEventListener('scroll', function () {
                const pct = bodyEl.scrollTop / Math.max(1, (bodyEl.scrollHeight - bodyEl.clientHeight));
                saveProgress(Math.round(pct * 100) || 1, 100);
            });
        }).catch(function () {
            showReaderError('This text file could not be loaded. Please use the download button instead.');
        });
    }

    function showReaderError(msg) {
        if (bodyEl) bodyEl.innerHTML = '<div class="unsupported-format"><p>' + msg + '</p></div>';
    }

    /* ================= Font size (TXT + EPUB) ================= */
    let fontSize = 17;
    function changeFontSize(delta) {
        fontSize = Math.min(28, Math.max(12, fontSize + delta));
        document.documentElement.style.setProperty('--reader-font-size', fontSize + 'px');
        if (cfg.fileType === 'epub') epubFontSize(fontSize + 2);
    }

    /* ================= Wire up toolbar ================= */
    document.addEventListener('click', function (e) {
        const action = e.target.closest('[data-reader-action]');
        if (!action) return;
        const act = action.getAttribute('data-reader-action');
        if (cfg.fileType === 'pdf') {
            if (act === 'prev') pdfPrev();
            if (act === 'next') pdfNext();
            if (act === 'zoom-in') pdfZoom(0.2);
            if (act === 'zoom-out') pdfZoom(-0.2);
        }
        if (cfg.fileType === 'epub') {
            if (act === 'prev') epubPrev();
            if (act === 'next') epubNext();
        }
        if (act === 'font-up') changeFontSize(1);
        if (act === 'font-down') changeFontSize(-1);
        if (act === 'fullscreen') {
            const shell = document.querySelector('.reader-shell');
            if (!document.fullscreenElement) shell.requestFullscreen().catch(function () {});
            else document.exitFullscreen();
        }
        if (act === 'close') { window.location.href = cfg.baseUrl + 'book.php?id=' + cfg.bookId; }
    });

    const gotoInput = document.querySelector('.reader-goto-input');
    if (gotoInput) {
        gotoInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                const num = parseInt(gotoInput.value, 10);
                if (num && cfg.fileType === 'pdf') pdfGoTo(num);
                gotoInput.value = '';
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { const shell = document.querySelector('.reader-shell'); if (shell) window.location.href = cfg.baseUrl + 'book.php?id=' + cfg.bookId; }
        if (e.key === 'ArrowRight') { if (cfg.fileType === 'pdf') pdfNext(); if (cfg.fileType === 'epub') epubNext(); }
        if (e.key === 'ArrowLeft') { if (cfg.fileType === 'pdf') pdfPrev(); if (cfg.fileType === 'epub') epubPrev(); }
    });

    /* ================= Boot ================= */
    if (cfg.fileType === 'pdf') initPdf();
    else if (cfg.fileType === 'epub') initEpub();
    else if (cfg.fileType === 'txt') initTxt();
    else showReaderError('This file format cannot be previewed online. Please download it to view.');
})();
