// Lightweight JSON fetch for the few endpoints that are not Inertia visits —
// the tracking GPS loop and its polling fallback (05-Live-Tracking). Inertia's
// router expects an Inertia response, so a plain fetch is the right tool here.
// We forward Laravel's XSRF-TOKEN cookie as the header its CSRF middleware
// reads, so no axios dependency is needed.

function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : null;
}

async function request<T>(url: string, method: string, body?: unknown): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const token = xsrfToken();
    if (token !== null) {
        headers['X-XSRF-TOKEN'] = token;
    }

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(`Request failed: ${response.status}`);
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return (await response.json()) as T;
}

export function postJson<T>(url: string, body?: unknown): Promise<T> {
    return request<T>(url, 'POST', body);
}

export function getJson<T>(url: string): Promise<T> {
    return request<T>(url, 'GET');
}
