/**
 * Client-side helpers for deferred chat file uploads to Cloudflare R2.
 * Files stay local until send; then we request a signed PUT URL and upload.
 */

/**
 * @param {Record<string, string[]|string>|null|undefined} headers
 * @returns {Record<string, string>}
 */
export function flattenUploadHeaders(headers) {
    /** @type {Record<string, string>} */
    const flattened = {};

    if (! headers || typeof headers !== 'object') {
        return flattened;
    }

    for (const [key, value] of Object.entries(headers)) {
        if (Array.isArray(value)) {
            flattened[key] = String(value[0] ?? '');
        } else if (typeof value === 'string') {
            flattened[key] = value;
        }
    }

    return flattened;
}

/**
 * @param {File|null|undefined} file
 * @param {number} maxFileBytes
 * @returns {string}
 */
export function validateSelectedFile(file, maxFileBytes) {
    if (! file) {
        return 'No file selected.';
    }

    if (! Number.isFinite(file.size) || file.size < 1) {
        return 'The selected file is empty.';
    }

    if (file.size > maxFileBytes) {
        return `The file may not be greater than ${maxFileBytes} bytes.`;
    }

    return '';
}

/**
 * @param {{
 *   uploadsUrl: string,
 *   csrfToken: string,
 *   file: File,
 * }} options
 * @returns {Promise<{
 *   url: string,
 *   headers: Record<string, string[]|string>,
 *   path: string,
 *   max_file_bytes: number,
 *   expires_in: number,
 * }>}
 */
export async function requestUploadLink({ uploadsUrl, csrfToken, file }) {
    const response = await fetch(uploadsUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            filename: file.name,
            content_type: file.type || 'application/octet-stream',
            size: file.size,
        }),
    });

    if (response.status === 507) {
        throw new Error('Cloud storage capacity limit exceeded.');
    }

    if (! response.ok) {
        throw new Error('Failed to create upload link.');
    }

    return response.json();
}

/**
 * @param {File} file
 * @param {{ url: string, headers?: Record<string, string[]|string> }} link
 */
export async function uploadFileToLink(file, link) {
    const response = await fetch(link.url, {
        method: 'PUT',
        headers: flattenUploadHeaders(link.headers),
        body: file,
    });

    if (! response.ok) {
        throw new Error('Failed to upload file.');
    }
}

/**
 * @param {File} file
 * @param {string} path
 * @returns {{ path: string, name: string, content_type: string, size: number }}
 */
export function buildAttachmentMetadata(file, path) {
    return {
        path,
        name: file.name,
        content_type: file.type || 'application/octet-stream',
        size: file.size,
    };
}

/**
 * @param {{
 *   text?: string,
 *   attachment?: { path: string, name: string, content_type: string, size: number }|null,
 * }} content
 * @returns {string}
 */
export function serializeChatMessageContent({ text = '', attachment = null }) {
    if (! attachment) {
        return text;
    }

    return JSON.stringify({
        v: 1,
        text,
        attachment,
    });
}

/**
 * @param {unknown} attachment
 * @returns {{ path: string, name: string, content_type: string, size: number }|null}
 */
function normalizeAttachment(attachment) {
    if (! attachment || typeof attachment !== 'object') {
        return null;
    }

    const path = attachment.path;
    const name = attachment.name;

    if (typeof path !== 'string' || path === '' || typeof name !== 'string' || name === '') {
        return null;
    }

    return {
        path,
        name,
        content_type: typeof attachment.content_type === 'string' && attachment.content_type !== ''
            ? attachment.content_type
            : 'application/octet-stream',
        size: Number.isFinite(Number(attachment.size))
            ? Number(attachment.size)
            : 0,
    };
}

/**
 * @param {string|null|undefined} plaintext
 * @returns {{
 *   text: string,
 *   attachment: { path: string, name: string, content_type: string, size: number }|null,
 * }}
 */
export function parseChatMessageContent(plaintext) {
    if (typeof plaintext !== 'string') {
        return { text: '', attachment: null };
    }

    if (plaintext === '' || ! plaintext.startsWith('{')) {
        return { text: plaintext, attachment: null };
    }

    try {
        const parsed = JSON.parse(plaintext);

        if (! parsed || typeof parsed !== 'object' || parsed.v !== 1) {
            return { text: plaintext, attachment: null };
        }

        return {
            text: typeof parsed.text === 'string' ? parsed.text : '',
            attachment: normalizeAttachment(parsed.attachment),
        };
    } catch {
        return { text: plaintext, attachment: null };
    }
}

/**
 * @param {string|null} plaintext
 * @param {string} decryptionError
 * @returns {{
 *   plaintext: string|null,
 *   attachment: { path: string, name: string, content_type: string, size: number }|null,
 *   decryptionError: string,
 * }}
 */
export function hydrateMessageContent(plaintext, decryptionError) {
    if (decryptionError || plaintext === null) {
        return {
            plaintext,
            attachment: null,
            decryptionError,
        };
    }

    const parsed = parseChatMessageContent(plaintext);

    return {
        plaintext: parsed.text,
        attachment: parsed.attachment,
        decryptionError: '',
    };
}
