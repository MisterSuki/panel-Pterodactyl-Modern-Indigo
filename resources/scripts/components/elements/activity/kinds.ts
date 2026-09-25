import React from 'react';
import { enUS, fr } from 'date-fns/locale';
import {
    AdjustmentsIcon,
    ArchiveIcon,
    ClockIcon,
    CogIcon,
    DatabaseIcon,
    DocumentTextIcon,
    FolderOpenIcon,
    GlobeAltIcon,
    KeyIcon,
    LightningBoltIcon,
    LockClosedIcon,
    PlayIcon,
    RefreshIcon,
    StopIcon,
    TerminalIcon,
    UserIcon,
    UsersIcon,
} from '@heroicons/react/solid';

export interface EventKind {
    icon: (props: React.ComponentProps<'svg'>) => JSX.Element;
    // Colours of the round badge.
    className: string;
}

const kind = (icon: EventKind['icon'], className: string): EventKind => ({ icon, className });

const red = 'bg-red-500/15 text-red-300 ring-red-500/30';
const green = 'bg-green-500/15 text-green-300 ring-green-500/30';
const amber = 'bg-yellow-500/15 text-yellow-300 ring-yellow-500/30';
const blue = 'bg-blue-500/15 text-blue-300 ring-blue-500/30';
const purple = 'bg-purple-500/15 text-purple-300 ring-purple-500/30';
const cyan = 'bg-cyan-500/15 text-cyan-300 ring-cyan-500/30';
const pink = 'bg-pink-500/15 text-pink-300 ring-pink-500/30';
const gray = 'bg-gray-500/20 text-gray-300 ring-gray-500/30';
const primary = 'bg-primary-500/15 text-primary-300 ring-primary-500/30';

// The icon and colour of an event, from its name (server:power.stop, server:file.write, auth:success...).
export const eventKind = (event: string): EventKind => {
    if (event === 'server:power.start') return kind(PlayIcon, green);
    if (event === 'server:power.restart') return kind(RefreshIcon, amber);
    if (event.startsWith('server:power.')) return kind(StopIcon, red);
    if (event.startsWith('server:console.')) return kind(TerminalIcon, gray);
    if (event.startsWith('server:sftp.')) return kind(FolderOpenIcon, blue);
    if (event.startsWith('server:file.')) return kind(DocumentTextIcon, blue);
    if (event.startsWith('server:backup.')) {
        return kind(ArchiveIcon, /fail/.test(event) ? red : purple);
    }
    if (event.startsWith('server:database.')) return kind(DatabaseIcon, cyan);
    if (event.startsWith('server:schedule.') || event.startsWith('server:task.')) return kind(ClockIcon, amber);
    if (event.startsWith('server:subuser.')) return kind(UsersIcon, pink);
    if (event.startsWith('server:allocation.')) return kind(GlobeAltIcon, cyan);
    if (event.startsWith('server:startup.')) return kind(AdjustmentsIcon, primary);
    if (event.startsWith('server:settings.') || event === 'server:reinstall') return kind(CogIcon, gray);
    if (event.startsWith('auth:')) return kind(LockClosedIcon, /fail/.test(event) ? red : green);
    if (event.startsWith('user:api-key.') || event.startsWith('user:ssh-key.')) return kind(KeyIcon, amber);
    if (event.startsWith('user:')) return kind(UserIcon, primary);

    return kind(LightningBoltIcon, primary);
};

// The groups a person can filter the activity of a server by: the part of the event name that they share.
export const serverKinds: { label: string; event: string }[] = [
    { label: 'Power', event: 'server:power.' },
    { label: 'Console', event: 'server:console.' },
    { label: 'Files', event: 'server:file.' },
    { label: 'SFTP', event: 'server:sftp.' },
    { label: 'Backups', event: 'server:backup.' },
    { label: 'Databases', event: 'server:database.' },
    { label: 'Schedules', event: 'server:schedule.' },
    { label: 'Users', event: 'server:subuser.' },
    { label: 'Network', event: 'server:allocation.' },
    { label: 'Startup', event: 'server:startup.' },
    { label: 'Settings', event: 'server:settings.' },
];

// The language the panel is shown in (see public/js/translator.js), for dates written in words.
export const panelLanguage = (): string => {
    const w = window as any;
    const language = String(w.PterodactylLanguage || w.PterodactylUser?.language || 'en').toLowerCase();

    return /^[a-z]{2}$/.test(language) ? language : 'en';
};

export const dateLocale = () => (panelLanguage() === 'fr' ? fr : enUS);
