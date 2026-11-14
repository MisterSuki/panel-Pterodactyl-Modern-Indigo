import http from '@/api/http';

// A file the person keeps on the panel, with the link to share it.
export interface HostedFile {
    uuid: string;
    name: string;
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

/**
 * Sends a file in pieces (so it can be bigger than the upload limit of the web server), telling how far it is.
 */
export const uploadHostedFile = async (
    file: File,
    onProgress: (sent: number) => void,
    signal: { cancelled: boolean }
): Promise<HostedFile> => {
    const { data: begin } = await http.post('/api/client/hosting/uploads', { name: file.name, size: file.size });
    const id: string = begin.data.upload;
    const chunk: number = begin.data.chunk;

    try {
        for (let index = 0, offset = 0; offset < file.size; index++, offset += chunk) {
            if (signal.cancelled) throw new Error('cancelled');
            const piece = file.slice(offset, Math.min(file.size, offset + chunk));
            await http.post(`/api/client/hosting/uploads/${id}`, piece, {
                params: { index },
                headers: { 'Content-Type': 'application/octet-stream' },
                onUploadProgress: (event) => onProgress(offset + (event.loaded || 0)),
            });
            onProgress(Math.min(file.size, offset + piece.size));
        }
        const { data } = await http.post(`/api/client/hosting/uploads/${id}/complete`);

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
