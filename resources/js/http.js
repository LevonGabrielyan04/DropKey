export class ResponseRedirectError extends Error {
    /**
     * @param {string} url
     */
    constructor(url) {
        super(`Redirected to ${url}`);
        this.name = 'ResponseRedirectError';
        this.url = url;
    }
}

/**
 * Navigate to a URL and stop the caller.
 *
 * @param {string} url
 * @returns {never}
 */
function navigateTo(url) {
    window.location.assign(url);
    throw new ResponseRedirectError(url);
}

/**
 * If the response was a (followed) redirect or an unauthenticated 401, navigate
 * to that destination instead of letting callers treat HTML/JSON errors as data.
 *
 * Non-API routes in this app redirect guests to login. `/api/*` returns 401 JSON
 * instead (see bootstrap/app.php shouldRenderJsonWhen), so 401 is treated as login.
 *
 * @param {Response} response
 * @returns {Response}
 */
export function followResponseRedirect(response) {
    if (response.redirected) {
        navigateTo(response.url);
    }

    if (response.status === 401) {
        navigateTo(new URL('/login', window.location.origin).href);
    }

    return response;
}

/**
 * fetch() that navigates away when Laravel redirects or returns 401.
 *
 * @param {RequestInfo|URL} input
 * @param {RequestInit} [init]
 * @returns {Promise<Response>}
 */
export async function appFetch(input, init = {}) {
    const response = await fetch(input, init);

    return followResponseRedirect(response);
}
