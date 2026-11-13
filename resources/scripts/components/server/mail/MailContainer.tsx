import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import { Link } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faCheckCircle,
    faCircleNotch,
    faCopy,
    faEnvelope,
    faExclamationTriangle,
    faExternalLinkAlt,
    faKey,
    faMagic,
    faPlus,
    faSyncAlt,
    faTimesCircle,
    faTrashAlt,
} from '@fortawesome/free-solid-svg-icons';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import Can from '@/components/elements/Can';
import { ServerContext } from '@/state/server';
import useFlash, { useFlashKey } from '@/plugins/useFlash';
import { bytesToString } from '@/lib/formatters';
import {
    checkMail,
    createMailBox,
    deleteMailBox,
    disableMail,
    enableMail,
    getMail,
    MailBox,
    MailPage,
    MailRecord,
    setMailPassword,
} from '@/api/server/mail';

const field = tw`w-full rounded-xl bg-black/30 border border-white/10 px-3 py-2.5 text-sm text-neutral-100 focus:outline-none focus:border-primary-500`;
const label = tw`block text-sm font-medium text-neutral-200 mb-1.5`;
const card = tw`rounded-2xl bg-neutral-800 border border-white/10 shadow-card overflow-hidden mt-6`;
const pill = tw`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap`;

// A strong password, made in the browser: 16 characters without the ones that are easy to confuse.
const makePassword = () => {
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789-_!';
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);

    return Array.from(bytes, (b) => alphabet[b % alphabet.length]).join('');
};

const Copy = ({ value }: { value: string }) => {
    const [done, setDone] = useState(false);

    return (
        <button
            type={'button'}
            title={'Copy'}
            css={tw`ml-2 text-neutral-400 hover:text-white focus:outline-none flex-shrink-0`}
            onClick={() => {
                navigator.clipboard?.writeText(value).then(() => {
                    setDone(true);
                    setTimeout(() => setDone(false), 1500);
                });
            }}
        >
            <FontAwesomeIcon icon={done ? faCheckCircle : faCopy} />
        </button>
    );
};

const Status = ({ status }: { status: MailRecord['status'] }) => {
    switch (status) {
        case 'ok':
            return (
                <span css={[pill, tw`bg-green-500/10 text-green-300`]}>
                    <FontAwesomeIcon icon={faCheckCircle} /> <span>Found</span>
                </span>
            );
        case 'wrong':
            return (
                <span css={[pill, tw`bg-yellow-500/10 text-yellow-300`]}>
                    <FontAwesomeIcon icon={faExclamationTriangle} /> <span>Different</span>
                </span>
            );
        case 'missing':
            return (
                <span css={[pill, tw`bg-red-500/10 text-red-300`]}>
                    <FontAwesomeIcon icon={faTimesCircle} /> <span>Missing</span>
                </span>
            );
        case 'pending':
            return (
                <span css={[pill, tw`bg-white/10 text-neutral-300`]}>
                    <FontAwesomeIcon icon={faCircleNotch} spin /> <span>Being made</span>
                </span>
            );
        case 'unknown':
            return <span css={[pill, tw`bg-white/10 text-neutral-300`]}>?</span>;
        default:
            return null;
    }
};

const Usage = ({ box }: { box: MailBox }) => {
    const total = box.quotaMb * 1024 * 1024;
    const used = box.usedBytes ?? 0;
    const percent = Math.min(100, Math.round((used / total) * 100));

    return (
        <div css={tw`w-36 flex-shrink-0 hidden sm:block`}>
            <div css={tw`h-1.5 rounded-full bg-white/10 overflow-hidden`}>
                <div
                    css={tw`h-full rounded-full`}
                    style={{
                        width: `${Math.max(2, percent)}%`,
                        background: percent >= 90 ? '#ff453a' : percent >= 75 ? '#ff9f0a' : 'rgb(var(--pd-accent-500))',
                    }}
                />
            </div>
            <p css={tw`text-2xs text-neutral-400 mt-1 tabular-nums`}>
                {box.usedBytes === null ? '–' : bytesToString(used)} / {bytesToString(total)}
            </p>
        </div>
    );
};

const Setting = ({ name, value }: { name: string; value: string }) => (
    <div css={tw`flex items-center justify-between gap-3 py-2 border-b border-white/5 last:border-0`}>
        <span css={tw`text-sm text-neutral-400`}>{name}</span>
        <span css={tw`flex items-center text-sm text-neutral-100 font-mono min-w-0`}>
            <span css={tw`truncate`}>{value}</span>
            <Copy value={value} />
        </span>
    </div>
);

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const id = ServerContext.useStoreState((state) => state.server.data!.id);
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('server:mail');
    const { addFlash } = useFlash();

    const [page, setPage] = useState<MailPage | null>(null);
    const [busy, setBusy] = useState(false);
    const [checking, setChecking] = useState(false);
    const [local, setLocal] = useState('');
    const [password, setPassword] = useState('');
    const [shown, setShown] = useState(false);
    const [changing, setChanging] = useState<MailBox | null>(null);
    const [newPassword, setNewPassword] = useState('');
    const [removing, setRemoving] = useState<MailBox | null>(null);
    const [disabling, setDisabling] = useState(false);

    const run = (action: Promise<MailPage>, done?: () => void) => {
        clearFlashes();
        setBusy(true);
        action
            .then((next) => {
                setPage(next);
                done && done();
            })
            .catch((error) => clearAndAddHttpError(error))
            .then(() => setBusy(false));
    };

    useEffect(() => {
        getMail(uuid)
            .then(setPage)
            .catch((error) => clearAndAddHttpError(error));
    }, [uuid]);

    // The DKIM key is made by the mail server a little after email is turned on: the page follows it by itself.
    const waiting = !!page?.domain && !page.domain.dkimReady;
    useEffect(() => {
        if (!waiting) return;
        const timer = window.setInterval(() => {
            !document.hidden &&
                getMail(uuid)
                    .then(setPage)
                    .catch(() => undefined);
        }, 10000);

        return () => window.clearInterval(timer);
    }, [waiting, uuid]);

    const check = () => {
        clearFlashes();
        setChecking(true);
        checkMail(uuid)
            .then(setPage)
            .catch((error) => clearAndAddHttpError(error))
            .then(() => setChecking(false));
    };

    const create = () =>
        run(createMailBox(uuid, local, password), () => {
            addFlash({
                key: 'server:mail',
                type: 'success',
                title: 'Success',
                message: 'The mailbox is ready. Keep its password: the panel cannot show it again.',
            });
            setLocal('');
            setPassword('');
            setShown(false);
        });

    const domain = page?.domain;
    const full = !!page && page.boxes.length >= page.max;
    const allOk = !!domain && domain.records.every((r) => r.status === 'ok');

    return (
        <ServerContentBlock title={'Email'}>
            <FlashMessageRender byKey={'server:mail'} css={tw`mb-4`} />

            <div css={tw`flex flex-wrap items-center gap-3`}>
                <span
                    css={tw`flex items-center justify-center w-10 h-10 rounded-lg border border-neutral-600 bg-neutral-900 text-neutral-300 flex-shrink-0`}
                >
                    <FontAwesomeIcon icon={faEnvelope} />
                </span>
                <div css={tw`flex-1 min-w-0`}>
                    <h1 css={tw`text-xl text-neutral-50 font-semibold`}>Email</h1>
                    <p css={tw`text-sm text-neutral-400`}>Mailboxes on your domain, in your apps and in a webmail.</p>
                </div>
                {domain && page?.webmail && (
                    <a href={page.webmail} target={'_blank'} rel={'noopener noreferrer'} css={tw`no-underline`}>
                        <Button type={'button'}>
                            <FontAwesomeIcon icon={faExternalLinkAlt} css={tw`mr-2`} /> <span>Open the webmail</span>
                        </Button>
                    </a>
                )}
            </div>

            {!page ? (
                <div css={tw`flex justify-center py-16 text-neutral-400`}>
                    <FontAwesomeIcon icon={faCircleNotch} spin size={'2x'} />
                </div>
            ) : !page.available ? (
                <div css={[card, tw`p-8 text-center`]}>
                    <p css={tw`text-neutral-200 font-medium`}>Email is not available on this panel yet.</p>
                    <p css={tw`text-sm text-neutral-400 mt-1`}>The administrator has not set up the mail server.</p>
                </div>
            ) : !domain ? (
                <div css={[card, tw`p-8 text-center`]}>
                    {page.candidate ? (
                        <>
                            <p css={tw`text-neutral-100 font-medium`}>
                                <span>Turn on email for</span> <strong>{page.candidate}</strong>
                            </p>
                            <p css={tw`text-sm text-neutral-400 mt-1 mb-5`}>
                                contact@{page.candidate} · <span>Mailboxes</span>: {page.max} ×{' '}
                                {bytesToString(page.quotaMb * 1024 * 1024)}
                            </p>
                            <Can action={'allocation.create'}>
                                <Button type={'button'} disabled={busy} onClick={() => run(enableMail(uuid))}>
                                    {busy ? (
                                        <FontAwesomeIcon icon={faCircleNotch} spin />
                                    ) : (
                                        <>
                                            <FontAwesomeIcon icon={faEnvelope} css={tw`mr-2`} />
                                            <span>Turn on email</span>
                                        </>
                                    )}
                                </Button>
                            </Can>
                        </>
                    ) : (
                        <>
                            <p css={tw`text-neutral-100 font-medium`}>Your own domain is needed first.</p>
                            <p css={tw`text-sm text-neutral-400 mt-1 mb-5`}>
                                Add it in the Website tab and wait until it works: email then uses the same domain.
                            </p>
                            <Link to={`/server/${id}/web`} css={tw`no-underline`}>
                                <Button.Text type={'button'}>
                                    <span>Go to the Website tab</span>
                                </Button.Text>
                            </Link>
                        </>
                    )}
                </div>
            ) : (
                <>
                    {!page.online && (
                        <div
                            css={tw`mt-6 rounded-xl border border-yellow-500/30 bg-yellow-500/10 px-4 py-3 text-sm text-yellow-200`}
                        >
                            <FontAwesomeIcon icon={faExclamationTriangle} css={tw`mr-2`} />
                            <span>
                                The mail server is not answering right now: changes apply as soon as it is back.
                            </span>
                        </div>
                    )}

                    <div css={card}>
                        <div css={tw`flex flex-wrap items-center gap-3 px-4 py-3 border-b border-white/10`}>
                            <div css={tw`flex-1 min-w-0`}>
                                <h2 css={tw`text-base text-neutral-50 font-semibold`}>DNS records</h2>
                                <p css={tw`text-xs text-neutral-400`}>
                                    {allOk ? (
                                        <span>Everything is in place: mail is delivered and trusted.</span>
                                    ) : (
                                        <span>
                                            Add them where your domain is managed (Cloudflare, OVH...), then check.
                                        </span>
                                    )}
                                </p>
                            </div>
                            <Button.Text type={'button'} size={Button.Sizes.Small} onClick={check} disabled={checking}>
                                <FontAwesomeIcon icon={faSyncAlt} spin={checking} css={tw`mr-1.5`} /> <span>Check</span>
                            </Button.Text>
                        </div>
                        <div css={tw`overflow-x-auto`}>
                            <table css={tw`w-full text-sm`}>
                                <thead>
                                    <tr css={tw`text-left text-xs text-neutral-500`}>
                                        <th css={tw`px-4 py-2 font-semibold`}>Type</th>
                                        <th css={tw`px-4 py-2 font-semibold`}>Name</th>
                                        <th css={tw`px-4 py-2 font-semibold`}>Value</th>
                                        <th css={tw`px-4 py-2 font-semibold`} />
                                    </tr>
                                </thead>
                                <tbody>
                                    {domain.records.map((r, i) => (
                                        <tr key={i} css={tw`border-t border-white/5 align-top`}>
                                            <td css={tw`px-4 py-2.5 font-semibold text-primary-300`}>{r.type}</td>
                                            <td css={tw`px-4 py-2.5`}>
                                                <span css={tw`flex items-center font-mono text-neutral-100`}>
                                                    {r.name}
                                                    <Copy value={r.name} />
                                                </span>
                                            </td>
                                            <td css={tw`px-4 py-2.5 max-w-md`}>
                                                {r.value ? (
                                                    <span css={tw`flex items-start font-mono text-neutral-100`}>
                                                        <span css={tw`break-all`}>
                                                            {r.priority !== null && (
                                                                <span css={tw`text-neutral-400`}>{r.priority} </span>
                                                            )}
                                                            {r.value.length > 90 ? `${r.value.slice(0, 90)}…` : r.value}
                                                        </span>
                                                        <Copy value={r.value} />
                                                    </span>
                                                ) : (
                                                    <span css={tw`text-neutral-400`}>
                                                        The mail server is making the key, about a minute.
                                                    </span>
                                                )}
                                            </td>
                                            <td css={tw`px-4 py-2.5 text-right`}>
                                                <Status status={r.status} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <p css={tw`px-4 py-3 text-xs text-neutral-400 border-t border-white/5`}>
                            Only one SPF record (TXT starting with v=spf1) per domain: if you already have one, add{' '}
                            <code css={tw`text-neutral-200`}>mx</code> to it instead of making a second one.
                        </p>
                    </div>

                    <div css={card}>
                        <div css={tw`flex flex-wrap items-center gap-3 px-4 py-3 border-b border-white/10`}>
                            <div css={tw`flex-1 min-w-0`}>
                                <h2 css={tw`text-base text-neutral-50 font-semibold`}>Mailboxes</h2>
                                <p css={tw`text-xs text-neutral-400`}>
                                    {page.boxes.length} / {page.max} ·{' '}
                                    <span>{bytesToString(page.quotaMb * 1024 * 1024)} each</span>
                                </p>
                            </div>
                        </div>
                        {page.boxes.length === 0 ? (
                            <p css={tw`px-4 py-6 text-center text-sm text-neutral-400`}>No mailbox yet.</p>
                        ) : (
                            page.boxes.map((box) => (
                                <div
                                    key={box.id}
                                    css={tw`flex items-center gap-4 px-4 py-3 border-b border-white/5 last:border-0`}
                                >
                                    <span
                                        css={tw`flex items-center justify-center w-9 h-9 rounded-full bg-neutral-700 text-neutral-200 flex-shrink-0 font-medium uppercase`}
                                    >
                                        {box.address.charAt(0)}
                                    </span>
                                    <span
                                        css={tw`flex items-center flex-1 min-w-0 text-sm text-neutral-50 font-medium`}
                                    >
                                        <span css={tw`truncate`}>{box.address}</span>
                                        <Copy value={box.address} />
                                    </span>
                                    <Usage box={box} />
                                    <Can action={'allocation.update'}>
                                        <button
                                            type={'button'}
                                            title={'Change the password'}
                                            onClick={() => {
                                                setNewPassword(makePassword());
                                                setChanging(box);
                                            }}
                                            css={tw`flex items-center justify-center w-8 h-8 rounded-full text-neutral-400 hover:text-white hover:bg-white/10`}
                                        >
                                            <FontAwesomeIcon icon={faKey} css={tw`text-sm`} />
                                        </button>
                                    </Can>
                                    <Can action={'allocation.delete'}>
                                        <button
                                            type={'button'}
                                            title={'Delete'}
                                            onClick={() => setRemoving(box)}
                                            css={tw`flex items-center justify-center w-8 h-8 rounded-full text-neutral-400 hover:text-red-300 hover:bg-white/10`}
                                        >
                                            <FontAwesomeIcon icon={faTrashAlt} css={tw`text-sm`} />
                                        </button>
                                    </Can>
                                </div>
                            ))
                        )}
                        <Can action={'allocation.create'}>
                            {!full && (
                                <div css={tw`px-4 py-4 border-t border-white/10 bg-black/10`}>
                                    <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-3`}>
                                        <div>
                                            <label css={label}>New address</label>
                                            <div css={tw`flex items-center gap-2`}>
                                                <input
                                                    css={field}
                                                    value={local}
                                                    maxLength={64}
                                                    placeholder={'contact'}
                                                    autoComplete={'off'}
                                                    onChange={(e) =>
                                                        setLocal(e.currentTarget.value.toLowerCase().replace(/\s/g, ''))
                                                    }
                                                />
                                                <span css={tw`text-sm text-neutral-300 whitespace-nowrap`}>
                                                    @{domain.hostname}
                                                </span>
                                            </div>
                                        </div>
                                        <div>
                                            <label css={label}>Password</label>
                                            <div css={tw`flex items-center gap-2`}>
                                                <input
                                                    css={[field, tw`font-mono`]}
                                                    type={shown ? 'text' : 'password'}
                                                    value={password}
                                                    maxLength={128}
                                                    autoComplete={'new-password'}
                                                    onChange={(e) => setPassword(e.currentTarget.value)}
                                                />
                                                <Button.Text
                                                    type={'button'}
                                                    title={'Generate'}
                                                    onClick={() => {
                                                        setPassword(makePassword());
                                                        setShown(true);
                                                    }}
                                                >
                                                    <FontAwesomeIcon icon={faMagic} />
                                                </Button.Text>
                                            </div>
                                            <p css={tw`text-xs text-neutral-400 mt-1.5`}>8 characters at least.</p>
                                        </div>
                                    </div>
                                    <div css={tw`flex justify-end mt-3`}>
                                        <Button
                                            type={'button'}
                                            disabled={busy || !local || password.length < 8}
                                            onClick={create}
                                        >
                                            {busy ? (
                                                <FontAwesomeIcon icon={faCircleNotch} spin />
                                            ) : (
                                                <>
                                                    <FontAwesomeIcon icon={faPlus} css={tw`mr-2`} />
                                                    <span>Create the mailbox</span>
                                                </>
                                            )}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Can>
                    </div>

                    <div css={tw`grid grid-cols-1 lg:grid-cols-2 gap-6`}>
                        <div css={[card, tw`px-4 py-3`]}>
                            <h2 css={tw`text-base text-neutral-50 font-semibold mb-1`}>Receiving (IMAP)</h2>
                            <Setting name={'Server'} value={page.hostname} />
                            <Setting name={'Port'} value={'993'} />
                            <Setting name={'Security'} value={'SSL/TLS'} />
                            <Setting name={'User name'} value={'contact@' + domain.hostname} />
                        </div>
                        <div css={[card, tw`px-4 py-3`]}>
                            <h2 css={tw`text-base text-neutral-50 font-semibold mb-1`}>Sending (SMTP)</h2>
                            <Setting name={'Server'} value={page.hostname} />
                            <Setting name={'Port'} value={'465'} />
                            <Setting name={'Security'} value={'SSL/TLS'} />
                            <p css={tw`text-xs text-neutral-400 py-2`}>
                                Or port 587 with STARTTLS. Same user name and password as for receiving.
                            </p>
                        </div>
                    </div>

                    <Can action={'allocation.delete'}>
                        <div css={tw`mt-6 flex justify-end`}>
                            <Button.Danger
                                variant={Button.Variants.Secondary}
                                size={Button.Sizes.Small}
                                onClick={() => setDisabling(true)}
                            >
                                <span>Turn off email</span>
                            </Button.Danger>
                        </div>
                    </Can>
                </>
            )}

            <Dialog open={!!changing} onClose={() => setChanging(null)} title={'Change the password'}>
                <p css={tw`text-sm text-neutral-300 mb-3`}>{changing?.address}</p>
                <div css={tw`flex items-center gap-2`}>
                    <input
                        css={[field, tw`font-mono`]}
                        value={newPassword}
                        maxLength={128}
                        autoComplete={'new-password'}
                        onChange={(e) => setNewPassword(e.currentTarget.value)}
                    />
                    <Copy value={newPassword} />
                </div>
                <p css={tw`text-xs text-neutral-400 mt-1.5`}>
                    Apps signed in with the old password will ask for the new one.
                </p>
                <Dialog.Footer>
                    <Button.Text type={'button'} onClick={() => setChanging(null)}>
                        Cancel
                    </Button.Text>
                    <Button
                        type={'button'}
                        disabled={busy || newPassword.length < 8}
                        onClick={() =>
                            changing &&
                            run(setMailPassword(uuid, changing.id, newPassword), () => {
                                setChanging(null);
                                addFlash({
                                    key: 'server:mail',
                                    type: 'success',
                                    title: 'Success',
                                    message: 'The password was changed.',
                                });
                            })
                        }
                    >
                        <span>Save</span>
                    </Button>
                </Dialog.Footer>
            </Dialog>

            <Dialog.Confirm
                open={!!removing}
                onClose={() => setRemoving(null)}
                title={'Delete this mailbox?'}
                confirm={'Delete'}
                onConfirmed={() => removing && run(deleteMailBox(uuid, removing.id), () => setRemoving(null))}
            >
                <span>
                    {removing?.address}: its messages are deleted and mail sent to it is refused. This cannot be undone.
                </span>
            </Dialog.Confirm>

            <Dialog.Confirm
                open={disabling}
                onClose={() => setDisabling(false)}
                title={'Turn off email?'}
                confirm={'Turn off'}
                onConfirmed={() => run(disableMail(uuid), () => setDisabling(false))}
            >
                <span>Every mailbox of the site is deleted, with its messages. This cannot be undone.</span>
            </Dialog.Confirm>
        </ServerContentBlock>
    );
};
