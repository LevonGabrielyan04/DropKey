import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    buildAttachmentMetadata,
    flattenUploadHeaders,
    hydrateMessageContent,
    parseChatMessageContent,
    requestUploadLink,
    serializeChatMessageContent,
    uploadFileToLink,
    validateSelectedFile,
} from './chatFileUpload.js';

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

    it('accepts files within the size limit', () => {
        expect(validateSelectedFile({ size: 100, name: 'ok.txt' }, 100)).toBe('');
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

    it('round-trips attachment envelopes', () => {
        const attachment = {
            path: 'uploads/1/abc.txt',
            name: 'notes.txt',
            content_type: 'text/plain',
            size: 12,
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
            },
        });

        expect(hydrateMessageContent(plaintext, '')).toEqual({
            plaintext: '',
            attachment: {
                path: 'uploads/1/file.pdf',
                name: 'file.pdf',
                content_type: 'application/pdf',
                size: 2048,
            },
            decryptionError: '',
        });
    });
});

describe('buildAttachmentMetadata', () => {
    it('builds metadata from a browser file and storage path', () => {
        const file = new File(['hello'], 'hello.txt', { type: 'text/plain' });

        expect(buildAttachmentMetadata(file, 'uploads/9/ulid.txt')).toEqual({
            path: 'uploads/9/ulid.txt',
            name: 'hello.txt',
            content_type: 'text/plain',
            size: 5,
        });
    });

    it('falls back to octet-stream when the browser omits a type', () => {
        const file = new File(['abc'], 'mystery.bin');

        expect(buildAttachmentMetadata(file, 'uploads/9/ulid.bin').content_type)
            .toBe('application/octet-stream');
    });
});

describe('requestUploadLink and uploadFileToLink', () => {
    beforeEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('requests a signed upload link from the api', async () => {
        const file = new File(['hello'], 'hello.txt', { type: 'text/plain' });
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            status: 201,
            json: async () => ({
                url: 'https://r2.example/upload',
                headers: { 'Content-Type': ['text/plain'] },
                path: 'uploads/1/hello.txt',
                max_file_bytes: 10_000_000,
                expires_in: 300,
            }),
        });

        vi.stubGlobal('fetch', fetchMock);

        const link = await requestUploadLink({
            uploadsUrl: '/api/uploads',
            csrfToken: 'token',
            file,
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
                filename: 'hello.txt',
                content_type: 'text/plain',
                size: 5,
            }),
        });
        expect(link.path).toBe('uploads/1/hello.txt');
    });

    it('surfaces storage capacity errors', async () => {
        const file = new File(['hello'], 'hello.txt', { type: 'text/plain' });

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: false,
            status: 507,
        }));

        await expect(requestUploadLink({
            uploadsUrl: '/api/uploads',
            csrfToken: 'token',
            file,
        })).rejects.toThrow(/capacity/i);
    });

    it('puts the file to the signed upload url', async () => {
        const file = new File(['hello'], 'hello.txt', { type: 'text/plain' });
        const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200 });

        vi.stubGlobal('fetch', fetchMock);

        await uploadFileToLink(file, {
            url: 'https://r2.example/upload',
            headers: {
                'Content-Type': ['text/plain'],
                'Content-Length': ['5'],
            },
        });

        expect(fetchMock).toHaveBeenCalledWith('https://r2.example/upload', {
            method: 'PUT',
            headers: {
                'Content-Type': 'text/plain',
                'Content-Length': '5',
            },
            body: file,
        });
    });
});
