// HelpKonnect — Client main application script
(function () {
    const API = window.HNC;
    const L = window.HNC_LANG;

    // ---------- Helpers ----------
    function el(id) { return document.getElementById(id); }
    function show(id, on) { const e = el(id); if (e) e.style.display = on ? '' : 'none'; }

    function toast(message, type = 'success') {
        const wrap = el('toast-wrap');
        if (!wrap) return;
        const node = document.createElement('div');
        node.className = 'toast toast--' + type;
        node.textContent = message;
        wrap.appendChild(node);
        setTimeout(() => node.classList.add('toast--show'), 20);
        setTimeout(() => { node.classList.remove('toast--show'); setTimeout(() => node.remove(), 300); }, 3200);
    }

    // ---------- Global settings (name, contacts) ----------
    async function loadSettings() {
        try {
            const res = await API.request('settings', { auth: false });
            const s = res.data;
            document.querySelectorAll('[data-setting]').forEach(node => {
                const key = node.getAttribute('data-setting');
                if (s[key] !== undefined) {
                    if (node.tagName === 'A') node.href = s[key];
                    else node.textContent = s[key];
                }
            });
        } catch (e) { /* silent */ }
    }

    // ---------- Announcements ----------
    async function loadAnnouncements() {
        const grid = el('announced-grid');
        if (!grid) return;
        grid.innerHTML = skeleton(2);
        try {
            const res = await API.request('announcements?limit=4', { auth: false, query: { limit: 4 } });
            const items = res.data || [];
            if (!items.length) { grid.innerHTML = emptyState(L.t('no_results')); return; }
            grid.innerHTML = items.map(a => `
                <article class="card announce-card">
                    <div class="announce-meta">
                        <span class="badge badge-${(a.priority || 'normal').toLowerCase()}">${L.t('home') === 'Nyumbani' ? a.priority : a.priority}</span>
                        <span class="muted">${fmtDate(a.published_at || a.created_at)}</span>
                    </div>
                    <h3>${esc(a.title)}</h3>
                    <p>${esc(a.content)}</p>
                </article>`).join('');
        } catch (e) { grid.innerHTML = emptyState(L.t('something_wrong')); }
    }

    // ---------- Documents ----------
    async function loadDocuments() {
        const grid = el('docs-grid');
        if (!grid) return;
        grid.innerHTML = skeleton(3);
        try {
            const res = await API.request('documents', { auth: false, query: { limit: 6 } });
            const items = res.data || [];
            if (!items.length) { grid.innerHTML = emptyState(L.t('no_results')); return; }
            grid.innerHTML = items.map(d => `
                <a class="card doc-card" href="pages/documents.html" data-route="docs">
                    <div class="doc-icon">PDF</div>
                    <div>
                        <h3>${esc(d.title)}</h3>
                        <p class="muted">${esc(d.category_name || '')} · ${d.version || '1.0'}</p>
                    </div>
                </a>`).join('');
        } catch (e) { grid.innerHTML = emptyState(L.t('something_wrong')); }
    }

    // ---------- FAQ ----------
    async function loadFaqs() {
        const list = el('faq-list');
        if (!list) return;
        list.innerHTML = skeleton(3);
        try {
            const res = await API.request('faqs', { auth: false, query: { limit: 30 } });
            const items = res.data || [];
            if (!items.length) { list.innerHTML = emptyState(L.t('no_results')); return; }
            list.innerHTML = items.map((f, i) => `
                <div class="faq-item accordion">
                    <button class="accordion-head" aria-expanded="false">
                        <span>${esc(f.question)}</span>
                        <span class="accordion-icon">+</span>
                    </button>
                    <div class="accordion-body" hidden>
                        <p>${f.answer}</p>
                    </div>
                </div>`).join('');
            list.querySelectorAll('.accordion-head').forEach(btn => {
                btn.addEventListener('click', () => {
                    const open = btn.getAttribute('aria-expanded') === 'true';
                    btn.setAttribute('aria-expanded', String(!open));
                    btn.nextElementSibling.hidden = open;
                });
            });
        } catch (e) { list.innerHTML = emptyState(L.t('something_wrong')); }
    }

    // ---------- Search across docs/faqs/announcements ----------
    async function globalSearch(q) {
        if (!q) return [];
        const [docs, faqs, anns] = await Promise.all([
            API.request('documents', { auth: false, query: { search: q, limit: 5 } }).catch(() => ({ data: [] })),
            API.request('faqs', { auth: false, query: { search: q, limit: 5 } }).catch(() => ({ data: [] })),
            API.request('announcements', { auth: false, query: { search: q, limit: 5 } }).catch(() => ({ data: [] })),
        ]);
        return [
            ...(docs.data || []).map(d => ({ type: 'document', title: d.title, url: 'pages/documents.html' })),
            ...(faqs.data || []).map(f => ({ type: 'faq', title: f.question, url: 'pages/faq.html' })),
            ...(anns.data || []).map(a => ({ type: 'announcement', title: a.title, url: 'index.html#announcements' })),
        ];
    }

    // ---------- SPA navigation ----------
    const ROUTES = {
        '': { title: 'Home', load: initHome },
        'home': { title: 'Home', load: initHome },
        'services': { title: 'Services', load: null, scrollTo: 'services' },
        'apply': { title: 'Apply', load: null },
        'drawing': { title: 'Drawing', load: null },
        'assistance': { title: 'Assistance', load: null },
        'contact': { title: 'Contact', load: null, scrollTo: 'contact' },
        'faq': { title: 'FAQ', load: loadFaqs },
        'docs': { title: 'Documents', load: loadDocuments },
        'track': { title: 'Track', load: null },
    };

    function navigate(route) {
        const r = ROUTES[route] || ROUTES[''];
        document.title = r.title + ' — ' + (document.body.dataset.appName || 'HelpKonnect');
        // Toggle active nav
        document.querySelectorAll('[data-nav]').forEach(a =>
            a.classList.toggle('active', a.getAttribute('data-nav') === route));
        if (typeof r.load === 'function') r.load();
        if (r.scrollTo) {
            const target = document.getElementById(r.scrollTo);
            if (target) setTimeout(() => target.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
        }
    }

    // ---------- Decorative helpers ----------
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
    function fmtDate(s) {
        if (!s) return '';
        const d = new Date(s.replace(' ', 'T'));
        return d.toLocaleDateString();
    }
    function skeleton(n) {
        return Array.from({ length: n }).map(() => `
            <div class="skeleton-card">
                <div class="skeleton skeleton-line" style="width:80%"></div>
                <div class="skeleton skeleton-line" style="width:60%"></div>
                <div class="skeleton skeleton-line" style="width:90%"></div>
            </div>`).join('');
    }
    function emptyState(msg) {
        return `<div class="empty-state"><p>${esc(msg)}</p></div>`;
    }

    function initHome() {
        loadSettings();
        loadAnnouncements();
        if (el('docs-grid')) loadDocuments();
        if (el('faq-list')) loadFaqs();
    }

    // ---------- Boot ----------
    function initNavEvents() {
        document.addEventListener('click', (e) => {
            const a = e.target.closest('[data-route]');
            if (!a) return;
            const route = (a.getAttribute('data-route') || '').trim();
            const href = (a.getAttribute('href') || '').trim();
            // Real page links (e.g. pages/apply.html) should navigate normally,
            // not be swallowed by the in-page SPA handler.
            if (href && href !== '#' && !href.startsWith('#')) return;
            // Section anchors scroll to the element instead.
            if (route.startsWith('#')) {
                e.preventDefault();
                const t = document.querySelector(route);
                if (t) t.scrollIntoView({ behavior: 'smooth' });
                return;
            }
            e.preventDefault();
            navigate(route);
            if (route) history.replaceState({}, '', '#' + route);
        });
        // Mobile nav toggle
        const toggle = el('nav-toggle');
        if (toggle) toggle.addEventListener('click', () => {
            document.querySelector('.nav-links').classList.toggle('open');
        });
        // Language buttons
        document.querySelectorAll('[data-lang-btn]').forEach(btn => {
            btn.addEventListener('click', () => L.setLang(btn.getAttribute('data-lang-btn')));
        });
        // Search
        const searchInput = el('global-search');
        if (searchInput) {
            let timer;
            searchInput.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(async () => {
                    const q = searchInput.value.trim();
                    const results = await globalSearch(q);
                    const box = el('search-results');
                    if (!box) return;
                    if (!q) { box.innerHTML = ''; return; }
                    if (!results.length) { box.innerHTML = `<div class="empty-state">${L.t('no_results')}</div>`; return; }
                    box.innerHTML = results.map(r =>
                        `<a href="${r.url}" class="search-result" data-route="${r.url.includes('docs') ? 'docs' : r.url.includes('faq') ? 'faq' : 'home'}">
                            <span class="badge">${r.type}</span> ${esc(r.title)}
                        </a>`).join('');
                }, 300);
            });
        }
    }

    function boot() {
        initNavEvents();
        document.addEventListener('DOMContentLoaded', () => {
            L.apply();
            const hash = location.hash.replace('#', '');
            navigate(ROUTES[hash] ? hash : '');
        });
    }

    boot();
})();
