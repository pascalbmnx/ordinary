// HelpKonnect — Admin panel
(function () {
    const script = document.currentScript || document.querySelector('script[src*="admin.js"]');
    const apiDir = script ? new URL(script.src, window.location.href).pathname.replace(/\/admin\/js\/admin\.js$/, '/api') : '/api';
    const BASE = apiDir + '/index.php';
    let token = localStorage.getItem('hnc_admin_token');
    let currentUser = null;
    let currentSection = 'dashboard';

    // ---------- API ----------
    function apiUrl(route, query = {}) {
        const q = new URLSearchParams({ route: route.replace(/^\//, ''), ...query });
        return BASE + '?' + q.toString();
    }

    async function api(route, opts = {}) {
        const { method = 'GET', body, query = {} } = opts;
        const headers = { Accept: 'application/json' };
        const fetchOpts = { method, headers };
        if (body !== undefined) { headers['Content-Type'] = 'application/json'; fetchOpts.body = JSON.stringify(body); }
        if (token) headers['Authorization'] = 'Bearer ' + token;
        const q = new URLSearchParams({ route: route.replace(/^\//, ''), ...query });
        const res = await fetch(BASE + '?' + q.toString(), fetchOpts);
        const data = await res.json().catch(() => ({ success: false, message: 'Invalid response' }));
        if (!res.ok || !data.success) {
            const err = new Error(data.message || 'Request failed');
            err.status = res.status;
            err.body = data;
            throw err;
        }
        return data;
    }

    // ---------- DOM helpers ----------
    function $(id) { return document.getElementById(id); }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
    function fmtDate(s) { if (!s) return ''; const d = new Date(s.replace(' ', 'T')); return d.toLocaleDateString(); }
    function fmtDateTime(s) { if (!s) return ''; const d = new Date(s.replace(' ', 'T')); return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }

    function toast(msg, type = 'success') {
        const wrap = $('toast-wrap');
        if (!wrap) return;
        const n = document.createElement('div');
        n.className = 'toast toast--' + type;
        n.textContent = msg;
        wrap.appendChild(n);
        setTimeout(() => n.classList.add('toast--show'), 20);
        setTimeout(() => { n.classList.remove('toast--show'); setTimeout(() => n.remove(), 300); }, 3200);
    }

    function badge(text) {
        const cls = {
            'submitted': 'badge-submitted', 'under review': 'badge-under', 'in progress': 'badge-in-progress',
            'approved': 'badge-approved', 'completed': 'badge-completed', 'rejected': 'badge-rejected',
            'draft': 'badge-draft', 'normal': 'badge-normal', 'important': 'badge-important', 'urgent': 'badge-urgent',
        };
        return `<span class="badge ${cls[text.toLowerCase()] || 'badge-draft'}">${esc(text)}</span>`;
    }

    function empty(msg) { return `<div class="empty-state"><p>${esc(msg)}</p></div>`; }
    function skeletonRows(n) { return Array.from({ length: n }).map(() => `<tr><td colspan="6"><div class="skeleton" style="height:16px;width:${50 + Math.random() * 40}%"></div></td></tr>`).join(''); }

    // ---------- Modal ----------
    function openModal(title, bodyHtml, footerHtml) {
        $('modal-title').textContent = title;
        $('modal-body').innerHTML = bodyHtml;
        $('modal-footer').innerHTML = footerHtml || '';
        $('modal-overlay').style.display = 'flex';
    }
    function closeModal() { $('modal-overlay').style.display = 'none'; }
    $('modal-close').onclick = closeModal;
    $('modal-overlay').addEventListener('click', e => { if (e.target === $('modal-overlay')) closeModal(); });

    // ---------- Auth ----------
    $('login-form').addEventListener('submit', async e => {
        e.preventDefault();
        const email = $('login-email').value.trim();
        const password = $('login-password').value;
        const errEl = $('login-error');
        errEl.style.display = 'none';
        $('login-btn').disabled = true;
        try {
            const res = await api('auth/login', { method: 'POST', body: { email, password } });
            token = res.data.token;
            localStorage.setItem('hnc_admin_token', token);
            currentUser = res.data.user;
            showApp();
        } catch (err) {
            errEl.textContent = err.message || 'Login failed';
            errEl.style.display = 'block';
        } finally { $('login-btn').disabled = false; }
    });

    $('logout-btn').addEventListener('click', async () => {
        try { await api('auth/logout', { method: 'POST' }); } catch (e) { /* ignore */ }
        token = null; currentUser = null;
        stopAutoRefresh();
        localStorage.removeItem('hnc_admin_token');
        $('app-shell').style.display = 'none';
        $('login-screen').style.display = 'flex';
    });

    async function showApp() {
        try {
            const res = await api('auth/me');
            currentUser = res.data;
            $('topbar-user').textContent = currentUser.full_name || currentUser.email;
            ensureAutoRefresh();
        } catch (e) {
            token = null; localStorage.removeItem('hnc_admin_token');
            $('app-shell').style.display = 'none';
            $('login-screen').style.display = 'flex';
            return;
        }
        $('login-screen').style.display = 'none';
        $('app-shell').style.display = 'flex';
        navigateTo('dashboard');
    }

    // ---------- Sidebar nav ----------
    $('sidebar-nav').addEventListener('click', e => {
        const link = e.target.closest('.sidebar-link');
        if (!link) return;
        e.preventDefault();
        navigateTo(link.dataset.section);
    });

    function navigateTo(section) {
        currentSection = section;
        document.querySelectorAll('.sidebar-link').forEach(l => l.classList.toggle('active', l.dataset.section === section));
        const titles = { dashboard: 'Dashboard', applications: 'Applications', drawings: 'Drawings', faqs: 'FAQs', documents: 'Documents', announcements: 'Announcements', settings: 'Settings' };
        $('page-title').textContent = titles[section] || section;
        loadSection(section);
        if (section === 'dashboard') ensureAutoRefresh();
        if (window.innerWidth <= 900) $('sidebar').classList.remove('open');
    }

    $('sidebar-toggle').addEventListener('click', () => $('sidebar').classList.toggle('open'));

    // ---------- Section loaders ----------
    async function loadSection(section) {
        const area = $('content-area');
        area.innerHTML = '<div class="empty-state">Loading...</div>';
        try {
            switch (section) {
                case 'dashboard': await loadDashboard(); break;
                case 'applications': await loadApplications(); break;
                case 'drawings': await loadDrawings(); break;
                case 'faqs': await loadFaqs(); break;
                case 'documents': await loadDocuments(); break;
                case 'announcements': await loadAnnouncements(); break;
                case 'settings': await loadSettings(); break;
            }
        } catch (err) {
            if (err.status === 401) { $('logout-btn').click(); return; }
            area.innerHTML = empty(err.message || 'Failed to load');
        }
    }

    // ---------- Dashboard ----------
    async function loadDashboard() {
        const area = $('content-area');
        area.innerHTML = `
            <div class="stat-grid">
                <div class="stat-card">
                    <span class="stat-label">Applications</span>
                    <span class="stat-value" id="stat-apps">0</span>
                    <span class="stat-sub">Total submitted</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Drawings</span>
                    <span class="stat-value" id="stat-drawings">0</span>
                    <span class="stat-sub">Total requests</span>
                </div>
            </div>
            <div class="table-wrap">
                <div class="table-header"><h3>Recent Applications</h3></div>
                <div id="recent-apps"></div>
            </div>
            <div class="table-wrap">
                <div class="table-header"><h3>Recent Drawings</h3></div>
                <div id="recent-drawings"></div>
            </div>
        `;
        await refreshDashboardData();
    }

    // Re-fetches dashboard stats and recent lists without rebuilding the shell.
    // Used by the auto-refresh timer so the dashboard stays live in place.
    async function refreshDashboardData() {
        try {
            const a = await api('applications');
            const appCard = $('stat-apps');
            if (appCard) appCard.textContent = (a.pagination || {}).total || (a.data || []).length;
        } catch (e) { /* keep last value */ }
        try {
            const d = await api('drawings');
            const drwCard = $('stat-drawings');
            if (drwCard) drwCard.textContent = (d.pagination || {}).total || (d.data || []).length;
        } catch (e) { /* keep last value */ }
        try {
            const r = await api('applications', { query: { limit: 5 } });
            const t = $('recent-apps'); if (t) renderAppsTable(r.data, t, false);
        } catch (e) { const t = $('recent-apps'); if (t) t.innerHTML = empty('No applications yet'); }
        try {
            const r = await api('drawings', { query: { limit: 5 } });
            const t = $('recent-drawings'); if (t) renderDrawingsTable(r.data, t, false);
        } catch (e) { const t = $('recent-drawings'); if (t) t.innerHTML = empty('No drawings yet'); }
    }

    // Auto-refresh: polls the dashboard data every second while the logged-in
    // admin is viewing the dashboard. Stops on logout / navigation away.
    const AUTO_REFRESH_MS = 1000;
    let autoRefreshTimer = null;
    function stopAutoRefresh() { if (autoRefreshTimer) { clearInterval(autoRefreshTimer); autoRefreshTimer = null; } }
    function ensureAutoRefresh() {
        if (!autoRefreshTimer) {
            autoRefreshTimer = setInterval(() => {
                if (currentSection === 'dashboard') refreshDashboardData();
            }, AUTO_REFRESH_MS);
        }
    }

    // ---------- Applications ----------
    async function loadApplications() {
        const area = $('content-area');
        area.innerHTML = `
            <div class="table-wrap">
                <div class="table-header">
                    <h3>All Applications</h3>
                    <input class="table-search" id="app-search" type="search" placeholder="Search reference, name...">
                </div>
                <div id="apps-table"></div>
            </div>
        `;
        await fetchAndRenderApps('');
        let timer;
        $('app-search').addEventListener('input', e => {
            clearTimeout(timer);
            timer = setTimeout(() => fetchAndRenderApps(e.target.value.trim()), 300);
        });
    }

    async function fetchAndRenderApps(query) {
        const container = $('apps-table');
        container.innerHTML = '<table class="data-table"><tbody>' + skeletonRows(5) + '</tbody></table>';
        try {
            const r = await api('applications', { query: { search: query, limit: 50 } });
            renderAppsTable(r.data, container, true);
        } catch (e) { container.innerHTML = empty(e.message); }
    }

    function renderAppsTable(rows, container, full) {
        if (!rows || !rows.length) { container.innerHTML = empty('No applications found'); return; }
        container.innerHTML = `
            <table class="data-table">
                <thead><tr>
                    <th>Reference</th>${full ? '<th>Applicant</th><th>Type</th>' : ''}
                    <th>Status</th><th>Date</th>${full ? '<th>Actions</th>' : ''}
                </tr></thead>
                <tbody>${rows.map(a => `<tr>
                    <td><strong>${esc(a.reference_no)}</strong></td>
                    ${full ? `<td>${esc(a.full_name)}</td><td>${esc(a.type_name || '')}</td>` : ''}
                    <td>${badge(a.status)}</td>
                    <td>${fmtDate(a.submitted_at || a.created_at)}</td>
                    ${full ? `<td>
                        <button class="btn btn-sm btn-outline" onclick="HNC_ADMIN.viewApp(${a.id})">View</button>
                        <button class="btn btn-sm btn-primary" onclick="HNC_ADMIN.updateApp(${a.id})">Update</button>
                    </td>` : ''}
                </tr>`).join('')}</tbody>
            </table>`;
    }

    // ---------- Drawings ----------
    async function loadDrawings() {
        const area = $('content-area');
        area.innerHTML = `
            <div class="table-wrap">
                <div class="table-header">
                    <h3>All Drawings</h3>
                    <input class="table-search" id="dr-search" type="search" placeholder="Search reference, name...">
                </div>
                <div id="drawings-table"></div>
            </div>
        `;
        await fetchAndRenderDrawings('');
        let timer;
        $('dr-search').addEventListener('input', e => {
            clearTimeout(timer);
            timer = setTimeout(() => fetchAndRenderDrawings(e.target.value.trim()), 300);
        });
    }

    async function fetchAndRenderDrawings(query) {
        const container = $('drawings-table');
        container.innerHTML = '<table class="data-table"><tbody>' + skeletonRows(5) + '</tbody></table>';
        try {
            const r = await api('drawings', { query: { search: query, limit: 50 } });
            renderDrawingsTable(r.data, container, true);
        } catch (e) { container.innerHTML = empty(e.message); }
    }

    function renderDrawingsTable(rows, container, full) {
        if (!rows || !rows.length) { container.innerHTML = empty('No drawing requests found'); return; }
        container.innerHTML = `
            <table class="data-table">
                <thead><tr>
                    <th>Reference</th>${full ? '<th>Applicant</th><th>Property</th>' : ''}
                    <th>Status</th><th>Date</th>${full ? '<th>Actions</th>' : ''}
                </tr></thead>
                <tbody>${rows.map(d => `<tr>
                    <td><strong>${esc(d.reference_no)}</strong></td>
                    ${full ? `<td>${esc(d.applicant_name)}</td><td>${esc(d.property_type || '')}</td>` : ''}
                    <td>${badge(d.status)}</td>
                    <td>${fmtDate(d.submitted_at || d.created_at)}</td>
                    ${full ? `<td>
                        <button class="btn btn-sm btn-outline" onclick="HNC_ADMIN.viewDrawing(${d.id})">View</button>
                        <button class="btn btn-sm btn-primary" onclick="HNC_ADMIN.updateDrawing(${d.id})">Update</button>
                    </td>` : ''}
                </tr>`).join('')}</tbody>
            </table>`;
    }

    // ---------- FAQs ----------
    async function loadFaqs() {
        const area = $('content-area');
        area.innerHTML = `
            <div class="table-wrap">
                <div class="table-header">
                    <h3>FAQs</h3>
                    <button class="btn btn-sm btn-primary" onclick="HNC_ADMIN.createFaq()">+ New FAQ</button>
                </div>
                <div id="faqs-table"></div>
            </div>
        `;
        const container = $('faqs-table');
        container.innerHTML = '<table class="data-table"><tbody>' + skeletonRows(3) + '</tbody></table>';
        try {
            const r = await api('faqs', { query: { limit: 100 } });
            const rows = r.data || [];
            if (!rows.length) { container.innerHTML = empty('No FAQs yet'); return; }
            container.innerHTML = `
                <table class="data-table">
                    <thead><tr><th>Question</th><th>Category</th><th>Order</th><th>Actions</th></tr></thead>
                    <tbody>${rows.map(f => `<tr>
                        <td style="max-width:300px">${esc(f.question)}</td>
                        <td>${esc(f.category_name || '')}</td>
                        <td>${f.sort_order || 0}</td>
                        <td>
                            <button class="btn btn-sm btn-outline" onclick="HNC_ADMIN.editFaq(${f.id})">Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="HNC_ADMIN.deleteFaq(${f.id})">Del</button>
                        </td>
                    </tr>`).join('')}</tbody>
                </table>`;
        } catch (e) { container.innerHTML = empty(e.message); }
    }

    // ---------- Documents ----------
    async function loadDocuments() {
        const area = $('content-area');
        area.innerHTML = `
            <div class="table-wrap">
                <div class="table-header">
                    <h3>Documents</h3>
                    <button class="btn btn-sm btn-primary" onclick="HNC_ADMIN.createDocument()">+ New Document</button>
                </div>
                <div id="docs-table"></div>
            </div>
        `;
        const container = $('docs-table');
        container.innerHTML = '<table class="data-table"><tbody>' + skeletonRows(3) + '</tbody></table>';
        try {
            const r = await api('documents', { query: { limit: 100 } });
            const rows = r.data || [];
            if (!rows.length) { container.innerHTML = empty('No documents yet'); return; }
            container.innerHTML = `
                <table class="data-table">
                    <thead><tr><th>Title</th><th>Category</th><th>Version</th><th>Actions</th></tr></thead>
                    <tbody>${rows.map(d => `<tr>
                        <td>${esc(d.title)}</td>
                        <td>${esc(d.category_name || '')}</td>
                        <td>${esc(d.version || '1.0')}</td>
                        <td>
                            <button class="btn btn-sm btn-outline" onclick="HNC_ADMIN.editDocument(${d.id})">Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="HNC_ADMIN.deleteDocument(${d.id})">Del</button>
                        </td>
                    </tr>`).join('')}</tbody>
                </table>`;
        } catch (e) { container.innerHTML = empty(e.message); }
    }

    // ---------- Announcements ----------
    async function loadAnnouncements() {
        const area = $('content-area');
        area.innerHTML = `
            <div class="table-wrap">
                <div class="table-header">
                    <h3>Announcements</h3>
                    <button class="btn btn-sm btn-primary" onclick="HNC_ADMIN.createAnnouncement()">+ New Announcement</button>
                </div>
                <div id="ann-table"></div>
            </div>
        `;
        const container = $('ann-table');
        container.innerHTML = '<table class="data-table"><tbody>' + skeletonRows(3) + '</tbody></table>';
        try {
            const r = await api('announcements', { query: { limit: 100 } });
            const rows = r.data || [];
            if (!rows.length) { container.innerHTML = empty('No announcements yet'); return; }
            container.innerHTML = `
                <table class="data-table">
                    <thead><tr><th>Title</th><th>Priority</th><th>Published</th><th>Actions</th></tr></thead>
                    <tbody>${rows.map(a => `<tr>
                        <td>${esc(a.title)}</td>
                        <td>${badge(a.priority || 'Normal')}</td>
                        <td>${fmtDate(a.published_at || a.created_at)}</td>
                        <td>
                            <button class="btn btn-sm btn-outline" onclick="HNC_ADMIN.editAnnouncement(${a.id})">Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="HNC_ADMIN.deleteAnnouncement(${a.id})">Del</button>
                        </td>
                    </tr>`).join('')}</tbody>
                </table>`;
        } catch (e) { container.innerHTML = empty(e.message); }
    }

    // ---------- Settings ----------
    async function loadSettings() {
        const area = $('content-area');
        area.innerHTML = '<div class="empty-state">Loading settings...</div>';
        try {
            const r = await api('settings', { query: { include_private: 1 } });
            const settings = r.data || [];
            area.innerHTML = `
                <div class="table-wrap">
                    <div class="table-header"><h3>System Settings</h3></div>
                    <form id="settings-form" style="padding:24px">
                        <div class="form-row">
                            ${settings.map(s => `<div class="form-group">
                                <label for="set-${s.setting_key}">${esc(s.setting_key)}</label>
                                <input class="form-input" id="set-${s.setting_key}" name="${esc(s.setting_key)}" value="${esc(s.setting_value || '')}">
                                ${s.description ? '<span class="muted" style="font-size:.8rem">' + esc(s.description) + '</span>' : ''}
                            </div>`).join('')}
                        </div>
                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit">Save Settings</button>
                        </div>
                    </form>
                </div>
            `;
            $('settings-form').addEventListener('submit', async e => {
                e.preventDefault();
                const fd = new FormData(e.target);
                const updates = {};
                for (const [k, v] of fd.entries()) updates[k] = v;
                try {
                    await api('settings', { method: 'PUT', body: updates });
                    toast('Settings saved');
                } catch (err) { toast(err.message || 'Failed to save', 'error'); }
            });
        } catch (e) { area.innerHTML = empty(e.message); }
    }

    // ---------- Category options (stable seed IDs) ----------
    const FAQ_CATEGORIES = [
        { id: 1, name: 'Applications' }, { id: 2, name: 'Drawings' },
        { id: 3, name: 'Electrical Installation' }, { id: 4, name: 'Documents' },
        { id: 5, name: 'Payments' }, { id: 6, name: 'Support' }, { id: 7, name: 'General Questions' },
    ];
    const DOC_CATEGORIES = [
        { id: 1, name: 'Application Forms' }, { id: 2, name: 'Application Guides' },
        { id: 3, name: 'Regulations & Guidelines' }, { id: 4, name: 'Required Documents' },
        { id: 5, name: 'Safety Information' }, { id: 6, name: 'Technical Resources' },
    ];
    function faqCategorySelect(selectedName) {
        const selectedId = (FAQ_CATEGORIES.find(c => c.name === selectedName) || {}).id;
        return `<select class="form-select" id="faq-category">
            <option value="">— No category —</option>
            ${FAQ_CATEGORIES.map(c => `<option value="${c.id}" ${c.id === selectedId ? 'selected' : ''}>${c.name}</option>`).join('')}
        </select>`;
    }
    function docCategorySelect(selectedName) {
        const selectedId = (DOC_CATEGORIES.find(c => c.name === selectedName) || {}).id;
        return `<select class="form-select" id="doc-category">
            <option value="">— No category —</option>
            ${DOC_CATEGORIES.map(c => `<option value="${c.id}" ${c.id === selectedId ? 'selected' : ''}>${c.name}</option>`).join('')}
        </select>`;
    }

    // ---------- CRUD modals ----------
    function faqFormHtml(vals = {}) {
        return `
            <div class="form-group">
                <label>Question <span class="req">*</span></label>
                <input class="form-input" id="faq-question" value="${esc(vals.question || '')}" required>
            </div>
            <div class="form-group">
                <label>Answer <span class="req">*</span></label>
                <textarea class="form-textarea" id="faq-answer" required>${esc(vals.answer || '')}</textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    ${faqCategorySelect(vals.category_name)}
                </div>
                <div class="form-group">
                    <label>Sort Order</label>
                    <input class="form-input" type="number" id="faq-sort" value="${vals.sort_order || 0}">
                </div>
            </div>`;
    }

    function docFormHtml(vals = {}, isCreate = false) {
        return `
            <div class="form-group">
                <label>Title <span class="req">*</span></label>
                <input class="form-input" id="doc-title" value="${esc(vals.title || '')}" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea class="form-textarea" id="doc-desc">${esc(vals.description || '')}</textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    ${docCategorySelect(vals.category_name)}
                </div>
                <div class="form-group">
                    <label>Version</label>
                    <input class="form-input" id="doc-version" value="${esc(vals.version || '1.0')}">
                </div>
            </div>
            <div class="form-group">
                <label>PDF File ${isCreate ? '<span class="req">*</span>' : '(optional, to replace)'}</label>
                <input class="form-input" type="file" id="doc-file" accept="application/pdf">
                ${vals.original_name ? '<span class="muted" style="font-size:.8rem">Current: ' + esc(vals.original_name) + '</span>' : ''}
            </div>`;
    }

    function annFormHtml(vals = {}) {
        return `
            <div class="form-group">
                <label>Title <span class="req">*</span></label>
                <input class="form-input" id="ann-title" value="${esc(vals.title || '')}" required>
            </div>
            <div class="form-group">
                <label>Content <span class="req">*</span></label>
                <textarea class="form-textarea" id="ann-content" required>${esc(vals.content || '')}</textarea>
            </div>
            <div class="form-group">
                <label>Priority</label>
                <select class="form-select" id="ann-priority">
                    <option value="Normal" ${vals.priority === 'Normal' ? 'selected' : ''}>Normal</option>
                    <option value="Important" ${vals.priority === 'Important' ? 'selected' : ''}>Important</option>
                    <option value="Urgent" ${vals.priority === 'Urgent' ? 'selected' : ''}>Urgent</option>
                </select>
            </div>`;
    }

    // ---------- Public API (global) ----------
    window.HNC_ADMIN = {
        async viewApp(id) {
            try {
                const r = await api('applications/' + id);
                const a = r.data;
                const histR = await api('applications/' + id + '/history');
                const hist = histR.data || [];
                openModal('Application ' + a.reference_no, `
                    <div class="form-row">
                        <div class="form-group"><label>Reference</label><p>${esc(a.reference_no)}</p></div>
                        <div class="form-group"><label>Status</label><p>${badge(a.status)}</p></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Name</label><p>${esc(a.full_name)}</p></div>
                        <div class="form-group"><label>Phone</label><p>${esc(a.phone)}</p></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Type</label><p>${esc(a.type_name || '')}</p></div>
                        <div class="form-group"><label>Region</label><p>${esc(a.region || '')}</p></div>
                    </div>
                    <div class="form-group"><label>Description</label><p>${esc(a.description || '')}</p></div>
                    ${hist.length ? `<h4 style="margin-top:20px">Status History</h4>
                    <div class="history-list">${hist.map(h => `
                        <div class="history-item">
                            <div class="history-dot"></div>
                            <div>
                                <div>${h.previous_status ? esc(h.previous_status) + ' → ' : ''}<strong>${esc(h.new_status)}</strong></div>
                                <div class="history-meta">${fmtDateTime(h.changed_at)}${h.changed_by_name ? ' by ' + esc(h.changed_by_name) : ''}</div>
                            </div>
                        </div>`).join('')}</div>` : ''}
                `);
            } catch (e) { toast(e.message, 'error'); }
        },

        async updateApp(id) {
            const statuses = ['Submitted', 'Under Review', 'In Progress', 'Approved', 'Completed', 'Rejected'];
            let current = '';
            try { const r = await api('applications/' + id); current = r.data.status; } catch (e) { /* ignore */ }
            openModal('Update Application Status', `
                <div class="form-group">
                    <label>Current Status</label>
                    <p>${badge(current)}</p>
                </div>
                <div class="form-group">
                    <label>New Status</label>
                    <select class="form-select" id="app-status-select">
                        ${statuses.map(s => `<option value="${s}" ${s === current ? 'selected' : ''}>${s}</option>`).join('')}
                    </select>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea class="form-textarea" id="app-status-note" placeholder="Optional note..."></textarea>
                </div>
            `, `<button class="btn btn-ghost" onclick="document.getElementById('modal-overlay').style.display='none'">Cancel</button>
                <button class="btn btn-primary" id="app-update-btn">Update Status</button>`);
            $('app-update-btn').addEventListener('click', async () => {
                try {
                    await api('applications/' + id, { method: 'PATCH', body: { status: $('app-status-select').value } });
                    closeModal();
                    toast('Application updated');
                    if (currentSection === 'applications') fetchAndRenderApps('');
                    else if (currentSection === 'dashboard') loadDashboard();
                } catch (e) { toast(e.message, 'error'); }
            });
        },

        async viewDrawing(id) {
            try {
                const r = await api('drawings/' + id);
                const d = r.data;
                const histR = await api('drawings/' + id + '/history');
                const hist = histR.data || [];
                openModal('Drawing ' + d.reference_no, `
                    <div class="form-row">
                        <div class="form-group"><label>Reference</label><p>${esc(d.reference_no)}</p></div>
                        <div class="form-group"><label>Status</label><p>${badge(d.status)}</p></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Name</label><p>${esc(d.applicant_name)}</p></div>
                        <div class="form-group"><label>Phone</label><p>${esc(d.phone)}</p></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Property</label><p>${esc(d.property_type || '')}</p></div>
                        <div class="form-group"><label>Location</label><p>${esc(d.location || '')}</p></div>
                    </div>
                    <div class="form-group"><label>Electrical Requirement</label><p>${esc(d.electrical_requirement || '')}</p></div>
                    <div class="form-group"><label>Rooms</label><p>${d.number_of_rooms || '—'}</p></div>
                    <div class="form-group"><label>Description</label><p>${esc(d.description || '')}</p></div>
                    ${hist.length ? `<h4 style="margin-top:20px">Status History</h4>
                    <div class="history-list">${hist.map(h => `
                        <div class="history-item">
                            <div class="history-dot"></div>
                            <div>
                                <div>${h.previous_status ? esc(h.previous_status) + ' → ' : ''}<strong>${esc(h.new_status)}</strong></div>
                                <div class="history-meta">${fmtDateTime(h.changed_at)}${h.changed_by_name ? ' by ' + esc(h.changed_by_name) : ''}</div>
                            </div>
                        </div>`).join('')}</div>` : ''}
                `);
            } catch (e) { toast(e.message, 'error'); }
        },

        async updateDrawing(id) {
            const statuses = ['Submitted', 'Under Review', 'In Progress', 'Completed', 'Rejected'];
            let current = '';
            try { const r = await api('drawings/' + id); current = r.data.status; } catch (e) { /* ignore */ }
            openModal('Update Drawing Status', `
                <div class="form-group">
                    <label>Current Status</label>
                    <p>${badge(current)}</p>
                </div>
                <div class="form-group">
                    <label>New Status</label>
                    <select class="form-select" id="dr-status-select">
                        ${statuses.map(s => `<option value="${s}" ${s === current ? 'selected' : ''}>${s}</option>`).join('')}
                    </select>
                </div>
            `, `<button class="btn btn-ghost" onclick="document.getElementById('modal-overlay').style.display='none'">Cancel</button>
                <button class="btn btn-primary" id="dr-update-btn">Update Status</button>`);
            $('dr-update-btn').addEventListener('click', async () => {
                try {
                    await api('drawings/' + id, { method: 'PATCH', body: { status: $('dr-status-select').value } });
                    closeModal();
                    toast('Drawing updated');
                    if (currentSection === 'drawings') fetchAndRenderDrawings('');
                    else if (currentSection === 'dashboard') loadDashboard();
                } catch (e) { toast(e.message, 'error'); }
            });
        },

        createFaq() {
            openModal('New FAQ', faqFormHtml(), `
                <button class="btn btn-ghost" onclick="document.getElementById('modal-overlay').style.display='none'">Cancel</button>
                <button class="btn btn-primary" id="faq-save-btn">Save FAQ</button>`);
            $('faq-save-btn').addEventListener('click', async () => {
                try {
                    await api('faqs', { method: 'POST', body: {
                        question: $('faq-question').value.trim(),
                        answer: $('faq-answer').value.trim(),
                        category_id: parseInt($('faq-category').value) || 0,
                        sort_order: parseInt($('faq-sort').value) || 0,
                    }});
                    closeModal(); toast('FAQ created'); loadFaqs();
                } catch (e) { toast(e.message, 'error'); }
            });
        },

        async editFaq(id) {
            let vals = {};
            try { const r = await api('faqs/' + id); vals = r.data; } catch (e) { toast(e.message, 'error'); return; }
            openModal('Edit FAQ', faqFormHtml(vals), `
                <button class="btn btn-ghost" onclick="document.getElementById('modal-overlay').style.display='none'">Cancel</button>
                <button class="btn btn-primary" id="faq-save-btn">Save Changes</button>`);
            $('faq-save-btn').addEventListener('click', async () => {
                try {
                    await api('faqs/' + id, { method: 'PATCH', body: {
                        question: $('faq-question').value.trim(),
                        answer: $('faq-answer').value.trim(),
                        category_id: parseInt($('faq-category').value) || 0,
                        sort_order: parseInt($('faq-sort').value) || 0,
                    }});
                    closeModal(); toast('FAQ updated'); loadFaqs();
                } catch (e) { toast(e.message, 'error'); }
            });
        },

        async deleteFaq(id) {
            if (!confirm('Delete this FAQ?')) return;
            try {
                await api('faqs/' + id, { method: 'DELETE' });
                toast('FAQ deleted'); loadFaqs();
            } catch (e) { toast(e.message, 'error'); }
        },

        createDocument() {
            openModal('New Document', docFormHtml({}, true), `
                <button class="btn btn-ghost" onclick="document.getElementById('modal-overlay').style.display='none'">Cancel</button>
                <button class="btn btn-primary" id="doc-save-btn">Save Document</button>`);
            $('doc-save-btn').addEventListener('click', async () => {
                const file = $('doc-file').files[0];
                if (!file) { toast('A PDF file is required', 'error'); return; }
                const fd = new FormData();
                fd.append('file', file);
                fd.append('title', $('doc-title').value.trim());
                fd.append('description', $('doc-desc').value.trim());
                fd.append('category_id', $('doc-category').value || '0');
                fd.append('version', $('doc-version').value.trim() || '1.0');
                try {
                    const res = await fetch(apiUrl('documents'), {
                        method: 'POST', headers: { Accept: 'application/json', Authorization: 'Bearer ' + token }, body: fd,
                    });
                    const data = await res.json();
                    if (!res.ok || !data.success) throw new Error(data.message || 'Upload failed');
                    closeModal(); toast('Document created'); loadDocuments();
                } catch (e) { toast(e.message, 'error'); }
            });
        },

        async editDocument(id) {
            let vals = {};
            try { const r = await api('documents/' + id); vals = r.data; } catch (e) { toast(e.message, 'error'); return; }
            openModal('Edit Document', docFormHtml(vals, false), `
                <button class="btn btn-ghost" onclick="document.getElementById('modal-overlay').style.display='none'">Cancel</button>
                <button class="btn btn-primary" id="doc-save-btn">Save Changes</button>`);
            $('doc-save-btn').addEventListener('click', async () => {
                try {
                    await api('documents/' + id, { method: 'PATCH', body: {
                        title: $('doc-title').value.trim(),
                        description: $('doc-desc').value.trim(),
                        category_id: parseInt($('doc-category').value) || 0,
                        version: $('doc-version').value.trim() || '1.0',
                    }});
                    closeModal(); toast('Document updated'); loadDocuments();
                } catch (e) { toast(e.message, 'error'); }
            });
        },

        async deleteDocument(id) {
            if (!confirm('Delete this document?')) return;
            try {
                await api('documents/' + id, { method: 'DELETE' });
                toast('Document deleted'); loadDocuments();
            } catch (e) { toast(e.message, 'error'); }
        },

        createAnnouncement() {
            openModal('New Announcement', annFormHtml(), `
                <button class="btn btn-ghost" onclick="document.getElementById('modal-overlay').style.display='none'">Cancel</button>
                <button class="btn btn-primary" id="ann-save-btn">Save Announcement</button>`);
            $('ann-save-btn').addEventListener('click', async () => {
                try {
                    await api('announcements', { method: 'POST', body: {
                        title: $('ann-title').value.trim(),
                        content: $('ann-content').value.trim(),
                        priority: $('ann-priority').value,
                    }});
                    closeModal(); toast('Announcement created'); loadAnnouncements();
                } catch (e) { toast(e.message, 'error'); }
            });
        },

        async editAnnouncement(id) {
            let vals = {};
            try { const r = await api('announcements/' + id); vals = r.data; } catch (e) { toast(e.message, 'error'); return; }
            openModal('Edit Announcement', annFormHtml(vals), `
                <button class="btn btn-ghost" onclick="document.getElementById('modal-overlay').style.display='none'">Cancel</button>
                <button class="btn btn-primary" id="ann-save-btn">Save Changes</button>`);
            $('ann-save-btn').addEventListener('click', async () => {
                try {
                    await api('announcements/' + id, { method: 'PATCH', body: {
                        title: $('ann-title').value.trim(),
                        content: $('ann-content').value.trim(),
                        priority: $('ann-priority').value,
                    }});
                    closeModal(); toast('Announcement updated'); loadAnnouncements();
                } catch (e) { toast(e.message, 'error'); }
            });
        },

        async deleteAnnouncement(id) {
            if (!confirm('Delete this announcement?')) return;
            try {
                await api('announcements/' + id, { method: 'DELETE' });
                toast('Announcement deleted'); loadAnnouncements();
            } catch (e) { toast(e.message, 'error'); }
        },
    };

    // ---------- Boot ----------
    if (token) showApp();
    else { $('login-screen').style.display = 'flex'; $('app-shell').style.display = 'none'; }

    // ---------- Logo reload ----------
    function reloadPage() { window.location.reload(); }
    const loginBrand = $('login-brand');
    const sidebarBrand = $('sidebar-brand');
    if (loginBrand) { loginBrand.style.cursor = 'pointer'; loginBrand.addEventListener('click', reloadPage); loginBrand.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); reloadPage(); } }); }
    if (sidebarBrand) { sidebarBrand.style.cursor = 'pointer'; sidebarBrand.addEventListener('click', reloadPage); sidebarBrand.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); reloadPage(); } }); }
})();
