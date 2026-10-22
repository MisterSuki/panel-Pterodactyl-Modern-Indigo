import { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import {
    faCar,
    faCode,
    faCube,
    faGamepad,
    faRobot,
    faServer,
    faTractor,
    faTruck,
} from '@fortawesome/free-solid-svg-icons';
import { Server } from '@/api/server/getServer';

export interface ServerGame {
    name: string;
    icon: IconDefinition;
    // The two colours of the tile behind the icon.
    from: string;
    to: string;
}

// The game (or kind of app) of a server, guessed from its image, its startup command and its egg features, for the
// icon and the label of its card. Anything not recognised is a plain server.
const GAMES: Array<ServerGame & { test: RegExp }> = [
    { test: /fxserver|cfx-server|citizen_dir|fivem/i, name: 'FiveM', icon: faCar, from: '#f97316', to: '#ea580c' },
    { test: /fs25|farming/i, name: 'Farming Simulator', icon: faTractor, from: '#84cc16', to: '#4d7c0f' },
    {
        test: /ets2|eurotruck|american.?truck|ats_server/i,
        name: 'Truck Simulator',
        icon: faTruck,
        from: '#38bdf8',
        to: '#0369a1',
    },
    {
        test: /minecraft|paper|spigot|forge|fabric|bungee|velocity|purpur/i,
        name: 'Minecraft',
        icon: faCube,
        from: '#4ade80',
        to: '#15803d',
    },
    { test: /steamcmd|srcds/i, name: 'Steam', icon: faGamepad, from: '#818cf8', to: '#4338ca' },
    { test: /python|\.py\b/i, name: 'Python', icon: faRobot, from: '#facc15', to: '#2563eb' },
    { test: /node|npm|yarn|bun\b|\.js\b/i, name: 'Node.js', icon: faRobot, from: '#4ade80', to: '#166534' },
    { test: /java|golang|rust|php|dotnet/i, name: 'App', icon: faCode, from: '#a78bfa', to: '#6d28d9' },
];

export default (server: Server): ServerGame => {
    if (server.eggFeatures.includes('eula') || server.eggFeatures.includes('java_version')) {
        return GAMES.find((game) => game.name === 'Minecraft')!;
    }
    const haystack = `${server.dockerImage || ''} ${server.invocation || ''}`;

    return (
        GAMES.find((game) => game.test.test(haystack)) || {
            name: '',
            icon: faServer,
            from: 'rgb(var(--pd-accent-400))',
            to: 'rgb(var(--pd-accent-600))',
        }
    );
};
