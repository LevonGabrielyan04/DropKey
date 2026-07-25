/**
 * Client-side helpers for deferred chat file uploads to Cloudflare R2.
 * Files stay local until send; then we encrypt with the conversation key,
 * request a signed PUT URL, and upload ciphertext only.
 */

import {
    AES_GCM_TAG_BYTES,
    PAYLOAD_VERSION,
    encryptBytes,
} from './cryptography/e2ee/messageCrypto.js';

export const ENCRYPTED_UPLOAD_CONTENT_TYPE = 'application/octet-stream';

/**
 * @param {number} plaintextBytes
 * @returns {number}
 */
export function encryptedUploadSize(plaintextBytes) {
    return plaintextBytes + AES_GCM_TAG_BYTES;
}

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

    if (encryptedUploadSize(file.size) > maxFileBytes) {
        return `The file may not be greater than ${maxFileBytes} bytes.`;
    }

    return '';
}

/**
 * Encrypt a browser File with the pairwise conversation key before upload.
 *
 * @param {File} file
 * @param {CryptoKey} conversationKey
 * @returns {Promise<{
 *   body: ArrayBuffer,
 *   uploadSize: number,
 *   uploadContentType: string,
 *   iv: string,
 *   v: number,
 * }>}
 */
export async function prepareEncryptedUpload(file, conversationKey) {
    const plaintext = await file.arrayBuffer();
    const encrypted = await encryptBytes(plaintext, conversationKey);

    return {
        body: encrypted.ciphertext,
        uploadSize: encrypted.ciphertext.byteLength,
        uploadContentType: ENCRYPTED_UPLOAD_CONTENT_TYPE,
        iv: encrypted.iv,
        v: encrypted.v,
    };
}

/**
 * @param {{
 *   uploadsUrl: string,
 *   csrfToken: string,
 *   filename: string,
 *   contentType: string,
 *   size: number,
 * }} options
 * @returns {Promise<{
 *   url: string,
 *   headers: Record<string, string[]|string>,
 *   path: string,
 *   max_file_bytes: number,
 *   expires_in: number,
 * }>}
 */
export async function requestUploadLink({ uploadsUrl, csrfToken, filename, contentType, size }) {
    const response = await fetch(uploadsUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            filename,
            content_type: contentType,
            size,
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
 * @param {Blob|ArrayBuffer|File} body
 * @param {{ url: string, headers?: Record<string, string[]|string> }} link
 */
export async function uploadFileToLink(body, link) {
    const response = await fetch(link.url, {
        method: 'PUT',
        headers: flattenUploadHeaders(link.headers),
        body,
    });

    if (! response.ok) {
        throw new Error('Failed to upload file.');
    }
}

/**
 * @param {File} file
 * @param {string} path
 * @param {{ iv: string, v?: number }} encryption
 * @returns {{
 *   path: string,
 *   name: string,
 *   content_type: string,
 *   size: number,
 *   v: number,
 *   iv: string,
 * }}
 */
export function buildAttachmentMetadata(file, path, encryption) {
    return {
        path,
        name: file.name,
        content_type: file.type || 'application/octet-stream',
        size: file.size,
        v: encryption.v ?? PAYLOAD_VERSION,
        iv: encryption.iv,
    };
}

/**
 * @param {{
 *   text?: string,
 *   attachment?: {
 *     path: string,
 *     name: string,
 *     content_type: string,
 *     size: number,
 *     v: number,
 *     iv: string,
 *   }|null,
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
 * @returns {{
 *   path: string,
 *   name: string,
 *   content_type: string,
 *   size: number,
 *   v: number,
 *   iv: string,
 * }|null}
 */
function normalizeAttachment(attachment) {
    if (! attachment || typeof attachment !== 'object') {
        return null;
    }

    const path = attachment.path;
    const name = attachment.name;
    const iv = attachment.iv;

    if (
        typeof path !== 'string'
        || path === ''
        || typeof name !== 'string'
        || name === ''
        || typeof iv !== 'string'
        || iv === ''
    ) {
        return null;
    }

    const version = Number.isFinite(Number(attachment.v))
        ? Number(attachment.v)
        : PAYLOAD_VERSION;

    return {
        path,
        name,
        content_type: typeof attachment.content_type === 'string' && attachment.content_type !== ''
            ? attachment.content_type
            : 'application/octet-stream',
        size: Number.isFinite(Number(attachment.size))
            ? Number(attachment.size)
            : 0,
        v: version,
        iv,
    };
}

/**
 * @param {string|null|undefined} plaintext
 * @returns {{
 *   text: string,
 *   attachment: {
 *     path: string,
 *     name: string,
 *     content_type: string,
 *     size: number,
 *     v: number,
 *     iv: string,
 *   }|null,
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
 *   attachment: {
 *     path: string,
 *     name: string,
 *     content_type: string,
 *     size: number,
 *     v: number,
 *     iv: string,
 *   }|null,
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
