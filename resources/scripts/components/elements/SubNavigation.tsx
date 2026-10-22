import React, { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { useHistory, useLocation } from 'react-router-dom';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faChevronDown, faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons';

// The tabs, as a floating Liquid Glass capsule with an iOS segmented-control look: the active tab is a lighter glass
// pill. On a computer they stay on one line: the tabs that do not fit go into a "More" menu at the end (the open tab
// always stays visible). On a phone the capsule scrolls sideways instead, with arrows and a soft fade.
const MORE_WIDTH = 96;

const Bar = styled.div`
    ${tw`w-full px-2 sm:px-4 pt-3`};

    & > .pd-tabs {
        ${tw`relative mx-auto rounded-full z-20`};
        max-width: 1200px;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0.05) 100%);
        border: 1px solid rgba(255, 255, 255, 0.16);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.35), 0 10px 30px -12px rgba(0, 0, 0, 0.5);
        -webkit-backdrop-filter: saturate(180%) blur(20px);
        backdrop-filter: saturate(180%) blur(20px);
    }

    & .pd-tabs-scroll {
        ${tw`overflow-x-auto rounded-full`};
        scrollbar-width: none;
        scroll-behavior: smooth;

        &::-webkit-scrollbar {
            display: none;
        }
    }

    & .pd-tabs-scroll.fade-left {
        -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 48px);
        mask-image: linear-gradient(90deg, transparent 0, #000 48px);
    }

    & .pd-tabs-scroll.fade-right {
        -webkit-mask-image: linear-gradient(270deg, transparent 0, #000 48px);
        mask-image: linear-gradient(270deg, transparent 0, #000 48px);
    }

    & .pd-tabs-scroll.fade-left.fade-right {
        -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 48px, #000 calc(100% - 48px), transparent 100%);
        mask-image: linear-gradient(90deg, transparent 0, #000 48px, #000 calc(100% - 48px), transparent 100%);
    }

    & .pd-tabs-scroll > div {
        ${tw`flex items-center gap-1 text-sm p-1.5 w-max min-w-full`};

        & > a,
        & > div {
            ${tw`inline-flex items-center gap-2 py-1.5 px-3.5 rounded-full text-neutral-300 no-underline whitespace-nowrap transition-all duration-150`};

            & > svg {
                ${tw`w-3.5 h-3.5 opacity-70`};
            }

            &:hover {
                ${tw`text-white bg-white/10`};
            }

            &:active,
            &.active {
                ${tw`text-white`};
                background: rgba(255, 255, 255, 0.18);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.35), 0 2px 8px -2px rgba(0, 0, 0, 0.4);

                & > svg {
                    ${tw`opacity-100`};
                    color: rgb(var(--pd-accent-300));
                }
            }
        }

        & > .pd-tab-hidden {
            display: none !important;
        }
    }

    & .pd-tabs-arrow {
        ${tw`absolute top-1/2 z-10 flex items-center justify-center w-8 h-8 rounded-full text-white text-xs focus:outline-none`};
        transform: translateY(-50%);
        background: rgba(40, 40, 50, 0.9);
        border: 1px solid rgba(255, 255, 255, 0.18);
        box-shadow: 0 4px 14px -4px rgba(0, 0, 0, 0.6);

        &:hover {
            background: rgba(60, 60, 72, 0.95);
        }
    }

    & .pd-tabs-more {
        ${tw`absolute top-1/2 right-1.5`};
        transform: translateY(-50%);

        & > button {
            ${tw`inline-flex items-center gap-2 py-1.5 px-3.5 rounded-full text-sm text-neutral-300 whitespace-nowrap transition-all duration-150 focus:outline-none`};

            & > svg {
                ${tw`w-2.5 h-2.5 transition-transform duration-150`};
            }

            &:hover,
            &.open {
                ${tw`text-white bg-white/10`};
            }

            &.open > svg {
                transform: rotate(180deg);
            }

            &.has-active {
                ${tw`text-white`};
                background: rgba(255, 255, 255, 0.18);
            }
        }
    }

    & .pd-tabs-menu {
        ${tw`absolute right-0 mt-3 py-1.5 rounded-2xl text-sm`};
        min-width: 210px;
        background: rgba(28, 28, 36, 0.97);
        border: 1px solid rgba(255, 255, 255, 0.14);
        box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.7);

        & > a {
            ${tw`flex items-center gap-3 mx-1.5 px-3 py-2 rounded-xl text-neutral-300 no-underline cursor-pointer`};

            & > span:first-child {
                ${tw`inline-flex w-4 justify-center opacity-70`};

                & > svg {
                    ${tw`w-3.5 h-3.5`};
                }
            }

            &:hover {
                ${tw`text-white bg-white/10`};
            }
        }
    }

    @media (min-width: 768px) {
        & .pd-tabs-scroll,
        & .pd-tabs-scroll.fade-left,
        & .pd-tabs-scroll.fade-right {
            -webkit-mask-image: none;
            mask-image: none;
        }

        & .pd-tabs-arrow {
            ${tw`hidden`};
        }
    }
`;

interface HiddenTab {
    href: string;
    label: string;
    icon: string;
    external: boolean;
    active: boolean;
}

const isWide = () => window.matchMedia('(min-width: 768px)').matches;

const SubNavigation = ({ children, className }: { children?: React.ReactNode; className?: string }) => {
    const scroller = useRef<HTMLDivElement>(null);
    const more = useRef<HTMLDivElement>(null);
    const history = useHistory();
    const { pathname } = useLocation();
    const [edges, setEdges] = useState({ left: false, right: false });
    const [hidden, setHidden] = useState<HiddenTab[]>([]);
    const [open, setOpen] = useState(false);

    const measure = useCallback(() => {
        const el = scroller.current;
        if (!el) return;
        setEdges({ left: el.scrollLeft > 4, right: el.scrollLeft + el.clientWidth < el.scrollWidth - 4 });
    }, []);

    // On a computer, keeps the tabs that fit on the line and puts the others in the "More" menu.
    const fit = useCallback(() => {
        const el = scroller.current;
        const row = el?.firstElementChild as HTMLElement | null;
        if (!el || !row) return;

        const items = Array.from(row.children) as HTMLElement[];
        items.forEach((item) => item.classList.remove('pd-tab-hidden'));

        let keep = items.map(() => true);
        if (isWide()) {
            const widths = items.map((item) => item.offsetWidth + 4);
            const room = el.clientWidth - 12;
            if (widths.reduce((a, b) => a + b, 0) > room) {
                const space = room - MORE_WIDTH;
                let used = 0;
                keep = widths.map((w) => {
                    if (used + w > space) {
                        used = Infinity;
                        return false;
                    }
                    used += w;
                    return true;
                });

                const active = items.findIndex((item) => item.classList.contains('active'));
                if (active >= 0 && !keep[active]) {
                    keep[active] = true;
                    used = widths.reduce((sum, w, i) => sum + (keep[i] ? w : 0), 0);
                    for (let i = active - 1; i >= 0 && used > space; i--) {
                        if (keep[i]) {
                            keep[i] = false;
                            used -= widths[i];
                        }
                    }
                }
            }
        }

        items.forEach((item, i) => !keep[i] && item.classList.add('pd-tab-hidden'));
        const next = items
            .filter((_, i) => !keep[i])
            .map((item) => ({
                href: item.getAttribute('href') || '#',
                label: (item.textContent || '').trim() || item.getAttribute('title') || '',
                icon: item.querySelector('svg')?.outerHTML || '',
                external: item.getAttribute('target') === '_blank',
                active: item.classList.contains('active'),
            }));
        setHidden((current) => (JSON.stringify(current) === JSON.stringify(next) ? current : next));
        measure();
    }, [measure]);

    useEffect(() => {
        const el = scroller.current;
        if (!el) return;
        measure();
        el.addEventListener('scroll', measure, { passive: true });

        // The tabs are checked again when the window changes size, and when they change (their permissions are loaded
        // after the page, and the translation changes their width).
        let frame = 0;
        const refit = () => {
            window.cancelAnimationFrame(frame);
            frame = window.requestAnimationFrame(fit);
        };
        window.addEventListener('resize', refit);
        const observer = new MutationObserver(refit);
        el.firstElementChild &&
            observer.observe(el.firstElementChild, { childList: true, subtree: true, characterData: true });

        // A mouse wheel moves the tabs sideways while the pointer is on them.
        const wheel = (e: WheelEvent) => {
            if (el.scrollWidth <= el.clientWidth || Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;
            e.preventDefault();
            el.scrollLeft += e.deltaY;
        };
        el.addEventListener('wheel', wheel, { passive: false });

        return () => {
            window.cancelAnimationFrame(frame);
            observer.disconnect();
            el.removeEventListener('scroll', measure);
            window.removeEventListener('resize', refit);
            el.removeEventListener('wheel', wheel);
        };
    }, [measure, fit]);

    useLayoutEffect(() => {
        setOpen(false);
        fit();
    }, [pathname, fit]);

    // On a phone the open tab is brought into view (in the middle) when the page changes. The tabs can appear a little
    // after the page (their permissions are checked first), so it is tried again a few times while they load.
    useEffect(() => {
        const bring = () => {
            const el = scroller.current;
            const active = el?.querySelector<HTMLElement>('a.active');
            if (el && active && !isWide()) {
                el.style.scrollBehavior = 'auto';
                el.scrollLeft = active.offsetLeft - el.clientWidth / 2 + active.offsetWidth / 2;
                el.style.scrollBehavior = '';
            }
            measure();
        };
        const timers = [0, 150, 500, 1200].map((ms) => window.setTimeout(bring, ms));

        return () => timers.forEach((t) => window.clearTimeout(t));
    }, [pathname, measure]);

    useEffect(() => {
        if (!open) return;
        const close = (e: MouseEvent) => {
            if (more.current && !more.current.contains(e.target as Node)) setOpen(false);
        };
        const escape = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', escape);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', escape);
        };
    }, [open]);

    const move = (direction: 1 | -1) => {
        const el = scroller.current;
        el && el.scrollBy({ left: direction * el.clientWidth * 0.6, behavior: 'smooth' });
    };

    const go = (e: React.MouseEvent, tab: HiddenTab) => {
        e.preventDefault();
        setOpen(false);
        tab.external ? window.open(tab.href, '_blank') : history.push(tab.href);
    };

    return (
        <Bar className={className}>
            <div className={'pd-tabs'}>
                {edges.left && (
                    <button
                        type={'button'}
                        className={'pd-tabs-arrow'}
                        style={{ left: 6 }}
                        onClick={() => move(-1)}
                        aria-label={'Previous tabs'}
                    >
                        <FontAwesomeIcon icon={faChevronLeft} />
                    </button>
                )}
                <div
                    ref={scroller}
                    className={`pd-tabs-scroll${edges.left ? ' fade-left' : ''}${edges.right ? ' fade-right' : ''}`}
                >
                    {children}
                </div>
                {edges.right && (
                    <button
                        type={'button'}
                        className={'pd-tabs-arrow'}
                        style={{ right: 6 }}
                        onClick={() => move(1)}
                        aria-label={'Next tabs'}
                    >
                        <FontAwesomeIcon icon={faChevronRight} />
                    </button>
                )}
                {hidden.length > 0 && (
                    <div ref={more} className={'pd-tabs-more'}>
                        <button
                            type={'button'}
                            className={`${open ? 'open' : ''}${hidden.some((tab) => tab.active) ? ' has-active' : ''}`}
                            onClick={() => setOpen((v) => !v)}
                            aria-expanded={open}
                        >
                            <span>More</span>
                            <FontAwesomeIcon icon={faChevronDown} />
                        </button>
                        {open && (
                            <div className={'pd-tabs-menu'}>
                                {hidden.map((tab) => (
                                    <a key={tab.href} href={tab.href} onClick={(e) => go(e, tab)}>
                                        <span dangerouslySetInnerHTML={{ __html: tab.icon }} />
                                        <span>{tab.label}</span>
                                    </a>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </Bar>
    );
};

export default SubNavigation;
