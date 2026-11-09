import React, { useEffect, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faBars, faChevronDown } from '@fortawesome/free-solid-svg-icons';
import { IconDefinition } from '@fortawesome/fontawesome-svg-core';

// The tabs, in a Liquid Glass sidebar on the left of the page: every tab is visible, nothing to scroll. It stays in
// view while the page scrolls. On a phone (or a narrow window) it becomes a button showing the open tab, which opens the
// list of tabs.
const Layout = styled.div`
    ${tw`w-full lg:px-4`};

    & > .pd-side-inner {
        ${tw`mx-auto lg:flex lg:items-start lg:gap-5`};
        max-width: 1200px;
    }

    & .pd-side {
        ${tw`px-2 sm:px-4 pt-3 lg:p-0 lg:flex-shrink-0 lg:sticky`};
        top: 84px;

        @media (min-width: 1024px) {
            width: 220px;
            margin-top: 2.5rem;
        }
    }

    & .pd-side-panel {
        ${tw`relative p-2`};
        border-radius: 1.375rem;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.045) 100%);
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.24), 0 16px 40px -18px rgba(0, 0, 0, 0.6);
        -webkit-backdrop-filter: saturate(180%) blur(20px);
        backdrop-filter: saturate(180%) blur(20px);
    }

    & .pd-side-toggle {
        ${tw`flex lg:hidden items-center w-full gap-3 px-3 py-2 rounded-xl text-sm text-white focus:outline-none`};

        & > svg:first-child {
            ${tw`w-3.5 h-3.5 opacity-70`};
        }

        & > span {
            ${tw`flex-1 text-left truncate`};
        }

        & > svg:last-child {
            ${tw`w-3 h-3 opacity-70 transition-transform duration-150`};
        }

        &.open > svg:last-child {
            transform: rotate(180deg);
        }
    }

    & .pd-side-list {
        ${tw`hidden lg:block`};

        &.open {
            ${tw`block mt-1 pt-1 border-t border-white/10 lg:mt-0 lg:pt-0 lg:border-0`};
        }

        & > div {
            ${tw`flex flex-col gap-0.5`};
            font-size: 0.9375rem;

            & > a,
            & > div {
                ${tw`flex items-center gap-3 pl-1.5 pr-3 py-1.5 text-neutral-100 no-underline whitespace-nowrap transition-colors duration-150`};
                border-radius: 0.75rem;
                font-weight: 500;

                & > svg {
                    ${tw`w-4 h-3.5 flex-shrink-0 opacity-60`};
                }

                & > span:not(.pd-tile) {
                    ${tw`truncate`};
                }

                &:hover {
                    background: rgba(255, 255, 255, 0.08);
                }

                &:active {
                    background: rgba(255, 255, 255, 0.14);
                }

                &.active {
                    ${tw`text-white`};
                    background: rgb(var(--pd-accent-500));
                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.22), 0 6px 16px -8px rgb(var(--pd-accent-500) / 0.8);

                    & > .pd-tile {
                        box-shadow: 0 0 0 1.5px rgba(255, 255, 255, 0.55);
                    }
                }
            }

            & > a[target='_blank'] {
                ${tw`mt-1.5 pt-2.5 text-neutral-300`};
                border-top: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 0 0 0.75rem 0.75rem;
            }
        }

        /* The coloured square of an icon, like the ones of the Settings app. */
        & .pd-tile {
            ${tw`flex flex-shrink-0 items-center justify-center text-white`};
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 0.5rem;
            background-image: linear-gradient(180deg, rgba(255, 255, 255, 0.22) 0%, rgba(255, 255, 255, 0) 100%);
            box-shadow: inset 0 0 0 0.5px rgba(255, 255, 255, 0.18), 0 1px 2px rgba(0, 0, 0, 0.3);

            & > svg {
                width: 0.875rem;
                height: 0.875rem;
            }
        }
    }

    & .pd-side-content {
        ${tw`lg:flex-1 min-w-0`};
    }
`;

// The icon of a tab, white in a coloured square.
export const TabIcon = ({ icon, color }: { icon: IconDefinition; color?: string }) => (
    <span className={'pd-tile'} style={{ backgroundColor: color || '#8e8e93' }} aria-hidden>
        <FontAwesomeIcon icon={icon} />
    </span>
);

interface Props {
    // The tab links (NavLinks inside one <div>).
    tabs: React.ReactNode;
    // The page shown next to the tabs.
    children?: React.ReactNode;
    className?: string;
}

const SubNavigation = ({ tabs, children, className }: Props) => {
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
                    <div className={'pd-side-panel lg-rim'}>
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
