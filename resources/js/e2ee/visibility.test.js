import { describe, expect, it } from 'vitest';
import {
    shouldRefreshInboxOnPageShow,
    shouldResumeChatSessionOnVisibility,
} from './visibility.js';

describe('shouldRefreshInboxOnPageShow', () => {
    it('refreshes only when the page was restored from bfcache', () => {
        expect(shouldRefreshInboxOnPageShow({ persisted: true })).toBe(true);
        expect(shouldRefreshInboxOnPageShow({ persisted: false })).toBe(false);
        expect(shouldRefreshInboxOnPageShow(null)).toBe(false);
    });
});

describe('shouldResumeChatSessionOnVisibility', () => {
    it('resumes only after the tab was hidden and becomes visible again', () => {
        expect(shouldResumeChatSessionOnVisibility('visible', true)).toBe(true);
        expect(shouldResumeChatSessionOnVisibility('visible', false)).toBe(false);
        expect(shouldResumeChatSessionOnVisibility('hidden', true)).toBe(false);
        expect(shouldResumeChatSessionOnVisibility('hidden', false)).toBe(false);
        expect(shouldResumeChatSessionOnVisibility(null, true)).toBe(false);
    });
});
