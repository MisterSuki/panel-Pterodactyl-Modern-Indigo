import React, { useCallback, useEffect, useState } from 'react';
import getFileContents from '@/api/server/files/getFileContents';
import { httpErrorToHuman } from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import saveFileContents from '@/api/server/files/saveFileContents';
import FileManagerBreadcrumbs from '@/components/server/files/FileManagerBreadcrumbs';
import { useHistory, useLocation, useParams } from 'react-router';
import FileNameModal from '@/components/server/files/FileNameModal';
import Can from '@/components/elements/Can';
import FlashMessageRender from '@/components/FlashMessageRender';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { ServerError } from '@/components/elements/ScreenBlock';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import modes from '@/modes';
import useFlash from '@/plugins/useFlash';
import { ServerContext } from '@/state/server';
import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { encodePathSegments, hashToPath } from '@/helpers';
import { dirname } from 'pathe';
import CodemirrorEditor from '@/components/elements/CodemirrorEditor';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCircleNotch, faSave } from '@fortawesome/free-solid-svg-icons';
import { lookForName } from '@/components/server/files/fileIcon';
import { bytesToString } from '@/lib/formatters';

const getNewFileDraftKey = (uuid: string, directory: string) => `pterodactyl:new-file:${uuid}:${directory}`;

export default () => {
    const [error, setError] = useState('');
    const { action } = useParams<{ action: 'new' | string }>();
    const [loading, setLoading] = useState(action === 'edit');
    const [content, setContent] = useState('');
    const [modalVisible, setModalVisible] = useState(false);
    const [mode, setMode] = useState('text/plain');
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [status, setStatus] = useState({ line: 1, column: 1, lines: 1, length: 0 });
    const onEdited = useCallback(() => setDirty(true), []);

    const history = useHistory();
    const { hash } = useLocation();

    const id = ServerContext.useStoreState((state) => state.server.data!.id);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const setDirectory = ServerContext.useStoreActions((actions) => actions.files.setDirectory);
    const { addError, clearFlashes } = useFlash();

    const filePath = hashToPath(hash);
    const directory = action === 'new' ? filePath : dirname(filePath);
    const draftKey = action === 'new' ? getNewFileDraftKey(uuid, directory) : undefined;
    const saveDraft = useCallback(
        (value: string) => {
            if (!draftKey) return;

            if (value.length > 0) {
                sessionStorage.setItem(draftKey, value);
            } else {
                sessionStorage.removeItem(draftKey);
            }
        },
        [draftKey]
    );

    let fetchFileContent: null | (() => Promise<string>) = null;

    useEffect(() => {
        setDirectory(directory);
    }, [directory, setDirectory]);

    useEffect(() => {
        if (!draftKey) return;

        setContent(sessionStorage.getItem(draftKey) || '');
    }, [draftKey]);

    useEffect(() => {
        if (action === 'new') return;

        setError('');
        setLoading(true);
        getFileContents(uuid, filePath)
            .then(setContent)
            .catch((error) => {
                console.error(error);
                setError(httpErrorToHuman(error));
            })
            .then(() => setLoading(false));
    }, [action, uuid, filePath]);

    const save = async (name?: string) => {
        if (!fetchFileContent) {
            return;
        }

        setSaving(true);
        clearFlashes('files:view');

        let redirecting = false;

        try {
            const content = await fetchFileContent();

            await saveFileContents(uuid, name || filePath, content);
            setDirty(false);

            if (name) {
                if (draftKey) {
                    sessionStorage.removeItem(draftKey);
                }

                history.push(`/server/${id}/files/edit#/${encodePathSegments(name)}`);
                redirecting = true;
                return;
            }
        } catch (error) {
            console.error(error);
            addError({ message: httpErrorToHuman(error), key: 'files:view' });
        } finally {
            if (!redirecting) {
                setSaving(false);
            }
        }
    };

    if (error) {
        return <ServerError message={error} onBack={() => history.goBack()} />;
    }

    return (
        <PageContentBlock>
            <FlashMessageRender byKey={'files:view'} css={tw`mb-4`} />
            <ErrorBoundary>
                <div css={tw`mb-4`}>
                    <FileManagerBreadcrumbs withinFileEditor isNewFile={action !== 'edit'} />
                </div>
            </ErrorBoundary>
            {hash.replace(/^#/, '').endsWith('.pteroignore') && (
                <div css={tw`mb-4 p-4 border-l-4 bg-neutral-900 rounded border-cyan-400`}>
                    <p css={tw`text-neutral-300 text-sm`}>
                        You&apos;re editing a <code css={tw`font-mono bg-black rounded py-px px-1`}>.pteroignore</code>{' '}
                        file. Any files or directories listed in here will be excluded from backups. Wildcards are
                        supported by using an asterisk (<code css={tw`font-mono bg-black rounded py-px px-1`}>*</code>).
                        You can negate a prior rule by prepending an exclamation point (
                        <code css={tw`font-mono bg-black rounded py-px px-1`}>!</code>).
                    </p>
                </div>
            )}
            <FileNameModal
                visible={modalVisible}
                onDismissed={() => setModalVisible(false)}
                onFileNamed={(name) => {
                    setModalVisible(false);
                    save(name);
                }}
            />
            <div
                css={tw`relative rounded-2xl overflow-hidden`}
                style={{
                    background: 'linear-gradient(180deg, rgba(24, 24, 32, 0.86) 0%, rgba(10, 10, 16, 0.92) 100%)',
                    border: '1px solid rgba(255, 255, 255, 0.14)',
                    boxShadow: 'inset 0 1px 0 rgba(255, 255, 255, 0.22), 0 22px 54px -22px rgba(0, 0, 0, 0.75)',
                }}
            >
                <div
                    css={tw`flex flex-wrap items-center gap-3 px-4 py-2.5 border-b border-white/10`}
                    style={{ background: 'linear-gradient(180deg, rgba(255,255,255,0.09) 0%, rgba(255,255,255,0.03) 100%)' }}
                >
                    <span css={tw`hidden sm:flex items-center gap-1.5`} aria-hidden>
                        <span css={tw`w-3 h-3 rounded-full`} style={{ background: '#ff5f57' }} />
                        <span css={tw`w-3 h-3 rounded-full`} style={{ background: '#febc2e' }} />
                        <span css={tw`w-3 h-3 rounded-full`} style={{ background: '#28c840' }} />
                    </span>
                    <span css={tw`flex items-center gap-2 min-w-0 flex-1`}>
                        {(() => {
                            const look = lookForName(filePath);

                            return <FontAwesomeIcon icon={look.icon} style={{ color: look.color }} />;
                        })()}
                        <span css={tw`text-sm font-semibold text-neutral-50 truncate`}>
                            {action === 'edit' ? filePath.split('/').pop() : <span>New file</span>}
                        </span>
                        {dirty && (
                            <span
                                css={tw`flex-shrink-0 rounded-full px-2 py-0.5 text-2xs font-semibold bg-yellow-500 bg-opacity-20 text-yellow-300`}
                            >
                                Not saved
                            </span>
                        )}
                    </span>
                    <select
                        value={mode}
                        onChange={(e) => setMode(e.currentTarget.value)}
                        css={tw`rounded-full bg-white/10 border border-white/10 px-3 py-1 text-xs text-neutral-100 focus:outline-none`}
                    >
                        {modes.map((mode) => (
                            <option key={`${mode.name}_${mode.mime}`} value={mode.mime}>
                                {mode.name}
                            </option>
                        ))}
                    </select>
                    {action === 'edit' ? (
                        <Can action={'file.update'}>
                            <Button size={'xsmall'} onClick={() => save()} disabled={saving}>
                                <FontAwesomeIcon icon={saving ? faCircleNotch : faSave} spin={saving} css={tw`mr-1.5`} />
                                <span>Save Content</span>
                            </Button>
                        </Can>
                    ) : (
                        <Can action={'file.create'}>
                            <Button size={'xsmall'} onClick={() => setModalVisible(true)}>
                                <FontAwesomeIcon icon={faSave} css={tw`mr-1.5`} /> <span>Create File</span>
                            </Button>
                        </Can>
                    )}
                </div>
                <div css={tw`relative`}>
                    <SpinnerOverlay visible={loading} />
                    <CodemirrorEditor
                        mode={mode}
                        filename={hash.replace(/^#/, '')}
                        onModeChanged={setMode}
                        initialContent={content}
                        fetchContent={(value) => {
                            fetchFileContent = value;
                        }}
                        onContentSaved={() => {
                            if (action !== 'edit') {
                                setModalVisible(true);
                            } else {
                                save();
                            }
                        }}
                        onContentChanged={action === 'new' ? saveDraft : undefined}
                        onStatus={setStatus}
                        onEdited={onEdited}
                    />
                </div>
                <div
                    css={tw`flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 text-xs border-t border-white/10 tabular-nums`}
                    style={{ color: 'rgba(235, 235, 240, 0.55)' }}
                >
                    <span>
                        <span>Ln</span> {status.line}, <span>Col</span> {status.column}
                    </span>
                    <span>
                        {status.lines} <span>lines</span>
                    </span>
                    <span>{bytesToString(status.length)}</span>
                    <span>{modes.find((m) => m.mime === mode)?.name || 'Text'}</span>
                    <span css={tw`ml-auto hidden sm:inline`}>
                        <kbd css={tw`rounded bg-white/10 px-1.5 py-0.5 font-mono`}>Ctrl</kbd> +{' '}
                        <kbd css={tw`rounded bg-white/10 px-1.5 py-0.5 font-mono`}>S</kbd> <span>to save</span> ·{' '}
                        <kbd css={tw`rounded bg-white/10 px-1.5 py-0.5 font-mono`}>Ctrl</kbd> +{' '}
                        <kbd css={tw`rounded bg-white/10 px-1.5 py-0.5 font-mono`}>F</kbd> <span>to search</span>
                    </span>
                </div>
            </div>
        </PageContentBlock>
    );
};
