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

// The same for the tabs of the account.
export const accountTabIcons: Record<string, IconDefinition> = {
    '/': faUserCircle,
    '/appearance': faPalette,
    '/api': faCode,
    '/ssh': faKey,
    '/activity': faList,
};
