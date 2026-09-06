import { establishSession } from './cryptography/e2ee/session.js';
import { appFetch, ResponseRedirectError } from './http.js';

export const DEFAULT_AUTO_DELETE = '7 days';

/**
 * Persist a conversation auto-delete preference.
 *
 * @param {object} options
 * @param {string} options.autoDeleteUrl
 * @param {string} options.csrfToken
 * @param {string} options.autoDelete
 * @returns {Promise<{ ok: true, autoDelete: string } | { ok: false }>}
 */
export async function persistAutoDelete({ autoDeleteUrl, csrfToken, autoDelete }) {
    const response = await appFetch(autoDeleteUrl, {
        method: 'PATCH',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            auto_delete: autoDelete,
        }),
    });

    if (! response.ok) {
        return { ok: false };
    }

    const data = await response.json();

    return {
        ok: true,
        autoDelete: data.auto_delete ?? autoDelete,
    };
}

/**
 * Alpine component for per-conversation chat settings (fingerprint + auto-delete).
 */
if (typeof document !== 'undefined') {
document.addEventListener('alpine:init', () => {
    Alpine.data('e2eeChatSettings', () => ({
        loading: true,
        ready: false,
        error: '',
        partnerFingerprint: '',
        localUserId: 0,
        recipientId: 0,
        csrfToken: '',
        registerUrl: '',
        mineUrl: '',
        publicKeyUrl: '',
        autoDelete: DEFAULT_AUTO_DELETE,
        autoDeleteUrl: '',
        autoDeleteSaving: false,
        autoDeleteError: '',

        init() {
            this.localUserId = Number(this.$el.dataset.localUserId);
            this.recipientId = Number(this.$el.dataset.recipientId);
            this.csrfToken = this.$el.dataset.csrfToken ?? '';
            this.registerUrl = this.$el.dataset.registerUrl ?? '';
            this.mineUrl = this.$el.dataset.mineUrl ?? '';
            this.publicKeyUrl = this.$el.dataset.publicKeyUrl ?? '';
            this.autoDelete = this.$el.dataset.autoDelete || DEFAULT_AUTO_DELETE;
            this.autoDeleteUrl = this.$el.dataset.autoDeleteUrl ?? '';

            this.bootstrap();
        },

        async bootstrap() {
            this.loading = true;
            this.error = '';
            this.ready = false;

            try {
                const session = await establishSession({
                    localUserId: this.localUserId,
                    recipientId: this.recipientId,
                    publicKeyUrl: this.publicKeyUrl,
                    registerUrl: this.registerUrl,
                    mineUrl: this.mineUrl,
                    csrfToken: this.csrfToken,
                });

                this.partnerFingerprint = session.partnerFingerprint;
                this.ready = true;
            } catch (error) {
                if (error instanceof ResponseRedirectError) {
                    return;
                }

                this.error = 'Unable to load partner fingerprint. Ensure your partner has opened Messages at least once.';
            } finally {
                this.loading = false;
            }
        },

        async updateAutoDelete() {
            if (! this.autoDeleteUrl || this.autoDeleteSaving) {
                return;
            }

            const previousAutoDelete = this.$el.dataset.autoDelete || DEFAULT_AUTO_DELETE;
            this.autoDeleteSaving = true;
            this.autoDeleteError = '';

            try {
                const result = await persistAutoDelete({
                    autoDeleteUrl: this.autoDeleteUrl,
                    csrfToken: this.csrfToken,
                    autoDelete: this.autoDelete,
                });

                if (! result.ok) {
                    this.autoDelete = previousAutoDelete;
                    this.autoDeleteError = 'Failed to update auto-delete setting.';
                    return;
                }

                this.autoDelete = result.autoDelete;
                this.$el.dataset.autoDelete = this.autoDelete;
            } catch (error) {
                if (error instanceof ResponseRedirectError) {
                    return;
                }

                this.autoDelete = previousAutoDelete;
                this.autoDeleteError = 'Failed to update auto-delete setting.';
            } finally {
                this.autoDeleteSaving = false;
            }
        },
    }));
});
}
