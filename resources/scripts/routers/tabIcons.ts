import {
    faArchive,
    faChartLine,
    faClock,
    faCode,
    faCog,
    faCube,
    faDatabase,
    faFolder,
    faGlobe,
    faKey,
    faList,
    faNetworkWired,
    faPalette,
    faPlayCircle,
    faTerminal,
    faTruck,
    faUserCircle,
    faUsers,
    IconDefinition,
} from '@fortawesome/free-solid-svg-icons';

// The small icon in front of each tab of a server.
export const serverTabIcons: Record<string, IconDefinition> = {
    '/': faTerminal,
    '/web': faGlobe,
    '/files': faFolder,
    '/minecraft': faCube,
    '/ets2': faTruck,
    '/databases': faDatabase,
    '/schedules': faClock,
    '/statistics': faChartLine,
    '/subdomains': faGlobe,
    '/users': faUsers,
    '/backups': faArchive,
    '/network': faNetworkWired,
    '/startup': faPlayCircle,
    '/settings': faCog,
    '/activity': faList,
};

// The colour of the square behind each icon, from the system colours of Apple.
export const serverTabColors: Record<string, string> = {
    '/': '#48484a',
    '/web': '#40c8e0',
    '/files': '#0a84ff',
    '/minecraft': '#30d158',
    '/ets2': '#ff9f0a',
    '/databases': '#bf5af2',
    '/schedules': '#ff9f0a',
    '/statistics': '#ff375f',
    '/subdomains': '#40c8e0',
    '/users': '#5e5ce6',
    '/backups': '#ac8e68',
    '/network': '#32ade6',
    '/startup': '#30d158',
    '/settings': '#8e8e93',
    '/activity': '#ff9500',
};

// The same for the tabs of the account.
export const accountTabIcons: Record<string, IconDefinition> = {
    '/': faUserCircle,
    '/appearance': faPalette,
    '/api': faCode,
    '/ssh': faKey,
    '/activity': faList,
};

export const accountTabColors: Record<string, string> = {
    '/': '#0a84ff',
    '/appearance': '#bf5af2',
    '/api': '#5e5ce6',
    '/ssh': '#8e8e93',
    '/activity': '#ff9500',
};
