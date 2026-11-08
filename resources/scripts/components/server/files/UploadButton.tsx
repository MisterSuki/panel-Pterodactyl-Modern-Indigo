import axios, { AxiosProgressEvent } from 'axios';
import getFileUploadUrl from '@/api/server/files/getFileUploadUrl';
import tw from 'twin.macro';
import { Button } from '@/components/elements/button/index';
import React, { useEffect, useRef } from 'react';
import { ModalMask } from '@/components/elements/Modal';
import Fade from '@/components/elements/Fade';
import useEventListener from '@/plugins/useEventListener';
import { useFlashKey } from '@/plugins/useFlash';
import useFileManagerSwr from '@/plugins/useFileManagerSwr';
import { ServerContext } from '@/state/server';
import { WithClassname } from '@/components/types';
import Portal from '@/components/elements/Portal';
import { CloudUploadIcon } from '@heroicons/react/outline';
import { useSignal } from '@preact/signals-react';

// A file to send, and where it goes under the folder that is open: "my-site/css/style.css" for a file of a folder.
interface Upload {
    file: File;
    path: string;
}

// Files are sent a few at a time: a folder of thousands of files would otherwise open thousands of connections.
const PARALLEL_UPLOADS = 4;

// Every file of what was dropped, with the folders it was in. It must be read during the drop: the browser forgets
// the dropped items right after.
const readDropped = (items: DataTransferItemList): Promise<Upload[]> => {
    const entries = Array.from(items)
        .map((item) => (item.kind === 'file' ? item.webkitGetAsEntry() : null))
        .filter((entry): entry is FileSystemEntry => entry !== null);

    const walk = (entry: FileSystemEntry, prefix: string): Promise<Upload[]> => {
        if (entry.isFile) {
            return new Promise((resolve) =>
                (entry as FileSystemFileEntry).file(
                    (file) => resolve([{ file, path: prefix + file.name }]),
                    () => resolve([])
                )
            );
        }
        const reader = (entry as FileSystemDirectoryEntry).createReader();
        const all: FileSystemEntry[] = [];
        // A folder is read in parts (about 100 entries at a time) until the reader gives nothing more.
        const readAll = (): Promise<FileSystemEntry[]> =>
            new Promise((resolve) =>
                reader.readEntries(
                    (part) => (part.length ? (all.push(...part), resolve(readAll())) : resolve(all)),
                    () => resolve(all)
                )
            );

        return readAll().then((children) =>
            Promise.all(children.map((child) => walk(child, prefix + entry.name + '/'))).then((lists) =>
                ([] as Upload[]).concat(...lists)
            )
        );
    };

    return Promise.all(entries.map((entry) => walk(entry, ''))).then((lists) => ([] as Upload[]).concat(...lists));
};

function isFileOrDirectory(event: DragEvent): boolean {
    if (!event.dataTransfer?.types) {
        return false;
    }

    return event.dataTransfer.types.some((value) => value.toLowerCase() === 'files');
}

export default ({ className }: WithClassname) => {
    const fileUploadInput = useRef<HTMLInputElement>(null);
    const folderUploadInput = useRef<HTMLInputElement>(null);

    const visible = useSignal(false);
    const timeouts = useSignal<NodeJS.Timeout[]>([]);

    const { mutate } = useFileManagerSwr();
    const { addError, clearAndAddHttpError } = useFlashKey('files');

    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const directory = ServerContext.useStoreState((state) => state.files.directory);
    const { clearFileUploads, removeFileUpload, pushFileUpload, setUploadProgress } = ServerContext.useStoreActions(
        (actions) => actions.files
    );

    useEventListener(
        'dragenter',
        (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (isFileOrDirectory(e)) {
                visible.value = true;
            }
        },
        { capture: true }
    );

    useEventListener('dragexit', () => (visible.value = false), { capture: true });

    useEventListener('keydown', () => (visible.value = false));

    useEffect(() => {
        // Not in the types of React: the input that picks a whole folder.
        folderUploadInput.current?.setAttribute('webkitdirectory', '');

        return () => timeouts.value.forEach(clearTimeout);
    }, []);

    const onUploadProgress = (data: AxiosProgressEvent, name: string) => {
        setUploadProgress({ name, loaded: data.loaded });
    };

    const onFileSubmission = (list: Upload[]) => {
        clearAndAddHttpError();
        if (list.length === 0) {
            return addError('There is nothing to send: the folder is empty.', 'Error');
        }

        const uploads = list.map(({ file, path }) => {
            const controller = new AbortController();
            pushFileUpload({
                name: path,
                data: { abort: controller, loaded: 0, total: file.size },
            });
            // Wings keeps only the name of the file: its folder is given apart, and Wings creates it when missing.
            const folder = path.includes('/') ? path.slice(0, path.lastIndexOf('/')) : '';
            const target = folder ? `${directory.replace(/\/+$/, '')}/${folder}` : directory;

            return () =>
                getFileUploadUrl(uuid).then((url) =>
                    axios
                        .post(
                            url,
                            { files: file },
                            {
                                signal: controller.signal,
                                headers: { 'Content-Type': 'multipart/form-data' },
                                params: { directory: target },
                                onUploadProgress: (data) => onUploadProgress(data, path),
                            }
                        )
                        .then(() => timeouts.value.push(setTimeout(() => removeFileUpload(path), 500)))
                );
        });

        // A few at a time, each next one as soon as one is done.
        let next = 0;
        const worker = (): Promise<void> => {
            const job = uploads[next++];

            return job ? job().then(worker) : Promise.resolve();
        };
        Promise.all(Array.from({ length: Math.min(PARALLEL_UPLOADS, uploads.length) }, worker))
            .then(() => mutate())
            .catch((error) => {
                clearFileUploads();
                clearAndAddHttpError(error);
            });
    };

    return (
        <>
            <Portal>
                <Fade appear in={visible.value} timeout={75} key={'upload_modal_mask'} unmountOnExit>
                    <ModalMask
                        onClick={() => (visible.value = false)}
                        onDragOver={(e) => e.preventDefault()}
                        onDrop={(e) => {
                            e.preventDefault();
                            e.stopPropagation();

                            visible.value = false;
                            if (!e.dataTransfer?.items.length) return;

                            readDropped(e.dataTransfer.items).then(onFileSubmission);
                        }}
                    >
                        <div className={'w-full flex items-center justify-center pointer-events-none'}>
                            <div
                                className={
                                    'flex items-center space-x-4 bg-black w-full ring-4 ring-blue-200 ring-opacity-60 rounded p-6 mx-10 max-w-sm'
                                }
                            >
                                <CloudUploadIcon className={'w-10 h-10 flex-shrink-0'} />
                                <p className={'font-header flex-1 text-lg text-neutral-100 text-center'}>
                                    Drop files or folders to upload.
                                </p>
                            </div>
                        </div>
                    </ModalMask>
                </Fade>
            </Portal>
            <input
                type={'file'}
                ref={fileUploadInput}
                css={tw`hidden`}
                onChange={(e) => {
                    if (!e.currentTarget.files) return;

                    onFileSubmission(Array.from(e.currentTarget.files).map((file) => ({ file, path: file.name })));
                    e.currentTarget.value = '';
                }}
                multiple
            />
            <input
                type={'file'}
                ref={folderUploadInput}
                css={tw`hidden`}
                onChange={(e) => {
                    if (!e.currentTarget.files) return;

                    // The browser gives each file with the path inside the chosen folder, the folder included.
                    onFileSubmission(
                        Array.from(e.currentTarget.files).map((file) => ({
                            file,
                            path: file.webkitRelativePath || file.name,
                        }))
                    );
                    e.currentTarget.value = '';
                }}
                multiple
            />
            <Button.Text
                className={className}
                onClick={() => folderUploadInput.current && folderUploadInput.current.click()}
            >
                Upload a folder
            </Button.Text>
            <Button className={className} onClick={() => fileUploadInput.current && fileUploadInput.current.click()}>
                Upload
            </Button>
        </>
    );
};
