import React from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import tw from 'twin.macro';

const RADIUS = 24;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

interface Props {
    icon: IconDefinition;
    label: string;
    // The figure written next to the ring, and what it is out of ("of 10 GiB").
    value: string;
    limit: string;
    // How full it is, from 0 to 1 (it can go above 1). Null when there is no limit to compare with.
    ratio: number | null;
    // The colour of the ring while all is well. It turns yellow when it is nearly full, and red when it is about to be.
    color: string;
}

const WARNING = 0.75;
const ALARM = 0.9;

// A ring that fills up with the usage of a resource, with the figure beside it.
export default ({ icon, label, value, limit, ratio, color }: Props) => {
    const shown = ratio === null ? 0 : Math.max(0, Math.min(1, ratio));
    const stroke = ratio !== null && ratio >= ALARM ? '#f87171' : ratio !== null && ratio >= WARNING ? '#fbbf24' : color;

    return (
        <div css={tw`flex items-center gap-3 min-w-0`}>
            <div css={tw`relative flex-shrink-0 w-14 h-14`}>
                <svg viewBox={'0 0 60 60'} css={[tw`w-full h-full`, { transform: 'rotate(-90deg)' }]} aria-hidden>
                    <circle cx={30} cy={30} r={RADIUS} fill={'none'} stroke={'rgba(255,255,255,0.07)'} strokeWidth={5} />
                    {ratio !== null && (
                        <circle
                            cx={30}
                            cy={30}
                            r={RADIUS}
                            fill={'none'}
                            stroke={stroke}
                            strokeWidth={5}
                            strokeLinecap={'round'}
                            strokeDasharray={CIRCUMFERENCE}
                            strokeDashoffset={CIRCUMFERENCE * (1 - Math.max(shown, 0.02))}
                            style={{
                                transition: 'stroke-dashoffset 700ms ease-out, stroke 300ms',
                                filter: `drop-shadow(0 0 3px ${stroke})`,
                            }}
                        />
                    )}
                </svg>
                <span css={tw`absolute inset-0 flex items-center justify-center text-neutral-300`}>
                    {ratio === null ? (
                        <FontAwesomeIcon icon={icon} css={tw`text-sm`} />
                    ) : (
                        <span css={tw`text-xs font-semibold tabular-nums text-neutral-100`}>
                            {Math.round(ratio * 100)}%
                        </span>
                    )}
                </span>
            </div>
            <div css={tw`min-w-0`}>
                <p css={tw`text-2xs uppercase tracking-wider text-neutral-500 flex items-center gap-1.5`}>
                    <FontAwesomeIcon icon={icon} css={tw`text-neutral-600`} />
                    {label}
                </p>
                <p css={tw`text-sm font-semibold text-neutral-50 tabular-nums truncate`}>{value}</p>
                <p css={tw`text-xs text-neutral-500 truncate`}>of {limit}</p>
            </div>
        </div>
    );
};
