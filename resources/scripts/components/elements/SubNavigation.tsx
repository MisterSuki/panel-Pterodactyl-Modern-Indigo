import React, { useEffect, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import ContentContainer from '@/components/elements/ContentContainer';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faBars, faChevronDown } from '@fortawesome/free-solid-svg-icons';
import { IconDefinition } from '@fortawesome/fontawesome-svg-core';

// The tabs, in a plain sidebar on the left of the page (like the settings of Linear or Vercel): every tab is visible,
// nothing to scroll. It stays in view while the page scrolls. On a phone (or a narrow window) it becomes a button
// showing the open tab, which opens the list of tabs.
const Layout = styled.div`
    ${tw`w-full`};

    & > .pd-side-inner {
        ${tw`mx-auto px-4 sm:px-6 lg:flex lg:items-start lg:gap-10`};
        max-width: 1280px;
    }

    & .pd-side {
        ${tw`pt-4 lg:pt-0 lg:flex-shrink-0 lg:sticky`};
        top: 88px;

        @media (min-width: 1024px) {
            width: 200px;
            margin-top: 2.5rem;
        }
    }

    & .pd-side-panel {
        ${tw`relative rounded-lg border border-neutral-700 bg-neutral-800 p-1 lg:border-0 lg:bg-transparent lg:p-0`};
    }

    & .pd-side-header {
        ${tw`hidden lg:block mb-3 pb-3 border-b border-neutral-700`};
    }

    & .pd-side-toggle {
        ${tw`flex lg:hidden items-center w-full gap-3 px-3 py-2 rounded-md text-sm text-neutral-50 focus:outline-none`};

        & > svg:first-child {
            ${tw`w-3.5 h-3.5 text-neutral-400`};
        }

        & > span {
            ${tw`flex-1 text-left truncate`};
        }

        & > svg:last-child {
            ${tw`w-3 h-3 text-neutral-400 transition-transform duration-150`};
        }

        &.open > svg:last-child {
            transform: rotate(180deg);
        }
    }

    & .pd-side-list {
        ${tw`hidden lg:block`};

        &.open {
            ${tw`block mt-1 pt-1 border-t border-neutral-700 lg:mt-0 lg:pt-0 lg:border-0`};
        }

        & > div {
            ${tw`flex flex-col gap-px text-sm`};

            & > a,
            & > div {
                ${tw`flex items-center gap-2.5 px-2.5 py-1.5 rounded-md text-neutral-300 no-underline whitespace-nowrap transition-colors duration-150`};

                & > span:not(.pd-tile) {
                    ${tw`truncate`};
                }

                &:hover {
                    ${tw`text-neutral-50 bg-neutral-800`};
                }

                &.active {
                    ${tw`text-neutral-50`};
                    background: linear-gradient(
                        90deg,
                        rgb(var(--pd-accent-500) / 0.22),
                        rgb(var(--pd-neon-2) / 0.06) 70%,
                        transparent
                    );
                    box-shadow: inset 2px 0 0 rgb(var(--pd-accent-400)),
                        -4px 0 14px -4px rgb(var(--pd-accent-500) / 0.8);

                    & > .pd-tile {
                        color: rgb(var(--pd-accent-400));
                    }
                }
            }

            & > a[target='_blank'] {
                ${tw`mt-2 pt-2.5 text-neutral-400`};
                border-top: 1px solid #1f1f1f;
                border-radius: 0 0 0.375rem 0.375rem;
            }
        }

        /* The icon of a tab, in the colour of the text. */
        & .pd-tile {
            ${tw`flex flex-shrink-0 items-center justify-center w-4 h-4 text-neutral-500`};

            & > svg {
                width: 0.875rem;
                height: 0.875rem;
            }
        }
    }

    & .pd-side-content {
        ${tw`lg:flex-1 min-w-0`};

        & ${ContentContainer} {
            ${tw`px-0`};
            max-width: none;
        }
    }
`;

// The icon of a tab. The colour is kept for other themes; this one draws every icon in the colour of the text.
// eslint-disable-next-line @typescript-eslint/no-unused-vars
export const TabIcon = ({ icon, color }: { icon: IconDefinition; color?: string }) => (
    <span className={'pd-tile'} aria-hidden>
        <FontAwesomeIcon icon={icon} />
    </span>
);

interface Props {
    // The tab links (NavLinks inside one <div>).
    tabs: React.ReactNode;
    // Shown above the tabs on a wide screen: what the tabs belong to (the name of the server).
    header?: React.ReactNode;
    // The page shown next to the tabs.
    children?: React.ReactNode;
    className?: string;
}

const SubNavigation = ({ tabs, header, children, className }: Props) => {
    const list = useRef<HTMLDivElement>(null);
    const { pathname } = useLocation();
    const [open, setOpen] = useState(false);
    const [current, setCurrent] = useState('');

    // The phone button shows the name of the open tab. The tabs can appear a little after the page (their permissions
    // are checked first, then they are translated), so it is read again a few times.
    useEffect(() => {
        setOpen(false);
        const read = () => setCurrent((list.current?.querySelector('a.active')?.textContent || '').trim());
        const timers = [0, 150, 500, 1200].map((ms) => window.setTimeout(read, ms));

        return () => timers.forEach((t) => window.clearTimeout(t));
    }, [pathname]);

    return (
        <Layout className={className}>
            <div className={'pd-side-inner'}>
                <nav className={'pd-side'}>
                    <div className={'pd-side-panel'}>
                        {header && <div className={'pd-side-header'}>{header}</div>}
                        <button
                            type={'button'}
                            className={`pd-side-toggle${open ? ' open' : ''}`}
                            onClick={() => setOpen((v) => !v)}
                            aria-expanded={open}
                        >
                            <FontAwesomeIcon icon={faBars} />
                            <span>{current || 'Menu'}</span>
                            <FontAwesomeIcon icon={faChevronDown} />
                        </button>
                        <div ref={list} className={`pd-side-list${open ? ' open' : ''}`}>
                            {tabs}
                        </div>
                    </div>
                </nav>
                <div className={'pd-side-content'}>{children}</div>
            </div>
        </Layout>
    );
};

export default SubNavigation;
