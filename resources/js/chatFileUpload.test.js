import { beforeEach, describe, expect, it, vi } from 'vitest';
import { deriveConversationKey } from './cryptography/e2ee/conversationKey.js';
import { importPublicKey } from './cryptography/e2ee/identity.js';
import { AES_GCM_TAG_BYTES, decryptBytes } from './cryptography/e2ee/messageCrypto.js';
import {
    ENCRYPTED_UPLOAD_CONTENT_TYPE,
    buildAttachmentMetadata,
    downloadAndDecryptAttachment,
    encryptedUploadSize,
    flattenUploadHeaders,
    hydrateMessageContent,
    parseChatMessageContent,
    prepareEncryptedUpload,
    requestDownloadLink,
    requestUploadLink,
    serializeChatMessageContent,
    uploadFileToLink,
    validateSelectedFile,
} from './chatFileUpload.js';

/**
 * @returns {Promise<{ privateKey: CryptoKey, publicJwk: JsonWebKey }>}
 */
async function generateEcdhKeyPair() {
    const keyPair = await globalThis.crypto.subtle.generateKey(
        { name: 'ECDH', namedCurve: 'P-256' },
        true,
        ['deriveBits', 'deriveKey'],
    );

    return {
        privateKey: keyPair.privateKey,
        publicJwk: await globalThis.crypto.subtle.exportKey('jwk', keyPair.publicKey),
    };
}

async function conversationKeyForPair(alice, bob, aliceId, bobId) {
    const bobPublic = await importPublicKey(bob.publicJwk);

    return deriveConversationKey(alice.privateKey, bobPublic, aliceId, bobId);
}

describe('flattenUploadHeaders', () => {
    it('flattens laravel list headers for fetch', () => {
        expect(flattenUploadHeaders({
            'Content-Type': ['text/plain'],
            'Content-Length': ['12'],
            'x-amz-acl': 'private',
        })).toEqual({
            'Content-Type': 'text/plain',
            'Content-Length': '12',
            'x-amz-acl': 'private',
        });
    });

    it('returns an empty object for invalid headers', () => {
        expect(flattenUploadHeaders(null)).toEqual({});
        expect(flattenUploadHeaders(undefined)).toEqual({});
    });
});

describe('validateSelectedFile', () => {
    it('rejects missing, empty, and oversized files', () => {
        expect(validateSelectedFile(null, 100)).toBe('No file selected.');
        expect(validateSelectedFile({ size: 0, name: 'empty.txt' }, 100))
            .toBe('The selected file is empty.');
        expect(validateSelectedFile({ size: 101, name: 'big.bin' }, 100))
            .toBe('The file may not be greater than 100 bytes.');
    });

    it('rejects plaintext that would exceed the limit after the gcm tag', () => {
        expect(validateSelectedFile({ size: 90, name: 'almost.bin' }, 100))
            .toBe('The file may not be greater than 100 bytes.');
    });

    it('accepts files whose encrypted size fits the limit', () => {
        expect(validateSelectedFile({ size: 100 - AES_GCM_TAG_BYTES, name: 'ok.txt' }, 100)).toBe('');
    });
});

describe('serializeChatMessageContent and parseChatMessageContent', () => {
    it('keeps plain text messages unchanged', () => {
        expect(serializeChatMessageContent({ text: 'hello' })).toBe('hello');
        expect(parseChatMessageContent('hello')).toEqual({
            text: 'hello',
            attachment: null,
        });
    });

    it('round-trips attachment envelopes with encryption metadata', () => {
        const attachment = {
            path: 'uploads/1/abc.txt',
            name: 'notes.txt',
            content_type: 'text/plain',
            size: 12,
            v: 1,
            iv: 'AAAAAAAAAAAAAAAA',
        };

        const serialized = serializeChatMessageContent({
            text: 'see file',
            attachment,
        });

        expect(JSON.parse(serialized)).toEqual({
            v: 1,
            text: 'see file',
            attachment,
        });

        expect(parseChatMessageContent(serialized)).toEqual({
            text: 'see file',
            attachment,
        });
    });

    it('drops attachments that are missing the encryption iv', () => {
        const serialized = JSON.stringify({
            v: 1,
            text: 'see file',
            attachment: {
                path: 'uploads/1/abc.txt',
                name: 'notes.txt',
                content_type: 'text/plain',
                size: 12,
            },
        });

        expect(parseChatMessageContent(serialized)).toEqual({
            text: 'see file',
            attachment: null,
        });
    });

    it('treats unrelated json as plain text', () => {
        expect(parseChatMessageContent('{"hello":"world"}')).toEqual({
            text: '{"hello":"world"}',
            attachment: null,
        });
    });
});

describe('hydrateMessageContent', () => {
    it('preserves decryption failures', () => {
        expect(hydrateMessageContent(null, 'Unable to decrypt this message.')).toEqual({
            plaintext: null,
            attachment: null,
            decryptionError: 'Unable to decrypt this message.',
        });
    });

    it('parses attachment envelopes after decryption', () => {
        const plaintext = serializeChatMessageContent({
            text: '',
            attachment: {
                path: 'uploads/1/file.pdf',
                name: 'file.pdf',
                content_type: 'application/pdf',
                size: 2048,
                v: 1,
                iv: 'BBBBBBBBBBBBBBBB',
            },
        });

        expect(hydrateMessageContent(plaintext, '')).toEqual({
            plaintext: '',
            attachment: {
                path: 'uploads/1/file.pdf',
                name: 'file.pdf',
                content_type: 'application/pdf',
                size: 2048,
                v: 1,
                iv: 'BBBBBBBBBBBBBBBB',
            },
            decryptionError: '',
        });
    });
});

describe('buildAttachmentMetadata', () => {
    it('builds metadata from a browser file, storage path, and encryption iv', () => {
        const file = new File(['hello'], 'hello.txt', { type: 'text/plain' });

        expect(buildAttachmentMetadata(file, 'uploads/9/01a06663-eb69-72ac-b7af-7053bf13f690.txt', {
            iv: 'CCCCCCCCCCCCCCCC',
            v: 1,
        })).toEqual({
            path: 'uploads/9/01a06663-eb69-72ac-b7af-7053bf13f690.txt',
            name: 'hello.txt',
            content_type: 'text/plain',
            size: 5,
            v: 1,
            iv: 'CCCCCCCCCCCCCCCC',
        });
    });

    it('falls back to octet-stream when the browser omits a type', () => {
        const file = new File(['abc'], 'mystery.bin');

        expect(buildAttachmentMetadata(file, 'uploads/9/01a06663-eb69-72ac-b7af-7053bf13f690.bin', {
            iv: 'DDDDDDDDDDDDDDDD',
        }).content_type).toBe('application/octet-stream');
    });
});

describe('prepareEncryptedUpload', () => {
    it('encrypts file bytes with the conversation key', async () => {
        const alice = await generateEcdhKeyPair();
        const bob = await generateEcdhKeyPair();
        const conversationKey = await conversationKeyForPair(alice, bob, 1, 2);
        const file = new File(['hello'], 'hello.txt', { type: 'text/plain' });

        const prepared = await prepareEncryptedUpload(file, conversationKey);
        const decrypted = await decryptBytes(
            prepared.body,
            prepared.iv,
            conversationKey,
            prepared.v,
        );

        expect(prepared.uploadContentType).toBe(ENCRYPTED_UPLOAD_CONTENT_TYPE);
        expect(prepared.uploadSize).toBe(encryptedUploadSize(file.size));
        expect(new TextDecoder().decode(decrypted)).toBe('hello');
    });
});

describe('requestUploadLink and uploadFileToLink', () => {
    beforeEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('requests a signed upload link for encrypted ciphertext', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            status: 201,
            json: async () => ({
                url: 'https://r2.example/upload',
                headers: { 'Content-Type': [ENCRYPTED_UPLOAD_CONTENT_TYPE] },
                path: 'uploads/1/01a06663-eb69-72ac-b7af-7053bf13f690',
                max_file_bytes: 10_000_000,
                expires_in: 300,
            }),
        });

        vi.stubGlobal('fetch', fetchMock);

        const link = await requestUploadLink({
            uploadsUrl: '/api/uploads',
            csrfToken: 'token',
            contentType: ENCRYPTED_UPLOAD_CONTENT_TYPE,
            size: encryptedUploadSize(5),
        });

        expect(fetchMock).toHaveBeenCalledWith('/api/uploads', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': 'token',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                content_type: ENCRYPTED_UPLOAD_CONTENT_TYPE,
                size: encryptedUploadSize(5),
            }),
        });
        expect(link.path).toBe('uploads/1/01a06663-eb69-72ac-b7af-7053bf13f690');
    });

    it('surfaces storage capacity errors', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: false,
            status: 507,
        }));

        await expect(requestUploadLink({
            uploadsUrl: '/api/uploads',
            csrfToken: 'token',
            contentType: ENCRYPTED_UPLOAD_CONTENT_TYPE,
            size: 21,
        })).rejects.toThrow(/capacity/i);
    });

    it('puts ciphertext to the signed upload url', async () => {
        const body = new Uint8Array([1, 2, 3, 4, 5]).buffer;
        const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200 });

        vi.stubGlobal('fetch', fetchMock);

        await uploadFileToLink(body, {
            url: 'https://r2.example/upload',
            headers: {
                'Content-Type': [ENCRYPTED_UPLOAD_CONTENT_TYPE],
                'Content-Length': ['5'],
            },
        });

        expect(fetchMock).toHaveBeenCalledWith('https://r2.example/upload', {
            method: 'PUT',
            headers: {
                'Content-Type': ENCRYPTED_UPLOAD_CONTENT_TYPE,
                'Content-Length': '5',
            },
            body,
        });
    });
});

describe('requestDownloadLink and downloadAndDecryptAttachment', () => {
    beforeEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('requests a signed download link for an attachment path', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({
                url: 'https://r2.example/download',
                path: 'uploads/1/01a06663-eb69-72ac-b7af-7053bf13f690.bin',
                expires_in: 300,
            }),
        });

        vi.stubGlobal('fetch', fetchMock);

        const link = await requestDownloadLink({
            downloadsUrl: '/api/uploads/download',
            csrfToken: 'token',
            path: 'uploads/1/01a06663-eb69-72ac-b7af-7053bf13f690.bin',
        });

        expect(fetchMock).toHaveBeenCalledWith('/api/uploads/download', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': 'token',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                path: 'uploads/1/01a06663-eb69-72ac-b7af-7053bf13f690.bin',
            }),
        });
        expect(link.url).toBe('https://r2.example/download');
    });

    it('downloads, decrypts, and saves the plaintext attachment', async () => {
        const alice = await generateEcdhKeyPair();
        const bob = await generateEcdhKeyPair();
        const conversationKey = await conversationKeyForPair(alice, bob, 1, 2);
        const encrypted = await prepareEncryptedUpload(
            new File(['secret-file'], 'notes.txt', { type: 'text/plain' }),
            conversationKey,
        );
        const saved = [];

        vi.stubGlobal('fetch', vi.fn()
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                json: async () => ({
                    url: 'https://r2.example/download',
                    path: 'uploads/1/01a06663-eb69-72ac-b7af-7053bf13f690.txt',
                    expires_in: 300,
                }),
            })
            .mockResolvedValueOnce({
                ok: true,
                status: 200,
                arrayBuffer: async () => encrypted.body,
            }));

        await downloadAndDecryptAttachment({
            attachment: {
                path: 'uploads/1/01a06663-eb69-72ac-b7af-7053bf13f690.txt',
                name: 'notes.txt',
                content_type: 'text/plain',
                size: 11,
                v: encrypted.v,
                iv: encrypted.iv,
            },
            conversationKey,
            downloadsUrl: '/api/uploads/download',
            csrfToken: 'token',
            saveBlob: (blob, filename) => {
                saved.push({ blob, filename });
            },
        });

        expect(saved).toHaveLength(1);
        expect(saved[0].filename).toBe('notes.txt');
        expect(saved[0].blob.type).toBe('text/plain');
        expect(await saved[0].blob.text()).toBe('secret-file');
    });

    it('surfaces authorization failures from the download link endpoint', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: false,
            status: 403,
        }));

        await expect(requestDownloadLink({
            downloadsUrl: '/api/uploads/download',
            csrfToken: 'token',
            path: 'uploads/1/01a06663-eb69-72ac-b7af-7053bf13f690.bin',
        })).rejects.toThrow(/authorized/i);
    });
});
