import { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import { faCar, faCube, faGamepad, faGlobe, faRobot, faTerminal, faTree } from '@fortawesome/free-solid-svg-icons';

// The look of an offer in the shop, from its kind of game (OfferGame on the panel): an icon and two colours.
export interface OfferLook {
    icon: IconDefinition;
    from: string;
    to: string;
}

const LOOKS: Record<string, OfferLook> = {
    minecraft: { icon: faCube, from: '#4ade80', to: '#15803d' },
    fivem: { icon: faCar, from: '#f97316', to: '#c2410c' },
    survival: { icon: faTree, from: '#a3e635', to: '#4d7c0f' },
    bot: { icon: faRobot, from: '#60a5fa', to: '#1d4ed8' },
    website: { icon: faGlobe, from: '#38bdf8', to: '#6366f1' },
    linux: { icon: faTerminal, from: '#94a3b8', to: '#334155' },
    game: { icon: faGamepad, from: '#a78bfa', to: '#6d28d9' },
};

export default (game: string | undefined): OfferLook => LOOKS[game || 'game'] || LOOKS.game;
