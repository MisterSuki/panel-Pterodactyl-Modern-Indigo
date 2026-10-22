import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useLocation } from 'react-router-dom';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons';

// The tabs, as a floating Liquid Glass capsule with an iOS segmented-control look: the active tab is a lighter glass
// pill. On a computer the tabs wrap onto a second line when they do not all fit; on a phone the capsule scrolls
// sideways instead, with arrows and a soft fade to show that there is more.
const Bar = styled.div`
    ${tw`w-full px-2 sm:px-4 pt-3`};

    & > .pd-tabs {
        ${tw`relative mx-auto rounded-full`};
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

    /* On a computer every tab stays visible: they go on a second line instead of scrolling. */
    @media (min-width: 768px) {
        & > .pd-tabs,
        & .pd-tabs-scroll {
            border-radius: 22px;
        }

        & .pd-tabs-scroll,
        & .pd-tabs-scroll.fade-left,
        & .pd-tabs-scroll.fade-right {
            ${tw`overflow-visible`};
            -webkit-mask-image: none;
            mask-image: none;
        }

        & .pd-tabs-scroll > div {
            ${tw`flex-wrap justify-center w-full`};
        }

        & .pd-tabs-arrow {
            ${tw`hidden`};
        }
    }
`;

const SubNavigation = ({ children, className }: { children?: React.ReactNode; className?: string }) => {
    const scroller = useRef<HTMLDivElement>(null);
    const { pathname } = useLocation();
    const [edges, setEdges] = useState({ left: false, right: false });

    const measure = useCallback(() => {
        const el = scroller.current;
        if (!el) return;
        setEdges({ left: el.scrollLeft > 4, right: el.scrollLeft + el.clientWidth < el.scrollWidth - 4 });
    }, []);

    useEffect(() => {
        const el = scroller.current;
        if (!el) return;
        measure();
        el.addEventListener('scroll', measure, { passive: true });
        window.addEventListener('resize', measure);

        // A mouse wheel moves the tabs sideways while the pointer is on them.
        const wheel = (e: WheelEvent) => {
            if (el.scrollWidth <= el.clientWidth || Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;
            e.preventDefault();
            el.scrollLeft += e.deltaY;
        };
        el.addEventListener('wheel', wheel, { passive: false });

        return () => {
            el.removeEventListener('scroll', measure);
            window.removeEventListener('resize', measure);
            el.removeEventListener('wheel', wheel);
        };
    }, [measure]);

    // The open tab is brought into view (in the middle) when the page changes. The tabs can appear a little after the
    // page (their permissions are checked first), so it is tried again a few times while they load.
    useEffect(() => {
        const bring = () => {
            const el = scroller.current;
            const active = el?.querySelector<HTMLElement>('a.active');
            if (el && active) {
                el.style.scrollBehavior = 'auto';
                el.scrollLeft = active.offsetLeft - el.clientWidth / 2 + active.offsetWidth / 2;
                el.style.scrollBehavior = '';
            }
            measure();
        };
        const timers = [0, 150, 500, 1200].map((ms) => window.setTimeout(bring, ms));

        return () => timers.forEach((t) => window.clearTimeout(t));
    }, [pathname, measure]);

    const move = (direction: 1 | -1) => {
        const el = scroller.current;
        el && el.scrollBy({ left: direction * el.clientWidth * 0.6, behavior: 'smooth' });
    };

    return (
        <Bar className={className}>
            <div className={'pd-tabs'}>
                {edges.left && (
                    <button type={'button'} className={'pd-tabs-arrow'} style={{ left: 6 }} onClick={() => move(-1)} aria-label={'Previous tabs'}>
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
                    <button type={'button'} className={'pd-tabs-arrow'} style={{ right: 6 }} onClick={() => move(1)} aria-label={'Next tabs'}>
                        <FontAwesomeIcon icon={faChevronRight} />
                    </button>
                )}
            </div>
        </Bar>
    );
};

export default SubNavigation;
