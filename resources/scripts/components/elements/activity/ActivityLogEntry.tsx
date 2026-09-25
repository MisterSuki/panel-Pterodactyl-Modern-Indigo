import React from 'react';
import { Link } from 'react-router-dom';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import Translate from '@/components/elements/Translate';
import { format, formatDistanceToNowStrict } from 'date-fns';
import { ActivityLog } from '@definitions/user';
import ActivityLogMetaButton from '@/components/elements/activity/ActivityLogMetaButton';
import { FolderOpenIcon, TerminalIcon } from '@heroicons/react/solid';
import classNames from 'classnames';
import style from './style.module.css';
import Avatar from '@/components/Avatar';
import useLocationHash from '@/plugins/useLocationHash';
import { getObjectKeys, isObject } from '@/lib/objects';
import { dateLocale, eventKind } from '@/components/elements/activity/kinds';

interface Props {
    activity: ActivityLog;
    children?: React.ReactNode;
}

function wrapProperties(value: unknown): any {
    if (value === null || typeof value === 'string' || typeof value === 'number') {
        return `<strong>${String(value)}</strong>`;
    }

    if (isObject(value)) {
        return getObjectKeys(value).reduce((obj, key) => {
            if (key === 'count' || (typeof key === 'string' && key.endsWith('_count'))) {
                return { ...obj, [key]: value[key] };
            }
            return { ...obj, [key]: wrapProperties(value[key]) };
        }, {} as Record<string, unknown>);
    }

    if (Array.isArray(value)) {
        return value.map(wrapProperties);
    }

    return value;
}

// One event: what happened (in the language of the person), with an icon and a colour for its kind, then who did it,
// when, and from where.
export default ({ activity, children }: Props) => {
    const { pathTo } = useLocationHash();
    const actor = activity.relationships.actor;
    const properties = wrapProperties(activity.properties);
    const kind = eventKind(activity.event);
    const locale = dateLocale();

    return (
        <div className={style.entry}>
            <div className={style.rail}>
                <div className={classNames(style.badge, kind.className)}>
                    <kind.icon className={'w-5 h-5'} />
                </div>
            </div>
            <div className={'flex-1 min-w-0 pb-5'}>
                <div className={style.card}>
                    <div className={'flex items-start gap-3'}>
                        <div className={'flex-1 min-w-0'}>
                            <p className={style.description}>
                                <Translate
                                    ns={'activity'}
                                    values={properties}
                                    i18nKey={activity.event.replace(':', '.')}
                                />
                            </p>
                            <div className={style.details}>
                                <span className={'inline-flex items-center gap-1.5'}>
                                    <span className={'w-5 h-5 rounded-full overflow-hidden bg-gray-600 shrink-0'}>
                                        {actor?.image ? (
                                            <img src={actor.image} alt={''} className={'w-full h-full object-cover'} />
                                        ) : (
                                            <Avatar name={actor?.uuid || 'system'} />
                                        )}
                                    </span>
                                    <Tooltip placement={'top'} content={actor?.email || 'System User'}>
                                        <span className={'text-gray-100 font-medium'}>
                                            {actor?.username || 'System'}
                                        </span>
                                    </Tooltip>
                                </span>
                                <span className={style.dot} />
                                <Tooltip placement={'top'} content={format(activity.timestamp, 'PPPp', { locale })}>
                                    <span>
                                        {format(activity.timestamp, 'HH:mm', { locale })} (
                                        {formatDistanceToNowStrict(activity.timestamp, { addSuffix: true, locale })})
                                    </span>
                                </Tooltip>
                                {activity.ip && (
                                    <>
                                        <span className={style.dot} />
                                        <span className={'font-mono text-xs'}>{activity.ip}</span>
                                    </>
                                )}
                                {activity.isApi && (
                                    <Tooltip placement={'top'} content={'Using API Key'}>
                                        <span className={style.tag}>
                                            <TerminalIcon className={'w-3.5 h-3.5'} /> API
                                        </span>
                                    </Tooltip>
                                )}
                                {activity.event.startsWith('server:sftp.') && (
                                    <Tooltip placement={'top'} content={'Using SFTP'}>
                                        <span className={style.tag}>
                                            <FolderOpenIcon className={'w-3.5 h-3.5'} /> SFTP
                                        </span>
                                    </Tooltip>
                                )}
                                {children && <span className={style.icons}>{children}</span>}
                            </div>
                        </div>
                        <Link to={`#${pathTo({ event: activity.event })}`} className={style.event}>
                            {activity.event}
                        </Link>
                        {activity.hasAdditionalMetadata && <ActivityLogMetaButton meta={activity.properties} />}
                    </div>
                </div>
            </div>
        </div>
    );
};
