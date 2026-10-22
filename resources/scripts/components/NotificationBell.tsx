import React, { useCallback, useEffect, useRef, useState } from 'react';
import tw from 'twin.macro';
import { useHistory } from 'react-router-dom';
import { formatDistanceToNow } from 'date-fns';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faBell,
    faBullhorn,
    faCheckCircle,
    faCheckDouble,
    faCloudDownloadAlt,
    faExclamationTriangle,
    faInfoCircle,
    faTimesCircle,
    faWrench,
} from '@fortawesome/free-solid-svg-icons';
import { dateLocale } from '@/components/elements/activity/kinds';
import { getNotifications, NotificationList, PanelNotification, readNotifications } from '@/api/account/notifications';

const ICONS = {
    info: [faInfoCircle, '#64d2ff'],
    success: [faCheckCircle, '#30d158'],
    warning: [faExclamationTriangle, '#ff9f0a'],
    danger: [faTimesCircle, '#ff453a'],
    maintenance: [faWrench, '#ff9f0a'],
    update: [faCloudDownloadAlt, '#0a84ff'],
} as const;

// How often the bell asks for new messages while the page is open.
const POLL_MS = 60000;

/**
 * The bell of the top bar: the messages of the person (alerts of their servers, answers to their tickets, the shop)
 * and the announcements of the administration (maintenance, updates). A red dot counts the unread ones.
 */
export default () => {
    const history = useHistory();
    const [open, setOpen] = useState(false);
    const [list, setList] = useState<NotificationList>({ unread: 0, data: [] });
    const box = useRef<HTMLDivElement>(null);

    const refresh = useCallback(() => {
        if (document.hidden) return;
        getNotifications()
            .then(setList)
            .catch(() => undefined);
    }, []);

    useEffect(() => {
        refresh();
        const timer = window.setInterval(refresh, POLL_MS);
        document.addEventListener('visibilitychange', refresh);

        return () => {
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', refresh);
        };
    }, [refresh]);

    useEffect(() => {
        if (!open) return;
        const close = (e: MouseEvent) => {
            if (box.current && !box.current.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    const markAll = () =>
        readNotifications()
            .then(setList)
            .catch(() => undefined);

    const openOne = (n: PanelNotification) => {
        if (!n.read) {
            setList((current) => ({
                unread: Math.max(0, current.unread - 1),
                data: current.data.map((x) => (x.id === n.id ? { ...x, read: true } : x)),
            }));
            readNotifications([n.id]).catch(() => undefined);
        }
        if (!n.url) return;
        setOpen(false);
        if (n.url.startsWith('/')) {
            n.url.startsWith('/admin') ? (window.location.href = n.url) : history.push(n.url);
        } else {
            window.open(n.url, '_blank', 'noopener');
        }
    };

    return (
        <div ref={box} css={tw`relative h-full flex items-center`} className={'navigation-link'}>
            <button
                type={'button'}
                onClick={() => setOpen(!open)}
                css={tw`relative flex items-center justify-center w-full h-full text-current bg-transparent focus:outline-none`}
                aria-label={'Notifications'}
            >
                <FontAwesomeIcon icon={faBell} />
                {list.unread > 0 && (
                    <span
                        css={tw`absolute flex items-center justify-center rounded-full bg-red-500 text-white font-bold`}
                        style={{ top: 2, right: 0, minWidth: 16, height: 16, fontSize: 10, padding: '0 4px' }}
                    >
                        {list.unread > 9 ? '9+' : list.unread}
                    </span>
                )}
            </button>
            {open && (
                <div
                    className={'lg-glass-thick'}
                    css={tw`absolute right-0 top-full mt-3 rounded-2xl overflow-hidden z-50 text-left`}
                    // Nearly solid: a blur inside the blurred top bar does not work, the page would show through.
                    style={{
                        width: 'min(360px, calc(100vw - 24px))',
                        background: 'linear-gradient(180deg, rgba(48, 48, 58, 0.98) 0%, rgba(28, 28, 36, 0.98) 100%)',
                    }}
                >
                    <div css={tw`flex items-center justify-between px-4 py-3 border-b border-white/10`}>
                        <span css={tw`text-sm font-semibold text-neutral-50`}>Notifications</span>
                        {list.unread > 0 && (
                            <button
                                type={'button'}
                                onClick={markAll}
                                css={tw`text-xs text-primary-300 hover:text-primary-200 focus:outline-none`}
                            >
                                <FontAwesomeIcon icon={faCheckDouble} css={tw`mr-1`} /> <span>Mark all as read</span>
                            </button>
                        )}
                    </div>
                    <div css={tw`overflow-y-auto`} style={{ maxHeight: '60vh' }}>
                        {list.data.length === 0 ? (
                            <p css={tw`px-4 py-8 text-center text-sm text-neutral-400`}>Nothing new.</p>
                        ) : (
                            list.data.map((n) => {
                                const [icon, color] = ICONS[n.kind] || ICONS.info;

                                return (
                                    <button
                                        key={n.id}
                                        type={'button'}
                                        onClick={() => openOne(n)}
                                        css={[
                                            tw`flex w-full gap-3 px-4 py-3 text-left border-b border-white/5 hover:bg-white/10 focus:outline-none`,
                                            !n.read && tw`bg-white/5`,
                                        ]}
                                    >
                                        <span css={tw`flex-shrink-0 mt-0.5`} style={{ color }}>
                                            <FontAwesomeIcon icon={n.everyone && n.kind === 'info' ? faBullhorn : icon} />
                                        </span>
                                        <span css={tw`flex-1 min-w-0`}>
                                            <span css={tw`flex items-start gap-2`}>
                                                <span css={tw`flex-1 text-sm font-semibold text-neutral-50 leading-snug`}>
                                                    {n.title}
                                                </span>
                                                {!n.read && (
                                                    <span
                                                        css={tw`flex-shrink-0 mt-1.5 w-2 h-2 rounded-full bg-primary-500`}
                                                    />
                                                )}
                                            </span>
                                            {n.body && (
                                                <span
                                                    css={tw`block text-xs text-neutral-300 mt-0.5 leading-snug whitespace-pre-line`}
                                                >
                                                    {n.body}
                                                </span>
                                            )}
                                            {n.createdAt && (
                                                <span css={tw`block text-2xs text-neutral-500 mt-1`}>
                                                    {formatDistanceToNow(n.createdAt, { addSuffix: true, locale: dateLocale() })}
                                                </span>
                                            )}
                                        </span>
                                    </button>
                                );
                            })
                        )}
                    </div>
                </div>
            )}
        </div>
    );
};
