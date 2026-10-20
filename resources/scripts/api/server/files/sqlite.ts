import axios from 'axios';
import getFileDownloadUrl from '@/api/server/files/getFileDownloadUrl';
import getFileUploadUrl from '@/api/server/files/getFileUploadUrl';

/* eslint-disable @typescript-eslint/no-explicit-any */

// The files the panel opens as SQLite databases.
export const isSqliteFile = (name: string): boolean => /\.(db|sqlite|sqlite3|db3)$/i.test(name);

// Above this size the database is not opened in the browser (it is loaded whole in memory).
export const MAX_SQLITE_BYTES = 150 * 1024 * 1024;

const BASE = '/js/vendor/sql.js/';

let loading: Promise<any> | null = null;

/**
 * Loads sql.js (SQLite compiled to WebAssembly, shipped in public/js/vendor/sql.js) once, when a database is first
 * opened, so it costs nothing to the other pages.
 */
export const loadSqlJs = (): Promise<any> => {
    if (loading) return loading;
    loading = new Promise((resolve, reject) => {
        const start = () =>
            (window as any)
                .initSqlJs({ locateFile: (file: string) => BASE + file })
                .then(resolve)
                .catch(reject);
        if ((window as any).initSqlJs) return start();
        const script = document.createElement('script');
        script.src = BASE + 'sql-wasm.js';
        script.async = true;
        script.onload = start;
        script.onerror = () => {
            loading = null;
            reject(new Error('Could not load the SQLite reader.'));
        };
        document.head.appendChild(script);
    });

    return loading;
};

// Fetches the raw bytes of a file, straight from the node (the panel would turn binary data into text).
export const downloadBytes = async (uuid: string, file: string): Promise<Uint8Array> => {
    const url = await getFileDownloadUrl(uuid, file);
    const response = await fetch(url);
    if (!response.ok) {
        throw new Error(`The node answered ${response.status}.`);
    }
    const length = Number(response.headers.get('Content-Length') || 0);
    if (length > MAX_SQLITE_BYTES) {
        throw new Error('The database is too big to be opened in the browser (150 MB at most).');
    }
    const buffer = await response.arrayBuffer();
    if (buffer.byteLength > MAX_SQLITE_BYTES) {
        throw new Error('The database is too big to be opened in the browser (150 MB at most).');
    }

    return new Uint8Array(buffer);
};

// Writes the bytes back over the file, through the upload of the node (no size limit of the panel in between).
export const uploadBytes = async (uuid: string, directory: string, name: string, bytes: Uint8Array): Promise<void> => {
    const url = await getFileUploadUrl(uuid);
    const form = new FormData();
    form.append('files', new File([bytes], name, { type: 'application/octet-stream' }));
    await axios.post(url, form, { params: { directory } });
};
