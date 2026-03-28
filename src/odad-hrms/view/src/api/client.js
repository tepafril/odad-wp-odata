/**
 * REST API client for WP-HR Suite.
 * Uses window.wphrApi (root, nonce, namespace) injected by wp_localize_script.
 */

function getConfig() {
    if (typeof window !== 'undefined' && window.wphrApi) {
        return window.wphrApi;
    }
    return { root: '/wp-json', nonce: '', namespace: 'wp-hr/v1' };
}

export function getBaseUrl() {
    const { root, namespace } = getConfig();
    return `${root.replace(/\/$/, '')}/${namespace.replace(/^\//, '')}`;
}

/**
 * @param {string} path   Path relative to namespace (e.g. 'employees' or 'employees/1')
 * @param {RequestInit} [options]
 * @returns {Promise<{ data?: any, error?: { code: string, message: string }, status: number }>}
 */
export async function request(path, options = {}) {
    const url = `${getBaseUrl()}/${path.replace(/^\//, '')}`;
    const { nonce } = getConfig();

    const init = {
        ...options,
        headers: {
            'X-WP-Nonce': nonce,
            ...(options.body && typeof options.body === 'string' ? { 'Content-Type': 'application/json' } : {}),
            ...(options.headers || {}),
        },
        credentials: 'same-origin',
    };

    const res = await fetch(url, init);
    const status = res.status;
    let data = null;

    const ct = res.headers.get('content-type');
    if (ct && ct.includes('application/json')) {
        try { data = await res.json(); } catch { data = null; }
    }

    if (!res.ok) {
        const message = (data && data.message) || res.statusText;
        const code    = (data && data.code)    || 'request_failed';
        return { error: { code, message }, status, data };
    }
    return { data, status };
}

export default { getBaseUrl, request };
