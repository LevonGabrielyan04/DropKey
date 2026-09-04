/**
 * @param {{ persisted?: boolean } | null | undefined} event
 */
export function shouldRefreshInboxOnPageShow(event) {
    return Boolean(event?.persisted);
}

/**
 * Resume an open conversation after the tab was backgrounded.
 *
 * @param {string | null | undefined} visibilityState
 * @param {boolean} wasHidden
 */
export function shouldResumeChatSessionOnVisibility(visibilityState, wasHidden) {
    return visibilityState === 'visible' && wasHidden === true;
}
