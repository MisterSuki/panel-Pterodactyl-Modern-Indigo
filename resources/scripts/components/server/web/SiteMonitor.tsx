import React from 'react';
import tw from 'twin.macro';
import useSWR from 'swr';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faHeartbeat, faLock } from '@fortawesome/free-solid-svg-icons';
import http from '@/api/http';
import { ServerContext } from '@/state/server';

interface Monitor {
    url: string | null;
    state: 'up' | 'down' | 'stopped' | 'no_domain' | 'unknown';
    status_code: number | null;
    response_ms: number | null;
    average_ms: number | null;
    down_since: string | null;
    last_error: string | null;
    last_checked_at: string | null;
    ssl_expires_at: string | null;
    uptime_24h: number | null;
    uptime_7d: number | null;
}

const STATES: Record<Monitor['state'], { label: string; color: string }> = {
    up: { label: 'Online', color: '#3ecf8e' },
    down: { label: 'Down', color: '#e5484d' },
    stopped: { label: 'Server stopped', color: '#707070' },
    no_domain: { label: 'No domain', color: '#707070' },
    unknown: { label: 'Not checked yet', color: '#707070' },
};

const percent = (value: number | null) => (value === null ? '—' : `${value.toFixed(value === 100 ? 0 : 2)} %`);

// The monitor of the site (checked every minute by the panel): up or down, how fast, its availability, its
// certificate. The alerts (down, back, certificate) go where the alerts of the server go (Settings > Alerts).
export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { data } = useSWR<Monitor>(
        ['web-monitor', uuid],
        () => http.get(`/api/client/servers/${uuid}/web/monitor`).then(({ data }) => data),
        { refreshInterval: 30000, revalidateOnFocus: false }
    );
    if (!data) return null;

    const state = STATES[data.state] || STATES.unknown;
    const sslDays = data.ssl_expires_at
        ? Math.floor((new Date(data.ssl_expires_at).getTime() - Date.now()) / 86400000)
        : null;

    return (
        <div css={tw`rounded-2xl bg-neutral-800 border border-white/10 overflow-hidden mt-6`}>
            <div css={tw`px-4 py-3 border-b border-white/10 flex items-center gap-3`}>
                <FontAwesomeIcon icon={faHeartbeat} css={tw`text-neutral-400`} />
                <div css={tw`flex-1 min-w-0`}>
                    <h2 css={tw`text-base text-neutral-50 font-semibold`}>Monitoring</h2>
                    <p css={tw`text-xs text-neutral-400`}>
                        The panel checks every minute that the site answers, and tells you when it is down.
                    </p>
                </div>
                <span
                    css={tw`flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold`}
                    style={{ background: `${state.color}22`, color: state.color }}
                >
                    <span css={tw`w-1.5 h-1.5 rounded-full`} style={{ background: state.color }} />
                    <span>{state.label}</span>
                </span>
            </div>
            <div css={tw`grid grid-cols-2 md:grid-cols-4 gap-px bg-white/5`}>
                {[
                    {
                        label: 'Answer time',
                        value: data.response_ms !== null && data.state === 'up' ? `${data.response_ms} ms` : '—',
                        detail: data.average_ms !== null ? `${data.average_ms} ms` : null,
                        detailLabel: 'Average (24 h)',
                    },
                    { label: 'Available (24 h)', value: percent(data.uptime_24h) },
                    { label: 'Available (7 days)', value: percent(data.uptime_7d) },
                    {
                        label: 'HTTPS certificate',
                        value: sslDays === null ? '—' : sslDays < 0 ? 'Expired' : `${sslDays} d`,
                        detail: data.ssl_expires_at ? new Date(data.ssl_expires_at).toLocaleDateString() : null,
                        detailLabel: 'Until',
                        warn: sslDays !== null && sslDays <= 10,
                    },
                ].map((item) => (
                    <div key={item.label} css={tw`bg-neutral-800 px-4 py-3`}>
                        <p css={tw`text-xs text-neutral-400 flex items-center gap-1.5`}>
                            {item.label === 'HTTPS certificate' && <FontAwesomeIcon icon={faLock} css={tw`text-2xs`} />}
                            <span>{item.label}</span>
                        </p>
                        <p
                            css={[
                                tw`mt-1 text-lg font-semibold tabular-nums`,
                                item.warn ? tw`text-yellow-300` : tw`text-neutral-50`,
                            ]}
                        >
                            {item.value}
                        </p>
                        {item.detail && (
                            <p css={tw`text-xs text-neutral-500`}>
                                <span>{item.detailLabel}</span> {item.detail}
                            </p>
                        )}
                    </div>
                ))}
            </div>
            {data.state === 'down' && (
                <p css={tw`px-4 py-2 text-xs text-red-300 border-t border-white/10`}>
                    <span>Down since</span> {data.down_since ? new Date(data.down_since).toLocaleString() : '—'}
                    {data.last_error && <span data-no-translate> · {data.last_error}</span>}
                </p>
            )}
            {data.state === 'no_domain' && (
                <p css={tw`px-4 py-2 text-xs text-neutral-400 border-t border-white/10`}>
                    Add a domain to the site (above) to monitor it.
                </p>
            )}
        </div>
    );
};
