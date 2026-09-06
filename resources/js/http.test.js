import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { appFetch, followResponseRedirect, ResponseRedirectError } from './http.js';

function stubLocation() {
    const location = {
        origin: 'https://passshare.test',
        assign: vi.fn(),
    };

    vi.stubGlobal('window', { location });
    vi.stubGlobal('location', location);

    return location;
}

describe('followResponseRedirect', () => {
    /** @type {{ origin: string, assign: ReturnType<typeof vi.fn> }} */
    let location;

    beforeEach(() => {
        location = stubLocation();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('navigates to the final URL when fetch followed a redirect', () => {
        const response = {
            redirected: true,
            status: 200,
            url: 'https://passshare.test/login',
        };

        expect(() => followResponseRedirect(response)).toThrow(ResponseRedirectError);
        expect(location.assign).toHaveBeenCalledWith('https://passshare.test/login');
    });

    it('navigates to login when the response is 401', () => {
        const response = {
            redirected: false,
            status: 401,
            url: 'https://passshare.test/api/identity/public-key',
        };

        expect(() => followResponseRedirect(response)).toThrow(ResponseRedirectError);
        expect(location.assign).toHaveBeenCalledWith('https://passshare.test/login');
    });

    it('returns the response when it is neither a redirect nor 401', () => {
        const response = {
            redirected: false,
            status: 200,
            url: 'https://passshare.test/api/messages/1',
        };

        expect(followResponseRedirect(response)).toBe(response);
        expect(location.assign).not.toHaveBeenCalled();
    });
});

describe('appFetch', () => {
    /** @type {{ origin: string, assign: ReturnType<typeof vi.fn> }} */
    let location;

    beforeEach(() => {
        location = stubLocation();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('navigates when the underlying fetch followed a redirect', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => ({
            redirected: true,
            status: 200,
            url: 'https://passshare.test/login',
        })));

        await expect(appFetch('/broadcasting/auth', { method: 'POST' }))
            .rejects
            .toBeInstanceOf(ResponseRedirectError);

        expect(location.assign).toHaveBeenCalledWith('https://passshare.test/login');
    });
});
