import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faCheckCircle,
    faCircleNotch,
    faCopy,
    faDownload,
    faExclamationTriangle,
    faExternalLinkAlt,
    faGlobe,
    faHourglassHalf,
    faLock,
    faPlus,
    faSyncAlt,
    faTrashAlt,
} from '@fortawesome/free-solid-svg-icons';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import Can from '@/components/elements/Can';
import { ServerContext } from '@/state/server';
import { useFlashKey } from '@/plugins/useFlash';
import getServerStartup from '@/api/swr/getServerStartup';
import setSelectedDockerImage from '@/api/server/setSelectedDockerImage';
import {
    addWebDomain,
    addWebSubdomain,
    checkWebDomain,
    deleteWebDomain,
    getWeb,
    getWebInstall,
    installWebApp,
    uninstallWebApp,
    WebApp,
    WebDomain,
    WebInstallStatus,
    WebPage,
} from '@/api/server/web';

const field = tw`w-full rounded-xl bg-black/30 border border-white/10 px-3 py-2.5 text-sm text-neutral-100 focus:outline-none focus:border-primary-500`;
const label = tw`block text-xs uppercase tracking-wide font-semibold text-neutral-300 mb-1.5`;
const card = tw`rounded-2xl bg-neutral-800 border border-white/10 shadow-card overflow-hidden mt-6`;
const pill = tw`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold`;

const LOCALES: [string, string][] = [
    ['fr_FR', 'Français'],
    ['en_US', 'English'],
    ['de_DE', 'Deutsch'],
    ['es_ES', 'Español'],
    ['it_IT', 'Italiano'],
    ['pt_PT', 'Português'],
    ['nl_NL', 'Nederlands'],
];

const Copy = ({ value }: { value: string }) => {
    const [done, setDone] = useState(false);

    return (
        <button
            type={'button'}
            css={tw`ml-2 text-neutral-400 hover:text-white focus:outline-none`}
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

const Ssl = ({ domain }: { domain: WebDomain }) =>
    domain.ssl === 'active' ? (
        <span css={[pill, tw`bg-green-500/20 text-green-300`]}>
            <FontAwesomeIcon icon={faLock} /> <span>HTTPS active</span>
        </span>
    ) : domain.ssl === 'failed' ? (
        <span css={[pill, tw`bg-red-500/20 text-red-300`]}>
            <FontAwesomeIcon icon={faExclamationTriangle} /> <span>HTTPS failed</span>
        </span>
    ) : (
        <span css={[pill, tw`bg-yellow-500/20 text-yellow-300`]}>
            <FontAwesomeIcon icon={faCircleNotch} spin /> <span>HTTPS being set up</span>
        </span>
    );

const DomainRow = ({
    domain,
    busy,
    onCheck,
    onDelete,
}: {
    domain: WebDomain;
    busy: boolean;
    onCheck: () => void;
    onDelete: () => void;
}) => (
    <div css={tw`px-4 py-4 border-t border-white/5 first:border-t-0 space-y-3`}>
        <div css={tw`flex flex-wrap items-center gap-3`}>
            {domain.url ? (
                <a
                    href={domain.url}
                    target={'_blank'}
                    rel={'noopener noreferrer'}
                    css={tw`font-mono text-sm text-neutral-50 hover:text-primary-300 truncate`}
                >
                    {domain.hostname} <FontAwesomeIcon icon={faExternalLinkAlt} css={tw`text-xs ml-1 opacity-60`} />
                </a>
            ) : (
                <span css={tw`font-mono text-sm text-neutral-50 truncate`}>{domain.hostname}</span>
            )}
            {domain.ready ? (
                <Ssl domain={domain} />
            ) : (
                <span css={[pill, tw`bg-yellow-500/20 text-yellow-300`]}>
                    <FontAwesomeIcon icon={faHourglassHalf} /> <span>Waiting for your DNS</span>
                </span>
            )}
            {domain.www && <span css={[pill, tw`bg-white/10 text-neutral-300`]}>www</span>}
            <div css={tw`ml-auto flex items-center gap-2`}>
                {domain.own && (
                    <Button.Text type={'button'} size={Button.Sizes.Small} onClick={onCheck} disabled={busy}>
                        <FontAwesomeIcon icon={faSyncAlt} css={tw`mr-1.5`} /> <span>Check</span>
                    </Button.Text>
                )}
                <Can action={'allocation.delete'}>
                    <Button.Danger
                        variant={Button.Variants.Secondary}
                        size={Button.Sizes.Small}
                        shape={Button.Shapes.IconSquare}
                        type={'button'}
                        onClick={onDelete}
                        disabled={busy}
                    >
                        <FontAwesomeIcon icon={faTrashAlt} />
                    </Button.Danger>
                </Can>
            </div>
        </div>
        {domain.ssl === 'failed' && domain.sslError && (
            <p css={tw`text-xs text-red-300 break-words`}>{domain.sslError}</p>
        )}
        {domain.own && (!domain.ready || !domain.www) && domain.records.length > 0 && (
            <div>
                <p css={tw`text-xs text-neutral-300 mb-2`}>
                    {domain.ready
                        ? 'To also use www, create the second record, then press Check.'
                        : 'Create these records in the DNS of your domain, then press Check. It can take a few minutes to work.'}
                </p>
                <div css={tw`overflow-x-auto rounded-xl border border-white/10 bg-black/30`}>
                    <table css={tw`min-w-full text-sm`}>
                        <thead>
                            <tr css={tw`text-xs uppercase tracking-wide text-neutral-400`}>
                                <th css={tw`text-left px-3 py-2`}>Type</th>
                                <th css={tw`text-left px-3 py-2`}>Name</th>
                                <th css={tw`text-left px-3 py-2`}>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            {domain.records.map((r) => (
                                <tr key={r.name} css={tw`border-t border-white/5`}>
                                    <td css={tw`px-3 py-2 font-mono text-primary-300`}>{r.type}</td>
                                    <td css={tw`px-3 py-2 font-mono text-neutral-100 whitespace-nowrap`}>
                                        {r.name}
                                        <Copy value={r.name} />
                                    </td>
                                    <td css={tw`px-3 py-2 font-mono text-neutral-100 whitespace-nowrap`}>
                                        {r.content}
                                        <Copy value={r.content} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        )}
    </div>
);

const InstallBanner = ({
    status,
    apps,
    onUninstall,
}: {
    status: WebInstallStatus;
    apps: WebApp[];
    onUninstall: () => void;
}) => {
    const name = apps.find((a) => a.id === status.app)?.name || status.app;

    if (status.app === 'uninstall') {
        return (
            <div css={tw`mx-4 mt-4 rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm`}>
                {status.state === 'running' ? (
                    <p css={tw`text-neutral-100`}>
                        <FontAwesomeIcon icon={faCircleNotch} spin css={tw`mr-2 text-primary-300`} />
                        <span>{status.step}</span>
                    </p>
                ) : status.state === 'failed' ? (
                    <p css={tw`text-red-300 break-words`}>
                        <FontAwesomeIcon icon={faExclamationTriangle} css={tw`mr-2`} />
                        <span>{status.step}</span>
                    </p>
                ) : (
                    <p css={tw`text-green-300`}>
                        <FontAwesomeIcon icon={faCheckCircle} css={tw`mr-2`} />
                        <span>The CMS was removed: the site is empty again.</span>
                    </p>
                )}
            </div>
        );
    }

    return (
        <div css={tw`mx-4 mt-4 rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm`}>
            {status.state === 'running' ? (
                <p css={tw`text-neutral-100`}>
                    <FontAwesomeIcon icon={faCircleNotch} spin css={tw`mr-2 text-primary-300`} />
                    <span>{name}</span>: <span>{status.step}</span>
                </p>
            ) : status.state === 'failed' ? (
                <p css={tw`text-red-300 break-words`}>
                    <FontAwesomeIcon icon={faExclamationTriangle} css={tw`mr-2`} />
                    <span>{name}</span>: <span>{status.step}</span>
                </p>
            ) : (
                <div css={tw`flex flex-wrap items-center gap-x-4 gap-y-2`}>
                    <p css={tw`text-green-300`}>
                        <FontAwesomeIcon icon={faCheckCircle} css={tw`mr-2`} />
                        <span>{name}</span> <span>is installed.</span>
                    </p>
                    {status.url && (
                        <a
                            href={status.url}
                            target={'_blank'}
                            rel={'noopener noreferrer'}
                            css={tw`text-primary-300 hover:underline`}
                        >
                            <span>Open the site</span>
                        </a>
                    )}
                    {status.adminUrl && (
                        <a
                            href={status.adminUrl}
                            target={'_blank'}
                            rel={'noopener noreferrer'}
                            css={tw`text-primary-300 hover:underline`}
                        >
                            <span>Open the administration</span>
                        </a>
                    )}
                    <Button.Danger
                        variant={Button.Variants.Secondary}
                        size={Button.Sizes.Small}
                        type={'button'}
                        css={tw`ml-auto`}
                        onClick={onUninstall}
                    >
                        <FontAwesomeIcon icon={faTrashAlt} css={tw`mr-1.5`} /> <span>Uninstall</span>
                    </Button.Danger>
                </div>
            )}
        </div>
    );
};

// A page of the panel in HTTPS cannot show a site in plain HTTP: that one opens in a new tab. The number in the address
// of the frame makes the browser ask the site again, instead of showing the copy it keeps.
const Preview = ({ url, temporary }: { url: string | null; temporary: string | null }) => {
    const status = ServerContext.useStoreState((state) => state.status.value);
    const [version, setVersion] = useState(() => Date.now());
    const address = url || temporary;

    if (!address) {
        return null;
    }
    const framed = address.startsWith('https://') && status === 'running';

    return (
        <div css={card}>
            <div css={tw`flex flex-wrap items-center gap-3 px-4 py-3`}>
                <div css={tw`flex-1 min-w-0`}>
                    <h2 css={tw`text-base text-neutral-50 font-semibold`}>Preview of the site</h2>
                    <p css={tw`text-xs text-neutral-400 break-all`}>
                        {status !== 'running' ? (
                            <span>Start the site to see it.</span>
                        ) : temporary ? (
                            <span>Temporary address, until a domain leads to the site. It shows the port.</span>
                        ) : (
                            address
                        )}
                    </p>
                </div>
                {framed && (
                    <Button.Text type={'button'} size={Button.Sizes.Small} onClick={() => setVersion(Date.now())}>
                        <FontAwesomeIcon icon={faSyncAlt} css={tw`mr-1.5`} /> <span>Reload</span>
                    </Button.Text>
                )}
                {status === 'running' && (
                    <a href={address} target={'_blank'} rel={'noopener noreferrer'}>
                        <Button.Text type={'button'} size={Button.Sizes.Small}>
                            <FontAwesomeIcon icon={faExternalLinkAlt} css={tw`mr-1.5`} /> <span>Open the preview</span>
                        </Button.Text>
                    </a>
                )}
            </div>
            {framed && (
                <iframe
                    key={version}
                    src={`${address}/?preview=${version}`}
                    title={'Preview of the site'}
                    sandbox={'allow-scripts allow-same-origin allow-forms'}
                    css={tw`block w-full border-0 border-t border-white/10 bg-white`}
                    style={{ height: 480 }}
                />
            )}
        </div>
    );
};

const PhpVersion = ({ uuid, image }: { uuid: string; image: string }) => {
    const { data, mutate } = getServerStartup(uuid);
    const setServerFromState = ServerContext.useStoreActions((actions) => actions.server.setServerFromState);
    const { clearAndAddHttpError } = useFlashKey('server:web');
    const [saved, setSaved] = useState(false);

    if (!data || Object.keys(data.dockerImages).length < 2) {
        return null;
    }

    const change = (value: string) => {
        setSaved(false);
        setSelectedDockerImage(uuid, value)
            .then(() => {
                setServerFromState((s) => ({ ...s, dockerImage: value }));
                setSaved(true);
                mutate();
            })
            .catch((error) => clearAndAddHttpError(error));
    };

    return (
        <div css={card}>
            <div css={tw`flex flex-wrap items-center gap-4 px-4 py-3`}>
                <div css={tw`flex-1 min-w-0`}>
                    <h2 css={tw`text-base text-neutral-50 font-semibold`}>PHP version</h2>
                    <p css={tw`text-xs text-neutral-400`}>
                        {saved ? 'Saved: restart the site to use it.' : 'Takes effect the next time the site starts.'}
                    </p>
                </div>
                <select css={[field, tw`w-auto`]} value={image} onChange={(e) => change(e.currentTarget.value)}>
                    {Object.entries(data.dockerImages).map(([name, value]) => (
                        <option key={value} value={value}>
                            {name}
                        </option>
                    ))}
                </select>
            </div>
        </div>
    );
};

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const image = ServerContext.useStoreState((state) => state.server.data!.dockerImage);
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('server:web');

    const [page, setPage] = useState<WebPage | null>(null);
    const [busy, setBusy] = useState(false);
    const [adding, setAdding] = useState(false);
    const [hostname, setHostname] = useState('');
    const [subdomain, setSubdomain] = useState({ name: '', zone: 0 });
    const [removing, setRemoving] = useState<WebDomain | null>(null);
    const [uninstalling, setUninstalling] = useState(false);
    const [app, setApp] = useState<WebApp | null>(null);
    const [form, setForm] = useState({ title: '', adminUser: '', adminPassword: '', adminEmail: '', locale: 'fr_FR' });

    const run = (action: Promise<WebPage>, done?: () => void) => {
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
        getWeb(uuid)
            .then(setPage)
            .catch((error) => clearAndAddHttpError(error));
    }, [uuid]);

    // The certificate is made by the machine a little after the domain is added: the page follows it by itself.
    const waiting = !!page?.domains.some((d) => d.ready && d.ssl === 'pending');
    useEffect(() => {
        if (!waiting) return;
        const timer = window.setInterval(() => {
            !document.hidden &&
                getWeb(uuid)
                    .then(setPage)
                    .catch(() => undefined);
        }, 8000);

        return () => window.clearInterval(timer);
    }, [waiting, uuid]);

    const installing = page?.install?.state === 'running';
    useEffect(() => {
        if (!installing) return;
        const timer = window.setInterval(() => {
            getWebInstall(uuid)
                .then((install) => setPage((current) => (current ? { ...current, install } : current)))
                .catch(() => undefined);
        }, 3000);

        return () => window.clearInterval(timer);
    }, [installing, uuid]);

    const zone = page?.zones.find((z) => z.id === subdomain.zone) || page?.zones[0];
    const canInstall = !!page?.domains.some((d) => d.ready) && !installing;

    const openInstall = (target: WebApp) => {
        setForm({ title: '', adminUser: '', adminPassword: '', adminEmail: '', locale: form.locale });
        setApp(target);
    };

    const accountReady =
        !app?.account ||
        (form.title !== '' && form.adminUser.length >= 3 && form.adminPassword.length >= 12 && form.adminEmail !== '');

    return (
        <ServerContentBlock title={'Website'}>
            <FlashMessageRender byKey={'server:web'} css={tw`mb-4`} />
            <div css={tw`flex flex-wrap items-center gap-3`}>
                <span
                    css={tw`flex items-center justify-center w-11 h-11 rounded-2xl bg-primary-500 bg-opacity-20 text-primary-300 text-lg flex-shrink-0`}
                >
                    <FontAwesomeIcon icon={faGlobe} />
                </span>
                <div css={tw`flex-1 min-w-0`}>
                    <h1 css={tw`text-xl text-neutral-50 font-semibold`}>Website</h1>
                    <p css={tw`text-sm text-neutral-400`}>Its address, HTTPS and the installation of a CMS.</p>
                </div>
                {page?.url && (
                    <a href={page.url} target={'_blank'} rel={'noopener noreferrer'}>
                        <Button type={'button'}>
                            <FontAwesomeIcon icon={faExternalLinkAlt} css={tw`mr-2`} /> <span>Open the site</span>
                        </Button>
                    </a>
                )}
            </div>

            {!page ? (
                <div css={tw`flex justify-center py-16 text-neutral-400`}>
                    <FontAwesomeIcon icon={faCircleNotch} spin size={'2x'} />
                </div>
            ) : (
                <>
                    {!page.agent && (
                        <div
                            css={tw`mt-4 rounded-xl border border-yellow-500/30 bg-yellow-500/10 px-4 py-3 text-sm text-yellow-200`}
                        >
                            <FontAwesomeIcon icon={faExclamationTriangle} css={tw`mr-2`} />
                            <span>
                                This machine does not manage domains yet: they will work once the administrator has set
                                up web hosting on it.
                            </span>
                        </div>
                    )}

                    <div css={card}>
                        <div css={tw`flex flex-wrap items-center gap-3 px-4 py-3 border-b border-white/10`}>
                            <div css={tw`flex-1 min-w-0`}>
                                <h2 css={tw`text-base text-neutral-50 font-semibold`}>Domains</h2>
                                <p css={tw`text-xs text-neutral-400`}>
                                    Visitors use the domain, without a port. HTTPS is set up by itself.
                                </p>
                                {page.domains.length >= page.max && (
                                    <p css={tw`mt-1 text-xs text-neutral-500`}>
                                        {page.max === 1 ? (
                                            <span>One domain per site: remove it to use another one.</span>
                                        ) : (
                                            <span>This site has reached its maximum of domains.</span>
                                        )}
                                    </p>
                                )}
                            </div>
                            <Can action={'allocation.create'}>
                                <Button.Text
                                    type={'button'}
                                    size={Button.Sizes.Small}
                                    onClick={() => setAdding(true)}
                                    disabled={busy || page.domains.length >= page.max}
                                >
                                    <FontAwesomeIcon icon={faPlus} css={tw`mr-1.5`} /> <span>Add my domain</span>
                                </Button.Text>
                            </Can>
                        </div>

                        {page.domains.length === 0 ? (
                            <p css={tw`px-4 py-6 text-center text-sm text-neutral-400`}>No domain yet.</p>
                        ) : (
                            page.domains.map((d) => (
                                <DomainRow
                                    key={d.id}
                                    domain={d}
                                    busy={busy}
                                    onCheck={() => run(checkWebDomain(uuid, d.id))}
                                    onDelete={() => setRemoving(d)}
                                />
                            ))
                        )}

                        {page.zones.length > 0 && zone && (
                            <Can action={'allocation.create'}>
                                <div
                                    css={tw`flex flex-wrap items-end gap-3 px-4 py-4 border-t border-white/10 bg-black/10`}
                                >
                                    <div css={tw`flex-1 min-w-[10rem]`}>
                                        <label css={label}>Free subdomain</label>
                                        <input
                                            css={field}
                                            value={subdomain.name}
                                            placeholder={'mysite'}
                                            onChange={(e) =>
                                                setSubdomain({
                                                    ...subdomain,
                                                    name: e.currentTarget.value.trim().toLowerCase(),
                                                })
                                            }
                                        />
                                    </div>
                                    <div css={tw`flex-1 min-w-[10rem]`}>
                                        <label css={label}>Domain</label>
                                        <select
                                            css={field}
                                            value={zone.id}
                                            onChange={(e) =>
                                                setSubdomain({ ...subdomain, zone: Number(e.currentTarget.value) })
                                            }
                                        >
                                            {page.zones.map((z) => (
                                                <option key={z.id} value={z.id}>
                                                    .{z.domain}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <Button
                                        type={'button'}
                                        disabled={busy || !subdomain.name}
                                        onClick={() =>
                                            run(addWebSubdomain(uuid, subdomain.name, zone.id), () =>
                                                setSubdomain({ ...subdomain, name: '' })
                                            )
                                        }
                                    >
                                        <span>Create</span>
                                    </Button>
                                </div>
                            </Can>
                        )}
                    </div>

                    <Preview url={page.url} temporary={page.preview} />

                    {page.php && <PhpVersion uuid={uuid} image={image} />}

                    {page.php && (
                        <div css={[card, tw`pb-4`]}>
                            <div css={tw`px-4 py-3 border-b border-white/10`}>
                                <h2 css={tw`text-base text-neutral-50 font-semibold`}>Install a CMS</h2>
                                <p css={tw`text-xs text-neutral-400`}>
                                    {page.domains.some((d) => d.ready)
                                        ? 'In one click, or send your own PHP, HTML and JS files to public_html with the Files tab or SFTP.'
                                        : 'Add a domain first: the CMS is set up for its address. Your own files go to public_html, with the Files tab or SFTP.'}
                                </p>
                            </div>
                            {page.install && (
                                <InstallBanner
                                    status={page.install}
                                    apps={page.apps}
                                    onUninstall={() => setUninstalling(true)}
                                />
                            )}
                            <div css={tw`grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 px-4 pt-4`}>
                                {page.apps.map((a) => (
                                    <div
                                        key={a.id}
                                        css={tw`flex flex-col rounded-xl border border-white/10 bg-black/20 p-4`}
                                    >
                                        <p css={tw`text-neutral-50 font-semibold`}>{a.name}</p>
                                        <p css={tw`text-xs text-neutral-400 mt-1 flex-1`}>{a.description}</p>
                                        <Button.Text
                                            type={'button'}
                                            size={Button.Sizes.Small}
                                            css={tw`mt-3`}
                                            disabled={busy || !canInstall}
                                            onClick={() => openInstall(a)}
                                        >
                                            <FontAwesomeIcon icon={faDownload} css={tw`mr-1.5`} /> <span>Install</span>
                                        </Button.Text>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {!page.php && (
                        <div css={card}>
                            <div css={tw`px-4 py-3`}>
                                <h2 css={tw`text-base text-neutral-50 font-semibold`}>Your application</h2>
                                <p css={tw`text-xs text-neutral-400`}>
                                    Send your files with the Files tab or SFTP. Your application must listen on the PORT
                                    variable. Its start command is in the Startup tab.
                                </p>
                            </div>
                        </div>
                    )}
                </>
            )}

            <Dialog open={adding} onClose={() => setAdding(false)} title={'Add my domain'}>
                <label css={label}>Domain name</label>
                <input
                    css={field}
                    value={hostname}
                    placeholder={'example.com'}
                    autoFocus
                    onChange={(e) => setHostname(e.currentTarget.value.trim().toLowerCase())}
                />
                <p css={tw`text-xs text-neutral-400 mt-1.5`}>
                    The panel then shows what to create in your DNS, and checks it.
                </p>
                <Dialog.Footer>
                    <Button.Text type={'button'} onClick={() => setAdding(false)}>
                        Cancel
                    </Button.Text>
                    <Button
                        type={'button'}
                        disabled={busy || !hostname}
                        onClick={() =>
                            run(addWebDomain(uuid, hostname), () => {
                                setAdding(false);
                                setHostname('');
                            })
                        }
                    >
                        {busy ? <FontAwesomeIcon icon={faCircleNotch} spin /> : <span>Add my domain</span>}
                    </Button>
                </Dialog.Footer>
            </Dialog>

            <Dialog open={!!app} onClose={() => setApp(null)} title={app ? `${app.name}` : ''}>
                <div css={tw`space-y-4`}>
                    <p css={tw`rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200`}>
                        Everything in the folder of the site is replaced, and so is the database of the last CMS
                        installed here.
                    </p>
                    {app?.account && (
                        <>
                            <div>
                                <label css={label}>Name of the site</label>
                                <input
                                    css={field}
                                    value={form.title}
                                    maxLength={100}
                                    onChange={(e) => setForm({ ...form, title: e.currentTarget.value })}
                                />
                            </div>
                            <div css={tw`grid grid-cols-1 sm:grid-cols-2 gap-4`}>
                                <div>
                                    <label css={label}>Administrator</label>
                                    <input
                                        css={field}
                                        value={form.adminUser}
                                        autoComplete={'off'}
                                        maxLength={40}
                                        onChange={(e) => setForm({ ...form, adminUser: e.currentTarget.value.trim() })}
                                    />
                                </div>
                                <div>
                                    <label css={label}>Password</label>
                                    <input
                                        css={field}
                                        type={'password'}
                                        value={form.adminPassword}
                                        autoComplete={'new-password'}
                                        maxLength={100}
                                        onChange={(e) => setForm({ ...form, adminPassword: e.currentTarget.value })}
                                    />
                                    <p css={tw`text-xs text-neutral-400 mt-1.5`}>12 characters at least.</p>
                                </div>
                            </div>
                            <div>
                                <label css={label}>Email</label>
                                <input
                                    css={field}
                                    type={'email'}
                                    value={form.adminEmail}
                                    onChange={(e) => setForm({ ...form, adminEmail: e.currentTarget.value.trim() })}
                                />
                            </div>
                        </>
                    )}
                    <div>
                        <label css={label}>Language</label>
                        <select
                            css={field}
                            value={form.locale}
                            onChange={(e) => setForm({ ...form, locale: e.currentTarget.value })}
                        >
                            {LOCALES.map(([value, name]) => (
                                <option key={value} value={value}>
                                    {name}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
                <Dialog.Footer>
                    <Button.Text type={'button'} onClick={() => setApp(null)}>
                        Cancel
                    </Button.Text>
                    <Button
                        type={'button'}
                        disabled={busy || !accountReady}
                        onClick={() =>
                            app &&
                            run(installWebApp(uuid, { app: app.id, ...form }), () => {
                                setApp(null);
                                setForm({ ...form, adminPassword: '' });
                            })
                        }
                    >
                        {busy ? <FontAwesomeIcon icon={faCircleNotch} spin /> : <span>Install</span>}
                    </Button>
                </Dialog.Footer>
            </Dialog>

            <Dialog.Confirm
                open={uninstalling}
                onClose={() => setUninstalling(false)}
                title={'Uninstall the CMS'}
                confirm={'Uninstall'}
                onConfirmed={() => {
                    setUninstalling(false);
                    run(uninstallWebApp(uuid));
                }}
            >
                <span>
                    Everything in the folder of the site is deleted, with the database of the CMS and its data. The site
                    shows its first page again. This cannot be undone: make a backup first if you need it.
                </span>
            </Dialog.Confirm>

            <Dialog.Confirm
                open={!!removing}
                onClose={() => setRemoving(null)}
                title={'Remove the domain'}
                confirm={'Remove'}
                onConfirmed={() => {
                    const target = removing;
                    setRemoving(null);
                    target && run(deleteWebDomain(uuid, target.id));
                }}
            >
                <span>The site stops answering on this domain:</span> <code>{removing?.hostname}</code>
            </Dialog.Confirm>
        </ServerContentBlock>
    );
};
