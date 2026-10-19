import React, { useMemo, useRef, useState } from 'react';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCheck, faImage, faMagic, faPalette, faSlidersH, faSwatchbook } from '@fortawesome/free-solid-svg-icons';
import PageContentBlock from '@/components/elements/PageContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import updateAppearance, { appearanceApi, AppearancePrefs } from '@/api/account/updateAppearance';

const FLASH_KEY = 'account:appearance';

const ACCENT_NAMES: Record<string, string> = {
    blue: 'Blue',
    indigo: 'Indigo',
    purple: 'Purple',
    pink: 'Pink',
    red: 'Red',
    orange: 'Orange',
    green: 'Green',
    teal: 'Teal',
    graphite: 'Graphite',
};

const EFFECT_NAMES: Record<string, string> = {
    aurora: 'Aurora',
    silk: 'Silk',
    plasma: 'Plasma',
    waves: 'Waves',
    lines: 'Lines',
    glow: 'Glow',
    beams: 'Beams',
    stars: 'Stars',
    bubbles: 'Bubbles',
};

const GRADIENT_NAMES: Record<string, string> = {
    liquid: 'Liquid Glass',
    midnight: 'Midnight',
    ocean: 'Ocean',
    sunset: 'Sunset',
    forest: 'Forest',
};

// The Liquid Glass wallpaper of the stylesheet, to show it in its little card.
const LIQUID_PREVIEW =
    'radial-gradient(55% 45% at 12% 8%, rgb(var(--pd-accent-500) / 0.55), transparent 70%), radial-gradient(45% 40% at 88% 12%, rgba(191, 90, 242, 0.45), transparent 70%), radial-gradient(50% 45% at 82% 92%, rgba(255, 55, 95, 0.38), transparent 70%), linear-gradient(160deg, #0b1030 0%, #160c2c 48%, #05060f 100%)';

const DEFAULTS: AppearancePrefs = { accent: 'default', mode: 'default', gradient: 'liquid', effect: 'aurora', motion: true };

const Section = ({ icon, title, text, children }: { icon: any; title: string; text: string; children: React.ReactNode }) => (
    <div css={tw`rounded-2xl bg-neutral-800 border border-white/10 shadow-card p-5 sm:p-6 mb-6`}>
        <div css={tw`flex items-start gap-3 mb-5`}>
            <span
                css={tw`flex items-center justify-center w-9 h-9 rounded-xl bg-primary-500/20 text-primary-300 flex-shrink-0`}
            >
                <FontAwesomeIcon icon={icon} />
            </span>
            <div>
                <h2 css={tw`text-lg text-neutral-50 font-semibold`}>{title}</h2>
                <p css={tw`text-sm text-neutral-400`}>{text}</p>
            </div>
        </div>
        {children}
    </div>
);

// A card that can be picked: a picture of the choice and its name.
const Choice = ({
    selected,
    label,
    onClick,
    preview,
    disabled,
}: {
    selected: boolean;
    label: string;
    onClick: () => void;
    preview: React.CSSProperties;
    disabled?: boolean;
}) => (
    <button
        type={'button'}
        onClick={onClick}
        disabled={disabled}
        css={[
            tw`relative block w-full text-left rounded-xl overflow-hidden border transition-all duration-150 focus:outline-none`,
            selected
                ? tw`border-primary-400 ring-2 ring-primary-500/60`
                : tw`border-white/10 hover:border-white/30 transform hover:-translate-y-0.5`,
        ]}
    >
        <span css={tw`block h-24 sm:h-28 w-full bg-cover bg-center`} style={preview} />
        <span
            css={tw`absolute inset-x-0 bottom-0 px-3 pb-2 pt-6 text-sm font-semibold text-white`}
            style={{ background: 'linear-gradient(180deg, transparent, rgba(0, 0, 0, 0.7))' }}
        >
            {label}
        </span>
        {selected && (
            <span
                css={tw`absolute top-2 right-2 flex items-center justify-center w-6 h-6 rounded-full bg-primary-500 text-white text-xs`}
            >
                <FontAwesomeIcon icon={faCheck} />
            </span>
        )}
    </button>
);

const Segmented = ({
    value,
    options,
    onChange,
}: {
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
}) => (
    <div css={tw`flex flex-wrap gap-1 p-1 rounded-2xl bg-black/30 border border-white/10 mb-5`}>
        {options.map((option) => (
            <button
                key={option.value}
                type={'button'}
                onClick={() => onChange(option.value)}
                css={[
                    tw`flex-1 whitespace-nowrap px-3 py-2 rounded-xl text-sm font-medium transition-all duration-150 focus:outline-none`,
                    value === option.value
                        ? tw`bg-white/20 text-white shadow-card`
                        : tw`text-neutral-300 hover:text-white hover:bg-white/10`,
                ]}
            >
                {option.label}
            </button>
        ))}
    </div>
);

export default () => {
    const api = appearanceApi();
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const [prefs, setPrefs] = useState<AppearancePrefs>({ ...DEFAULTS, ...(api?.user || {}) });
    const [saving, setSaving] = useState(false);
    const request = useRef(0);

    const look = api ? api.resolve(prefs) : { accent: 'blue', mode: 'gradient' };
    const thumbnails = useMemo(() => {
        const out: Record<string, string | null> = {};
        (api?.effects || []).forEach((effect) => (out[effect] = api!.thumbnail(effect, look.accent, 320, 180)));

        return out;
    }, [look.accent]);

    if (!api) {
        return (
            <PageContentBlock title={'Appearance'}>
                <p css={tw`text-neutral-400 mt-10`}>The appearance settings could not be loaded. Reload the page.</p>
            </PageContentBlock>
        );
    }

    const site = api.site;

    const change = (patch: Partial<AppearancePrefs>) => {
        const next = { ...prefs, ...patch };
        setPrefs(next);
        api.preview(next);
        clearFlashes(FLASH_KEY);
        setSaving(true);
        const id = ++request.current;
        updateAppearance(next)
            .then((saved) => {
                if (id !== request.current) return;
                api.set(saved);
            })
            .catch((error) => {
                if (id !== request.current) return;
                clearAndAddHttpError({ key: FLASH_KEY, error });
            })
            .then(() => id === request.current && setSaving(false));
    };

    const modes = [
        { value: 'default', label: 'Panel default' },
        { value: 'gradient', label: 'Gradient' },
        { value: 'animated', label: 'Animated' },
        ...(site.image ? [{ value: 'image', label: 'Picture' }] : []),
    ];

    return (
        <PageContentBlock title={'Appearance'}>
            <div css={tw`max-w-5xl mx-auto mt-8 sm:mt-10`}>
                <div css={tw`flex items-center gap-4 mb-6`}>
                    <span
                        css={tw`flex flex-shrink-0 items-center justify-center w-12 h-12 rounded-2xl bg-primary-500/20 text-primary-300 text-xl border border-white/10`}
                    >
                        <FontAwesomeIcon icon={faPalette} />
                    </span>
                    <div css={tw`min-w-0`}>
                        <h1 css={tw`text-2xl text-neutral-50 font-semibold`}>Appearance</h1>
                        <p css={tw`text-sm text-neutral-400`}>
                            Pick your accent colour and the background of the panel. Saved on your account.
                        </p>
                    </div>
                    {saving && (
                        <span
                            css={tw`ml-auto w-4 h-4 rounded-full border-2 border-primary-400 border-t-transparent animate-spin flex-shrink-0`}
                        />
                    )}
                </div>
                <FlashMessageRender byKey={FLASH_KEY} css={tw`mb-4`} />

                <Section icon={faSwatchbook} title={'Accent colour'} text={'Buttons, links, highlights and focus rings.'}>
                    <div css={tw`flex flex-wrap gap-4`}>
                        <button
                            type={'button'}
                            onClick={() => change({ accent: 'default' })}
                            css={tw`flex flex-col items-center gap-2 w-16 focus:outline-none`}
                        >
                            <span
                                css={[
                                    tw`flex items-center justify-center w-11 h-11 rounded-full border-2 text-white text-xs transition-transform duration-150 hover:scale-110`,
                                    prefs.accent === 'default' ? tw`border-white` : tw`border-white/20`,
                                ]}
                                style={{ background: api.accents[site.accent] || api.accents.blue }}
                            >
                                {prefs.accent === 'default' && <FontAwesomeIcon icon={faCheck} />}
                            </span>
                            <span css={tw`text-xs text-neutral-300 text-center leading-tight`}>Panel default</span>
                        </button>
                        {Object.keys(api.accents).map((name) => (
                            <button
                                key={name}
                                type={'button'}
                                onClick={() => change({ accent: name })}
                                css={tw`flex flex-col items-center gap-2 w-16 focus:outline-none`}
                            >
                                <span
                                    css={[
                                        tw`flex items-center justify-center w-11 h-11 rounded-full border-2 text-white text-xs transition-transform duration-150 hover:scale-110`,
                                        prefs.accent === name ? tw`border-white` : tw`border-transparent`,
                                    ]}
                                    style={{
                                        background: api.accents[name],
                                        boxShadow: `0 6px 18px -6px ${api.accents[name]}`,
                                    }}
                                >
                                    {prefs.accent === name && <FontAwesomeIcon icon={faCheck} />}
                                </span>
                                <span css={tw`text-xs text-neutral-300 text-center leading-tight`}>
                                    {ACCENT_NAMES[name] || name}
                                </span>
                            </button>
                        ))}
                    </div>
                </Section>

                <Section icon={faMagic} title={'Background'} text={'What is drawn behind the pages.'}>
                    {!site.userChoice ? (
                        <p css={tw`text-sm text-neutral-300`}>
                            The background is chosen by the administration of the panel.
                        </p>
                    ) : (
                        <>
                            <Segmented
                                value={prefs.mode}
                                options={modes}
                                onChange={(mode) => change({ mode: mode as AppearancePrefs['mode'] })}
                            />
                            {prefs.mode === 'default' && (
                                <p css={tw`text-sm text-neutral-300`}>
                                    You get the background chosen by the administration of the panel.
                                </p>
                            )}
                            {prefs.mode === 'gradient' && (
                                <div css={tw`grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3`}>
                                    {api.gradients.map((name) => (
                                        <Choice
                                            key={name}
                                            label={GRADIENT_NAMES[name] || name}
                                            selected={prefs.gradient === name}
                                            onClick={() => change({ gradient: name })}
                                            preview={{ background: api.gradientCss(name) || LIQUID_PREVIEW }}
                                        />
                                    ))}
                                </div>
                            )}
                            {prefs.mode === 'animated' && (
                                <div css={tw`grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3`}>
                                    {api.effects.map((name) => (
                                        <Choice
                                            key={name}
                                            label={EFFECT_NAMES[name] || name}
                                            selected={prefs.effect === name}
                                            onClick={() => change({ effect: name })}
                                            preview={
                                                thumbnails[name]
                                                    ? { backgroundImage: `url(${thumbnails[name]})` }
                                                    : { background: '#0b0d18' }
                                            }
                                        />
                                    ))}
                                </div>
                            )}
                            {prefs.mode === 'image' && site.image && (
                                <div css={tw`grid grid-cols-2 md:grid-cols-3 gap-3`}>
                                    <Choice
                                        label={'Picture of the panel'}
                                        selected
                                        onClick={() => undefined}
                                        preview={{ backgroundImage: `url(${site.image})` }}
                                    />
                                </div>
                            )}
                        </>
                    )}
                </Section>

                <Section icon={faSlidersH} title={'Motion'} text={'Animated backgrounds can be stopped to save battery.'}>
                    <button
                        type={'button'}
                        onClick={() => change({ motion: !prefs.motion })}
                        css={tw`flex items-center justify-between w-full gap-4 text-left focus:outline-none`}
                    >
                        <span>
                            <span css={tw`block text-sm text-neutral-100 font-medium`}>Animate the background</span>
                            <span css={tw`block text-xs text-neutral-400 mt-0.5`}>
                                When it is off, animated effects stay still.
                            </span>
                        </span>
                        <span
                            css={[
                                tw`relative inline-flex flex-shrink-0 w-12 h-7 rounded-full transition-colors duration-200`,
                                prefs.motion ? tw`bg-green-500` : tw`bg-white/20`,
                            ]}
                        >
                            <span
                                css={tw`absolute top-0.5 left-0.5 w-6 h-6 rounded-full bg-white shadow transition-transform duration-200`}
                                style={{ transform: `translateX(${prefs.motion ? 20 : 0}px)` }}
                            />
                        </span>
                    </button>
                </Section>

                {site.mode === 'image' && prefs.mode === 'default' && (
                    <p css={tw`text-xs text-neutral-500 text-center`}>
                        <FontAwesomeIcon icon={faImage} css={tw`mr-1`} />
                        <span>The panel currently uses a picture as its background.</span>
                    </p>
                )}
            </div>
        </PageContentBlock>
    );
};
