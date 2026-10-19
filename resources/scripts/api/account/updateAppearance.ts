import http from '@/api/http';

export interface AppearancePrefs {
    // "default" follows the choice of the administration.
    accent: string;
    mode: 'default' | 'gradient' | 'animated' | 'image';
    gradient: string;
    effect: string;
    motion: boolean;
}

export interface AppearanceSite {
    accent: string;
    mode: 'gradient' | 'animated' | 'image';
    gradient: string;
    effect: string;
    dim: number;
    blur: number;
    userChoice: boolean;
    image: string | null;
}

// What /js/appearance.js gives the page (it is loaded on every page, before the dashboard).
export interface AppearanceApi {
    accents: Record<string, string>;
    effects: string[];
    gradients: string[];
    gradientCss: (name: string) => string | null;
    site: AppearanceSite;
    user: AppearancePrefs | null;
    preview: (prefs: AppearancePrefs) => { accent: string };
    set: (prefs: AppearancePrefs) => { accent: string };
    resolve: (prefs: AppearancePrefs) => { accent: string; mode: string };
    thumbnail: (effect: string, accent: string, width: number, height: number) => string | null;
}

export const appearanceApi = (): AppearanceApi | null => (window as any).PdAppearance || null;

export default (prefs: AppearancePrefs): Promise<AppearancePrefs> =>
    http.put('/api/client/account/appearance', prefs).then(({ data }) => data.appearance);
