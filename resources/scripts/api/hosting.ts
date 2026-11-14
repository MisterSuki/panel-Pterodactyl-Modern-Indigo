import http from '@/api/http';

// A file the person keeps on the panel, with the link to share it.
export interface HostedFile {
    uuid: string;
    name: string;
    // "" for the top, "Holidays/2026" for a folder in a folder.
    folder: string;
    mime: string;
    kind: 'image' | 'video' | 'audio' | 'other';
    // True when the browser shows it (a safe picture, video or sound); other files are downloaded.
    inline: boolean;
    size: number;
    public: boolean;
    views: number;
    url: string;
    createdAt: Date | null;
}

export interface HostingMeta {
    used: number;
    quota: number;
    maxFile: number;
    chunk: number;
    mediaOnly: boolean;
    publicAllowed: boolean;
}

/* eslint-disable camelcase */
const map = (data: any): HostedFile => ({
    uuid: data.uuid,
    name: data.name,
    folder: data.folder || '',
    mime: data.mime,
    kind: data.kind,
    inline: !!data.inline,
    size: data.size,
    public: !!data.public,
    views: data.views || 0,
    url: data.url,
    createdAt: data.created_at ? new Date(data.created_at) : null,
});

export const getHostedFiles = (): Promise<{ files: HostedFile[]; meta: HostingMeta }> =>
    http.get('/api/client/hosting').then(({ data }) => ({
        files: (data.data || []).map(map),
        meta: {
            used: data.meta.used,
            quota: data.meta.quota,
            maxFile: data.meta.max_file,
            chunk: data.meta.chunk,
            mediaOnly: !!data.meta.media_only,
            publicAllowed: !!data.meta.public_allowed,
        },
    }));
/* eslint-enable camelcase */

// The size of one piece (the same as the panel): a file this small goes in a single request.
const PIECE = 8 * 1024 * 1024;

const wait = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Runs a request again when the panel says "too many requests" (a folder of many files), after the time it asks for.
 */
const patiently = async <T>(request: () => Promise<T>, signal: { cancelled: boolean }): Promise<T> => {
    for (let attempt = 0; ; attempt++) {
        try {
            return await request();
        } catch (error: any) {
            const status = error && error.response ? error.response.status : 0;
            if (status !== 429 || attempt >= 20 || signal.cancelled) throw error;
            const after = parseInt(String(error.response.headers?.['retry-after'] || ''), 10);
            await wait(Math.min(60, Math.max(2, isNaN(after) ? 5 : after)) * 1000 + Math.random() * 1000);
        }
    }
};

/**
 * Sends a file: in one request when it is small, in pieces otherwise (so it can be bigger than the upload limit of the
 * web server), telling how far it is.
 */
export const uploadHostedFile = async (
    file: File,
    folder: string,
    onProgress: (sent: number) => void,
    signal: { cancelled: boolean }
): Promise<HostedFile> => {
    if (file.size <= PIECE) {
        const { data } = await patiently(
            () =>
                http.post('/api/client/hosting/uploads/direct', file, {
                    params: { name: file.name, size: file.size, folder },
                    headers: { 'Content-Type': 'application/octet-stream' },
                    onUploadProgress: (event) => onProgress(event.loaded || 0),
                }),
            signal
        );
        onProgress(file.size);

        return map(data.data);
    }

    const { data: begin } = await patiently(
        () => http.post('/api/client/hosting/uploads', { name: file.name, size: file.size, folder }),
        signal
    );
    const id: string = begin.data.upload;
    const chunk: number = begin.data.chunk;

    try {
        for (let index = 0, offset = 0; offset < file.size; index++, offset += chunk) {
            if (signal.cancelled) throw new Error('cancelled');
            const piece = file.slice(offset, Math.min(file.size, offset + chunk));
            const start = offset;
            await patiently(
                () =>
                    http.post(`/api/client/hosting/uploads/${id}`, piece, {
                        params: { index },
                        headers: { 'Content-Type': 'application/octet-stream' },
                        onUploadProgress: (event) => onProgress(start + (event.loaded || 0)),
                    }),
                signal
            );
            onProgress(Math.min(file.size, offset + piece.size));
        }
        const { data } = await patiently(() => http.post(`/api/client/hosting/uploads/${id}/complete`), signal);

        return map(data.data);
    } catch (error) {
        http.delete(`/api/client/hosting/uploads/${id}`).catch(() => undefined);
        throw error;
    }
};

export const updateHostedFile = (uuid: string, values: { name?: string; public?: boolean }): Promise<HostedFile> =>
    http.patch(`/api/client/hosting/${uuid}`, values).then(({ data }) => map(data.data));

export const deleteHostedFile = (uuid: string): Promise<void> =>
    http.delete(`/api/client/hosting/${uuid}`).then(() => undefined);

export type BulkAction = 'delete' | 'public' | 'private' | 'move';

export const bulkHostedFiles = (action: BulkAction, files: string[], folder?: string): Promise<number> =>
    http.post('/api/client/hosting/bulk', { action, files, folder }).then(({ data }) => data.data.count);
