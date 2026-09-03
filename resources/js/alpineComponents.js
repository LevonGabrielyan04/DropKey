import { formatLocalDatetime } from './formatLocalDatetime.js';
import {
    resolvePasswordRequiredForIdentityKeyOverwrite,
    verifyPasswordForIdentityKeyOverwrite,
} from './identityKeyOverwriteModal.js';
import {
    settleIdentityKeyOverwriteConfirmation,
} from './cryptography/e2ee/identityOverwriteConfirmation.js';

const IDENTITY_KEY_OVERWRITE_REQUEST = 'passshare:identity-key-overwrite-request';

document.addEventListener('alpine:init', () => {
    Alpine.data('identityKeyOverwriteModal', () => ({
        passwordRequired: false,
        password: '',
        passwordError: '',
        confirming: false,

        init() {
            const {
                passwordConfirmationStatusUrl,
                passwordConfirmUrl,
                csrfToken,
            } = document.body?.dataset ?? {};

            this.passwordConfirmationStatusUrl = passwordConfirmationStatusUrl ?? '';
            this.passwordConfirmUrl = passwordConfirmUrl ?? '';
            this.csrfToken = csrfToken ?? '';

            window.addEventListener(IDENTITY_KEY_OVERWRITE_REQUEST, async () => {
                await this.prepareModal();
                this.$flux.modal('identity-key-overwrite').show();
            });
        },

        async prepareModal() {
            this.password = '';
            this.passwordError = '';
            this.confirming = false;

            this.passwordRequired = await resolvePasswordRequiredForIdentityKeyOverwrite(
                this.passwordConfirmationStatusUrl,
            );
        },

        async confirmIdentityKeyOverwrite() {
            if (this.confirming) {
                return;
            }

            this.passwordError = '';

            if (this.passwordRequired) {
                this.confirming = true;

                try {
                    const result = await verifyPasswordForIdentityKeyOverwrite({
                        password: this.password,
                        passwordConfirmUrl: this.passwordConfirmUrl,
                        csrfToken: this.csrfToken,
                    });

                    if (! result.ok) {
                        this.passwordError = result.error;

                        return;
                    }

                    this.passwordRequired = false;
                    this.password = '';
                } finally {
                    this.confirming = false;
                }
            }

            settleIdentityKeyOverwriteConfirmation(true);
            this.$flux.modal('identity-key-overwrite').close();
        },

        cancelIdentityKeyOverwrite() {
            this.password = '';
            this.passwordError = '';
            this.confirming = false;
            settleIdentityKeyOverwriteConfirmation(false);
            this.$flux.modal('identity-key-overwrite').close();
        },
    }));

    Alpine.data('navigation', () => ({
        open: false,

        toggle() {
            this.open = !this.open;
        },

        get responsiveMenuClasses() {
            return {
                block: this.open,
                hidden: !this.open,
            };
        },

        get hamburgerClosedIconClasses() {
            return {
                hidden: this.open,
                'inline-flex': !this.open,
            };
        },

        get hamburgerOpenIconClasses() {
            return {
                hidden: !this.open,
                'inline-flex': this.open,
            };
        },
    }));

    Alpine.data('dropdown', () => ({
        open: false,

        toggle() {
            this.open = !this.open;
        },

        close() {
            this.open = false;
        },
    }));

    Alpine.data('modal', (initialShow = false, focusable = false, name = '') => ({
        show: initialShow,
        modalName: name,
        focusable,

        init() {
            this.$watch('show', (value) => {
                if (value) {
                    document.body.classList.add('overflow-y-hidden');

                    if (this.focusable) {
                        setTimeout(() => this.firstFocusable()?.focus(), 100);
                    }
                } else {
                    document.body.classList.remove('overflow-y-hidden');
                }
            });
        },

        focusables() {
            const selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])';

            return [...this.$el.querySelectorAll(selector)]
                .filter((element) => !element.hasAttribute('disabled'));
        },

        firstFocusable() {
            return this.focusables()[0];
        },

        lastFocusable() {
            return this.focusables().slice(-1)[0];
        },

        nextFocusable() {
            return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable();
        },

        prevFocusable() {
            return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable();
        },

        nextFocusableIndex() {
            return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1);
        },

        prevFocusableIndex() {
            return Math.max(0, this.focusables().indexOf(document.activeElement)) - 1;
        },

        handleOpenModal(event) {
            if (event.detail === this.modalName) {
                this.show = true;
            }
        },

        handleCloseModal(event) {
            if (event.detail === this.modalName) {
                this.show = false;
            }
        },

        close() {
            this.show = false;
        },

        handleTabKey(event) {
            if (!event.shiftKey) {
                this.nextFocusable().focus();
            }
        },

        handleShiftTabKey() {
            this.prevFocusable().focus();
        },
    }));

    Alpine.data('recoveryCodesVisibility', () => ({
        showRecoveryCodes: false,

        show() {
            this.showRecoveryCodes = true;
        },

        hide() {
            this.showRecoveryCodes = false;
        },
    }));

    Alpine.data('savedMessage', () => ({
        show: true,

        init() {
            setTimeout(() => {
                this.show = false;
            }, 2000);
        },
    }));

    Alpine.data('passwordVisibility', () => ({
        showPassword: false,

        toggle() {
            this.showPassword = ! this.showPassword;
        },
    }));

    Alpine.data('turnstileWidget', () => ({
        widgetId: null,
        pollId: null,

        init() {
            this.mount();
        },

        destroy() {
            this.teardown();
        },

        mount() {
            const tryRender = () => {
                if (! window.turnstile || this.widgetId !== null) {
                    return false;
                }

                // Avoid turnstile.ready() with async/defer api.js — Cloudflare throws TurnstileError.
                this.widgetId = window.turnstile.render(this.$el, {
                    sitekey: this.$el.dataset.sitekey,
                    theme: this.$el.dataset.theme || 'auto',
                });

                return true;
            };

            if (tryRender()) {
                return;
            }

            this.pollId = window.setInterval(() => {
                if (! tryRender()) {
                    return;
                }

                window.clearInterval(this.pollId);
                this.pollId = null;
            }, 50);
        },

        teardown() {
            if (this.pollId !== null) {
                window.clearInterval(this.pollId);
                this.pollId = null;
            }

            if (this.widgetId !== null && window.turnstile) {
                window.turnstile.remove(this.widgetId);
                this.widgetId = null;
            }
        },
    }));

    Alpine.data('passwordTooltip', () => ({
        showTooltip: false,

        show() {
            this.showTooltip = true;
        },

        hide() {
            this.showTooltip = false;
        },
    }));

    Alpine.data('dispatchOnClick', (eventName, detail = null) => ({
        handleClick() {
            this.$dispatch(eventName, detail);
        },
    }));

    Alpine.data('twoFactorChallenge', () => ({
        showRecoveryInput: false,
        code: '',
        recovery_code: '',

        init() {
            this.showRecoveryInput = this.$el.dataset.showRecoveryInput === 'true';

            if (! this.showRecoveryInput) {
                this.focusOtp();
            }
        },

        focusOtp() {
            this.$nextTick(() => this.$refs.otp?.querySelector('input')?.focus());
        },

        toggleInput() {
            this.showRecoveryInput = ! this.showRecoveryInput;

            this.code = '';
            this.recovery_code = '';

            this.$nextTick(() => {
                if (this.showRecoveryInput) {
                    this.$refs.recovery_code?.focus();
                } else {
                    this.focusOtp();
                }
            });
        },
    }));

    Alpine.data('localDatetime', () => ({
        formatted: '',

        init() {
            const utcDatetime = this.$el.dataset.utcDatetime ?? '';

            this.formatted = formatLocalDatetime(utcDatetime);
        },
    }));

    Alpine.data('copyText', () => ({
        copied: false,
        text: '',

        init() {
            this.text = this.$el.dataset.copyText ?? '';
        },

        async copy() {
            if (! this.text) {
                return;
            }

            try {
                await navigator.clipboard.writeText(this.text);
                this.copied = true;
                setTimeout(() => {
                    this.copied = false;
                }, 1500);
            } catch {
                console.warn('Could not copy to clipboard');
            }
        },
    }));

    Alpine.data('setupKeyCopy', () => ({
        copied: false,
        setupKey: '',

        init() {
            this.setupKey = this.$el.dataset.setupKey ?? '';
        },

        async copy() {
            if (! this.setupKey) {
                return;
            }

            try {
                await navigator.clipboard.writeText(this.setupKey);
                this.copied = true;
                setTimeout(() => {
                    this.copied = false;
                }, 1500);
            } catch {
                console.warn('Could not copy to clipboard');
            }
        },
    }));

    Alpine.data('notificationsTip', () => ({
        storageKey: '',
        dismissed: false,

        init() {
            this.storageKey = this.$el.dataset.storageKey ?? '';
            this.dismissed = this.storageKey !== ''
                && localStorage.getItem(this.storageKey) === '1';
        },

        dismiss() {
            if (this.storageKey !== '') {
                localStorage.setItem(this.storageKey, '1');
            }

            this.dismissed = true;
        },
    }));
});
