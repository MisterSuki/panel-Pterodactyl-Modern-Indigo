import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import useSWR from 'swr';
import tw from 'twin.macro';
import classNames from 'classnames';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCheckCircle, faClock, faCloud, faGlobe, faPlus, faServer, faTrash } from '@fortawesome/free-solid-svg-icons';
import PageContentBlock from '@/components/elements/PageContentBlock';
import Spinner from '@/components/elements/Spinner';
import Select from '@/components/elements/Select';
import Input from '@/components/elements/Input';
import { httpErrorToHuman } from '@/api/http';
import {
    addDomain,
    createSite,
    getHosting,
    HostingAccount,
    HostingData,
    HostingSite,
    removeDomain,
    setPhpVersion,
    verifyDomain,
} from '@/api/hosting';

const buttonStyle =
    'rounded-lg bg-primary-500 hover:bg-primary-400 px-4 py-2 text-sm font-semibold text-white transition-colors duration-150 disabled:opacity-50 disabled:cursor-not-allowed';
const quietButtonStyle =
    'rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 px-3 py-1.5 text-xs font-semibold text-neutral-200 transition-colors duration-150 disabled:opacity-50';

const Card = ({ children }: { children: React.ReactNode }) => (
    <div css={tw`mb-5 rounded-2xl border border-white/5 bg-neutral-800 p-5 shadow-card`}>{children}</div>
);

// What the client has to do at their registrar so that a domain leads to the web server.
const DnsHelp = ({ ips }: { ips: string[] }) => {
    if (ips.length === 0) {
        return null;
    }

    return (
        <div css={tw`mt-3 rounded-lg border border-white/10 bg-black/20 px-4 py-3 text-xs text-neutral-300`}>
            <p css={tw`mb-1 font-semibold text-neutral-100`}>To serve a domain</p>
            <p>
                <span>At your domain registrar, add these records for the domain (and for www if you ticked it):</span>
            </p>
            <ul css={tw`mt-1.5 font-mono`}>
                {ips.map((ip) => (
                    <li key={ip}>
                        {ip.includes(':') ? 'AAAA' : 'A'} &nbsp;{ip}
                    </li>
                ))}
            </ul>
            <p css={tw`mt-1.5 text-neutral-400`}>
                It can take a few minutes for the change to spread. The domain is served as soon as it leads here, and
                its HTTPS certificate is made by itself.
            </p>
        </div>
    );
};

const SiteCard = ({
    site,
    account,
    ips,
    onChange,
}: {
    site: HostingSite;
    account: HostingAccount;
    ips: string[];
    onChange: () => void;
}) => {
    const [domain, setDomain] = useState('');
    const [www, setWww] = useState(true);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const full = site.domains.length >= account.maxDomains;

    const run = (task: Promise<unknown>, message = '') => {
        setBusy(true);
        setError('');
        setNotice('');
        task.then(() => {
            setNotice(message);
            onChange();
        })
            .catch((e) => setError(httpErrorToHuman(e)))
            .then(() => setBusy(false));
    };

    const add = (e: React.FormEvent) => {
        e.preventDefault();
        run(addDomain(site.id, domain, www).then(() => setDomain('')));
    };

    const check = (id: number) => {
        setBusy(true);
        setError('');
        setNotice('');
        verifyDomain(site.id, id)
            .then((ok) => {
                setNotice(ok ? 'This domain leads here: it is served.' : 'This domain does not lead here yet.');
                onChange();
            })
            .catch((e) => setError(httpErrorToHuman(e)))
            .then(() => setBusy(false));
    };

    return (
        <Card>
            <div css={tw`flex flex-wrap items-center gap-3`}>
                <div css={tw`min-w-0 flex-1`}>
                    <h3 css={tw`text-lg font-semibold text-neutral-50 flex items-center gap-2`}>
                        <FontAwesomeIcon icon={faGlobe} css={tw`text-primary-300`} />
                        {site.name}
                    </h3>
                    <p css={tw`text-xs text-neutral-400 mt-0.5`}>
                        {site.status === 'active' ? 'Running' : site.status}
                        {site.phpVersion && ` · ${/^\d/.test(site.phpVersion) ? 'PHP ' : ''}${site.phpVersion}`}
                    </p>
                </div>
                {site.server && (
                    <Link
                        to={`/server/${site.server.identifier}`}
                        css={tw`inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-neutral-200 no-underline hover:bg-white/10`}
                    >
                        <FontAwesomeIcon icon={faServer} />
                        <span>Files and settings</span>
                    </Link>
                )}
            </div>

            {site.phpVersions.length > 0 && (
                <div css={tw`mt-4 flex flex-wrap items-center gap-3`}>
                    <label htmlFor={`php-${site.id}`} css={tw`text-sm text-neutral-300`}>
                        Version of PHP
                    </label>
                    <div css={tw`w-32`}>
                        <Select
                            id={`php-${site.id}`}
                            value={site.phpVersion || ''}
                            disabled={busy}
                            onChange={(e: React.ChangeEvent<HTMLSelectElement>) =>
                                run(
                                    setPhpVersion(site.id, e.currentTarget.value),
                                    'The version is changed. Restart the site for it to apply.'
                                )
                            }
                        >
                            {site.phpVersions.map((version) => (
                                <option key={version} value={version}>
                                    {version}
                                </option>
                            ))}
                        </Select>
                    </div>
                </div>
            )}

            <h4 css={tw`mt-5 mb-2 text-xs uppercase tracking-wider text-neutral-400`}>Domains</h4>
            {site.domains.length === 0 ? (
                <p css={tw`text-sm text-neutral-400`}>No domain yet.</p>
            ) : (
                site.domains.map((d) => (
                    <div
                        key={d.id}
                        css={tw`mb-1.5 flex flex-wrap items-center gap-3 rounded-lg border border-white/5 bg-black/20 px-3 py-2`}
                    >
                        <span css={tw`min-w-0 flex-1 text-sm text-neutral-100 break-all`}>
                            {d.domain}
                            {d.includeWww && <span css={tw`ml-1 text-xs text-neutral-500`}>+ www</span>}
                        </span>
                        {d.status === 'verified' ? (
                            <span css={tw`inline-flex items-center gap-1.5 text-xs text-green-300`}>
                                <FontAwesomeIcon icon={faCheckCircle} />
                                <span>Served</span>
                            </span>
                        ) : (
                            <>
                                <span css={tw`inline-flex items-center gap-1.5 text-xs text-yellow-300`}>
                                    <FontAwesomeIcon icon={faClock} />
                                    <span>Waiting for its DNS</span>
                                </span>
                                <button
                                    type={'button'}
                                    disabled={busy}
                                    onClick={() => check(d.id)}
                                    className={quietButtonStyle}
                                >
                                    Check now
                                </button>
                            </>
                        )}
                        <button
                            type={'button'}
                            disabled={busy}
                            aria-label={'Remove'}
                            title={'Remove'}
                            onClick={() => run(removeDomain(site.id, d.id))}
                            className={classNames(quietButtonStyle, 'text-red-300')}
                        >
                            <FontAwesomeIcon icon={faTrash} />
                        </button>
                    </div>
                ))
            )}

            {full ? (
                <p css={tw`mt-3 text-xs text-neutral-400`}>Your plan allows {account.maxDomains} domain(s) per site.</p>
            ) : (
                <form onSubmit={add} css={tw`mt-3 flex flex-wrap items-center gap-2`}>
                    <div css={tw`flex-1 min-w-[12rem]`}>
                        <Input
                            value={domain}
                            placeholder={'example.com'}
                            aria-label={'Domain'}
                            onChange={(e: React.ChangeEvent<HTMLInputElement>) => setDomain(e.currentTarget.value)}
                        />
                    </div>
                    <label css={tw`flex items-center gap-2 text-xs text-neutral-300`}>
                        <input
                            type={'checkbox'}
                            checked={www}
                            onChange={(e: React.ChangeEvent<HTMLInputElement>) => setWww(e.currentTarget.checked)}
                        />
                        <span>with www</span>
                    </label>
                    <button type={'submit'} disabled={busy || domain.trim() === ''} className={buttonStyle}>
                        <FontAwesomeIcon icon={faPlus} css={tw`mr-2`} />
                        Add
                    </button>
                </form>
            )}
            {site.domains.some((d) => d.status === 'pending') && <DnsHelp ips={ips} />}
            {notice && <p css={tw`mt-3 text-sm text-green-300`}>{notice}</p>}
            {error && <p css={tw`mt-3 text-sm text-red-300`}>{error}</p>}
        </Card>
    );
};

const NewSite = ({
    account,
    baseDomain,
    onDone,
}: {
    account: HostingAccount;
    baseDomain: string | null;
    onDone: () => void;
}) => {
    const [name, setName] = useState('');
    const [domain, setDomain] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setBusy(true);
        setError('');
        createSite(account.id, name, domain)
            .then(() => {
                setName('');
                setDomain('');
                onDone();
            })
            .catch((err) => setError(httpErrorToHuman(err)))
            .then(() => setBusy(false));
    };

    return (
        <Card>
            <h3 css={tw`text-lg font-semibold text-neutral-50 mb-3`}>New site</h3>
            <form onSubmit={submit} css={tw`flex flex-wrap items-center gap-2`}>
                <div css={tw`flex-1 min-w-[10rem]`}>
                    <Input
                        value={name}
                        maxLength={80}
                        placeholder={'Name of the site'}
                        aria-label={'Name of the site'}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setName(e.currentTarget.value)}
                    />
                </div>
                <div css={tw`flex-1 min-w-[10rem]`}>
                    <Input
                        value={domain}
                        placeholder={'example.com (optional)'}
                        aria-label={'Domain'}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setDomain(e.currentTarget.value)}
                    />
                </div>
                <button type={'submit'} disabled={busy || name.trim() === ''} className={buttonStyle}>
                    Make the site
                </button>
            </form>
            {baseDomain && (
                <p css={tw`mt-2 text-xs text-neutral-400`}>
                    <span>Without a domain, the site gets a free name under</span> {baseDomain}
                </p>
            )}
            {error && <p css={tw`mt-3 text-sm text-red-300`}>{error}</p>}
        </Card>
    );
};

export default () => {
    const { data, mutate } = useSWR<HostingData>('hosting', getHosting, {
        revalidateOnFocus: true,
        refreshInterval: 30000,
    });

    if (!data) {
        return (
            <PageContentBlock title={'Web hosting'}>
                <Spinner size={'large'} centered />
            </PageContentBlock>
        );
    }

    if (!data.enabled || data.accounts.length === 0) {
        return (
            <PageContentBlock title={'Web hosting'}>
                <p css={tw`text-center text-neutral-400 py-10`}>You have no web hosting.</p>
            </PageContentBlock>
        );
    }

    return (
        <PageContentBlock title={'Web hosting'}>
            <div css={tw`mb-5`}>
                <h1 css={tw`text-2xl font-semibold text-neutral-50 flex items-center gap-3`}>
                    <FontAwesomeIcon icon={faCloud} css={tw`text-primary-400`} />
                    Web hosting
                </h1>
                <p css={tw`text-sm text-neutral-400 mt-1`}>Your sites, their domains and their version of PHP.</p>
            </div>

            {data.accounts.map((account) => (
                <div key={account.id}>
                    <p css={tw`mb-3 text-xs uppercase tracking-wider text-neutral-400`}>
                        <span>Plan</span> {account.plan} &middot; {account.sites.length} / {account.maxSites}{' '}
                        <span>sites</span>
                        {account.status === 'suspended' && (
                            <span
                                css={tw`ml-2 rounded-full bg-red-500/10 border border-red-500/30 px-2 py-0.5 text-red-300`}
                            >
                                Suspended
                            </span>
                        )}
                    </p>
                    {account.sites.map((site) => (
                        <SiteCard
                            key={site.id}
                            site={site}
                            account={account}
                            ips={data.ips}
                            onChange={() => mutate()}
                        />
                    ))}
                    {account.canCreateSite && (
                        <NewSite account={account} baseDomain={data.baseDomain} onDone={() => mutate()} />
                    )}
                </div>
            ))}
        </PageContentBlock>
    );
};
