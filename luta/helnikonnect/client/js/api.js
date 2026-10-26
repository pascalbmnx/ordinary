// HelpKonnect — Client API client
// All communication with the backend goes through the REST API gateway.
window.HNC = (function () {
    const script = document.currentScript || document.querySelector('script[src*="api.js"]');
    const apiDir = script ? new URL(script.src, window.location.href).pathname.replace(/\/client\/js\/api\.js$/, '/api') : '/api';
    const BASE = apiDir + '/index.php';

    let token = localStorage.getItem('hnc_token');
    const listeners = [];

    function apiUrl(route, params = {}) {
        const q = new URLSearchParams({ route: route.replace(/^\//, ''), ...params });
        return BASE + '?' + q.toString();
    }

    async function request(route, options = {}) {
        const { method = 'GET', body, query = {}, auth = true } = options;
        const headers = { Accept: 'application/json' };
        const opts = { method, headers };
        if (body !== undefined) {
            headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        if (auth && token) {
            headers['Authorization'] = 'Bearer ' + token;
        }
        const url = apiUrl(route, query);
        let res, data;
        try {
            res = await fetch(url, opts);
            data = await res.json().catch(() => ({ success: false, message: 'Invalid response' }));
        } catch (e) {
            throw { network: true, message: 'Network error. Please try again.' };
        }
        if (!res.ok && !data.success) {
            const err = new Error(data.message || 'Something went wrong.');
            err.status = res.status;
            err.errors = data.errors || [];
            err.body = data;
            throw err;
        }
        return data;
    }

    async function upload(route, formData, query = {}) {
        const headers = { Accept: 'application/json' };
        const opts = { method: 'POST', headers, body: formData };
        if (token) headers['Authorization'] = 'Bearer ' + token;
        const res = await fetch(apiUrl(route, query), opts);
        return res.json();
    }

    function setToken(t) {
        token = t;
        if (t) localStorage.setItem('hnc_token', t);
        else localStorage.removeItem('hnc_token');
        listeners.forEach(fn => fn(token));
    }

    function getToken() { return token; }

    function onAuth(fn) { listeners.push(fn); }

    function user() {
        try { return JSON.parse(localStorage.getItem('hnc_user') || 'null'); }
        catch { return null; }
    }
    async function setUser(u) {
        if (u) localStorage.setItem('hnc_user', JSON.stringify(u));
        else localStorage.removeItem('hnc_user');
    }

    async function me() {
        if (!token) return null;
        try {
            const data = await request('auth/me');
            await setUser(data.data);
            return data.data;
        } catch (e) {
            if (e.status === 401) setToken(null);
            return null;
        }
    }

    return {
        BASE,
        apiUrl,
        request,
        upload,
        setToken,
        getToken,
        onAuth,
        user,
        setUser,
        me,
    };
})();
