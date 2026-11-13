import React from 'react';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCheck } from '@fortawesome/free-solid-svg-icons';
import Sparkline from '@/components/server/console/Sparkline';
import { accentHex } from '@/lib/helpers';

// The right half of the sign-in pages on a wide screen: a few words about the panel and a picture of a server in it,
// drawn with plain markup (nothing is loaded, nothing is live). Hidden on phones and tablets, where only the form shows.
const Panel = styled.aside`
    ${tw`hidden lg:flex relative flex-col justify-center overflow-hidden px-14 xl:px-20 select-none`};
    width: 48%;
    max-width: 46rem;
    border-left: 1px solid var(--pd-border);
    background: radial-gradient(55% 45% at 85% 5%, rgb(var(--pd-accent-500) / 0.32), transparent 70%),
        radial-gradient(45% 40% at 0% 100%, rgb(var(--pd-accent-700) / 0.3), transparent 70%),
        radial-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px) 0 0 / 22px 22px, rgba(10, 10, 10, 0.72);

    & .window {
        position: relative;
        margin-top: 3rem;
        border-radius: 14px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(17, 17, 17, 0.88);
        box-shadow: 0 40px 80px -30px rgba(0, 0, 0, 0.9), 0 0 0 1px rgba(0, 0, 0, 0.4),
            inset 0 1px 0 rgba(255, 255, 255, 0.06);
        transform: perspective(1400px) rotateY(-9deg) rotateX(4deg);
        transform-origin: left center;
        overflow: hidden;
    }

    & .bar {
        ${tw`flex items-center gap-2 px-4 py-3`};
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    }

    & .light {
        ${tw`w-2.5 h-2.5 rounded-full`};
        background: rgba(255, 255, 255, 0.14);
    }

    & .stat {
        ${tw`relative overflow-hidden rounded-lg px-3 pt-2.5 pb-6`};
        border: 1px solid rgba(255, 255, 255, 0.07);
        background: rgba(255, 255, 255, 0.02);
    }

    & .console {
        ${tw`px-4 py-3 font-mono text-xs leading-6 text-neutral-400`};
        border-top: 1px solid rgba(255, 255, 255, 0.07);
        background: rgba(0, 0, 0, 0.35);
    }
`;

const CPU = [22, 26, 24, 31, 28, 35, 30, 27, 33, 38, 34, 29, 32, 36, 31, 28, 30, 34];
const MEMORY = [40, 41, 41, 42, 44, 43, 45, 46, 46, 47, 47, 48, 48, 49, 49, 50, 50, 51];

const Stat = ({ label, value, points, color }: { label: string; value: string; points: number[]; color: string }) => (
    <div className={'stat'}>
        <p css={tw`text-2xs text-neutral-500`}>{label}</p>
        <p css={tw`mt-0.5 text-sm font-semibold text-neutral-100 tabular-nums`}>{value}</p>
        <Sparkline
            points={points}
            max={Math.max(...points) * 1.5}
            color={color}
            className={'absolute inset-x-0 bottom-0 w-full h-6 opacity-70 pointer-events-none'}
        />
    </div>
);

export default () => (
    <Panel aria-hidden={'true'}>
        <div css={tw`max-w-md`}>
            <h2 css={tw`text-3xl xl:text-4xl font-semibold text-neutral-50 tracking-tight leading-tight`}>
                Your servers, under control.
            </h2>
            <p css={tw`mt-4 text-base text-neutral-400 leading-relaxed`}>
                The console, the files, the backups and the statistics of every server, in one place.
            </p>
            <ul css={tw`mt-6 space-y-2.5`}>
                {['Live console and statistics', 'Automatic backups', 'Sign in with a passkey'].map((text) => (
                    <li key={text} css={tw`flex items-center gap-3 text-sm text-neutral-300`}>
                        <span
                            css={tw`flex items-center justify-center w-5 h-5 rounded-full text-2xs text-white`}
                            style={{ background: 'rgb(var(--pd-accent-500) / 0.25)' }}
                        >
                            <FontAwesomeIcon icon={faCheck} />
                        </span>
                        <span>{text}</span>
                    </li>
                ))}
            </ul>
        </div>

        <div className={'window'} css={tw`max-w-lg`}>
            <div className={'bar'}>
                <span className={'light'} />
                <span className={'light'} />
                <span className={'light'} />
                <span css={tw`ml-3 text-xs font-medium text-neutral-300`}>survival-1</span>
                <span
                    css={tw`ml-auto inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-2xs font-medium`}
                    style={{ background: '#3ecf8e1f', color: '#6ee7b7' }}
                >
                    <span css={tw`w-1.5 h-1.5 rounded-full`} style={{ background: '#3ecf8e' }} />
                    <span>Running</span>
                </span>
            </div>
            <div css={tw`grid grid-cols-3 gap-2.5 p-4`}>
                <Stat label={'CPU'} value={'34.2 %'} points={CPU} color={accentHex(400)} />
                <Stat label={'Memory'} value={'5.1 GiB'} points={MEMORY} color={'#22d3ee'} />
                <Stat label={'Players'} value={'18 / 64'} points={[...CPU].reverse()} color={'#a78bfa'} />
            </div>
            <div className={'console'}>
                <p>
                    <span css={tw`text-neutral-600`}>12:04:51</span> Loading world &quot;main&quot;
                </p>
                <p>
                    <span css={tw`text-neutral-600`}>12:04:53</span>{' '}
                    <span css={tw`text-green-400`}>Server started on port 25565</span>
                </p>
                <p>
                    <span css={tw`text-neutral-600`}>12:05:10</span> alex joined the game
                </p>
                <p>
                    <span css={tw`text-neutral-600`}>12:05:12</span>{' '}
                    <span style={{ color: 'rgb(var(--pd-accent-300))' }}>Backup created (1.2 GiB)</span>
                </p>
            </div>
        </div>
    </Panel>
);
