import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCheck, faCopy, faEye, faLink, faTrashAlt, faUserShield } from '@fortawesome/free-solid-svg-icons';
import PageContentBlock from '@/components/elements/PageContentBlock';
import ContentBox from '@/components/elements/ContentBox';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import { createClaudeLink, deleteClaudeLink, getClaudeLinks, ClaudeLink } from '@/api/account/claude';
import { httpErrorToHuman } from '@/api/http';

const copyText = (text: string) => {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }
    const area = document.createElement('textarea');
    area.value = text;
    document.body.appendChild(area);
    area.select();
    document.execCommand('copy');
    document.body.removeChild(area);

    return Promise.resolve();
};

// Account > Claude: the links that connect the Claude app (claude.ai, Claude Desktop) to the panel as a connector, so
// Claude can look at the servers, read their console, start or stop them and fix their files.
export default () => {
    const [links, setLinks] = useState<ClaudeLink[] | null>(null);
    const [name, setName] = useState('Claude');
    const [readOnly, setReadOnly] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [created, setCreated] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);
    const [removing, setRemoving] = useState<ClaudeLink | null>(null);

    useEffect(() => {
        getClaudeLinks()
            .then(setLinks)
            .catch((e) => setError(httpErrorToHuman(e)));
    }, []);

    const create = () => {
        setBusy(true);
        setError('');
        createClaudeLink(name.trim() || 'Claude', readOnly)
            .then(({ link, url }) => {
                setLinks((list) => [link, ...(list || [])]);
                setCreated(url);
                setCopied(false);
            })
            .catch((e) => setError(httpErrorToHuman(e)))
            .then(() => setBusy(false));
    };

    const remove = () => {
        if (!removing) return;
        const link = removing;
        setRemoving(null);
        deleteClaudeLink(link.id)
            .then(() => setLinks((list) => (list || []).filter((l) => l.id !== link.id)))
            .catch((e) => setError(httpErrorToHuman(e)));
    };

    return (
        <PageContentBlock title={'Claude'}>
            <div css={tw`grid grid-cols-1 lg:grid-cols-2 gap-6`}>
                <ContentBox title={'Connect Claude to the panel'}>
                    <p css={tw`text-sm text-neutral-300`}>
                        Add the panel as a connector in the Claude app: you then ask Claude to look at your servers,
                        read their console, start, stop or restart them, send commands and fix their files. Claude acts
                        with the rights of your account, and asks you before each action.
                    </p>
                    <div css={tw`mt-4`}>
                        <label css={tw`block text-xs text-neutral-400 mb-1`} htmlFor={'claude-name'}>
                            Name of the link
                        </label>
                        <input
                            id={'claude-name'}
                            value={name}
                            maxLength={60}
                            onChange={(e) => setName(e.target.value)}
                            css={tw`w-full h-9 rounded-md border border-neutral-600 bg-neutral-900 px-3 text-sm text-neutral-100 outline-none focus:border-neutral-500`}
                        />
                    </div>
                    <label css={tw`mt-3 flex items-start gap-2 text-sm text-neutral-200 cursor-pointer`}>
                        <input
                            type={'checkbox'}
                            checked={readOnly}
                            onChange={(e) => setReadOnly(e.target.checked)}
                            css={tw`mt-1`}
                        />
                        <span>
                            <span>Read only</span>
                            <span css={tw`block text-xs text-neutral-400`}>
                                Claude can look at the servers, the console and the files, but cannot change anything.
                            </span>
                        </span>
                    </label>
                    <div css={tw`mt-4 flex justify-end`}>
                        <Button onClick={create} disabled={busy}>
                            <FontAwesomeIcon icon={faLink} css={tw`mr-2`} />
                            <span>Create a link</span>
                        </Button>
                    </div>
                    {error && <p css={tw`mt-3 text-sm text-red-400`}>{error}</p>}
                </ContentBox>

                <ContentBox title={'How to add it in Claude'}>
                    <ol css={tw`list-decimal pl-5 space-y-2 text-sm text-neutral-300`}>
                        <li>Create a link here and copy its address (it is shown only once).</li>
                        <li>
                            In Claude (claude.ai or Claude Desktop): Settings, then Connectors, then Add a custom
                            connector.
                        </li>
                        <li>
                            Give it a name (for example the name of the panel) and paste the address. Leave the OAuth
                            fields empty.
                        </li>
                        <li>In a conversation, turn the connector on and ask: “What is the state of my servers?”</li>
                    </ol>
                    <p css={tw`mt-3 text-xs text-neutral-500`}>
                        The address is a secret: anyone who has it can use your servers like Claude. Delete the link if
                        it leaked, and create another one. Every change of a file made by Claude can be undone (AI
                        assistant of the server, history).
                    </p>
                </ContentBox>
            </div>

            <ContentBox title={'Your links'} css={tw`mt-6`}>
                {links === null ? (
                    <p css={tw`text-sm text-neutral-400`}>Loading...</p>
                ) : links.length === 0 ? (
                    <p css={tw`text-sm text-neutral-400`}>No link yet.</p>
                ) : (
                    <div css={tw`flex flex-col gap-2`}>
                        {links.map((link) => (
                            <div
                                key={link.id}
                                css={tw`flex items-center gap-3 rounded-lg border border-neutral-700 bg-neutral-800 px-3 py-2`}
                            >
                                <FontAwesomeIcon
                                    icon={link.readOnly ? faEye : faUserShield}
                                    css={tw`text-neutral-400`}
                                    fixedWidth
                                />
                                <div css={tw`min-w-0 flex-1`}>
                                    <p css={tw`text-sm text-neutral-100 truncate`} data-no-translate>
                                        {link.name}
                                    </p>
                                    <p css={tw`text-xs text-neutral-400`}>
                                        <span>{link.readOnly ? 'Read only' : 'Full access'}</span>
                                        {' · '}
                                        {link.lastUsedAt ? (
                                            <>
                                                <span>Last used</span> {link.lastUsedAt.toLocaleString()}
                                            </>
                                        ) : (
                                            <span>Never used</span>
                                        )}
                                    </p>
                                </div>
                                <button
                                    type={'button'}
                                    onClick={() => setRemoving(link)}
                                    title={'Delete'}
                                    aria-label={'Delete'}
                                    css={tw`flex items-center justify-center w-8 h-8 rounded-md text-neutral-400 hover:text-red-400 hover:bg-neutral-700`}
                                >
                                    <FontAwesomeIcon icon={faTrashAlt} />
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </ContentBox>

            <Dialog open={created !== null} onClose={() => setCreated(null)} title={'Your link for Claude'}>
                <p css={tw`mt-2 text-sm text-neutral-300`}>
                    Copy it now: it will not be shown again. Paste it in Claude, Settings, Connectors, Add a custom
                    connector.
                </p>
                <div css={tw`mt-3 flex items-center gap-2`}>
                    <input
                        readOnly
                        value={created || ''}
                        onFocus={(e) => e.currentTarget.select()}
                        css={tw`flex-1 min-w-0 h-9 rounded-md border border-neutral-600 bg-neutral-900 px-3 font-mono text-xs text-neutral-100 outline-none`}
                    />
                    <Button.Text
                        size={Button.Sizes.Small}
                        onClick={() => created && copyText(created).then(() => setCopied(true))}
                    >
                        <FontAwesomeIcon icon={copied ? faCheck : faCopy} />
                    </Button.Text>
                </div>
                <Dialog.Footer>
                    <Button onClick={() => setCreated(null)}>Done</Button>
                </Dialog.Footer>
            </Dialog>

            <Dialog.Confirm
                open={removing !== null}
                title={'Delete this link?'}
                confirm={'Delete'}
                onClose={() => setRemoving(null)}
                onConfirmed={remove}
            >
                Claude loses the access to the panel through this link at once.
            </Dialog.Confirm>
        </PageContentBlock>
    );
};
