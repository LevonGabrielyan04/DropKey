import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('./cryptography/e2ee/session.js', () => ({
    establishSession: vi.fn(),
}));

import {
    DEFAULT_AUTO_DELETE,
    persistAutoDelete,
} from './e2eeChatSettings.js';

describe('DEFAULT_AUTO_DELETE', () => {
    it('defaults to seven days', () => {
        expect(DEFAULT_AUTO_DELETE).toBe('7 days');
    });
});

describe('persistAutoDelete', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    it('returns the persisted auto-delete value on success', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ auto_delete: '1 day' }),
        }));

        await expect(persistAutoDelete({
            autoDeleteUrl: '/api/conversations/1/auto-delete',
            csrfToken: 'token',
            autoDelete: '1 day',
        })).resolves.toEqual({
            ok: true,
            autoDelete: '1 day',
        });

        expect(fetch).toHaveBeenCalledWith('/api/conversations/1/auto-delete', {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': 'token',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                auto_delete: '1 day',
            }),
        });
    });

    it('returns a failure result when the request is rejected', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: false,
            json: async () => ({}),
        }));

        await expect(persistAutoDelete({
            autoDeleteUrl: '/api/conversations/1/auto-delete',
            csrfToken: 'token',
            autoDelete: '1 hour',
        })).resolves.toEqual({ ok: false });
    });
});
