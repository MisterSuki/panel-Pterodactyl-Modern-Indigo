import * as React from 'react';
import { useEffect, useState } from 'react';
import { Link, NavLink, useLocation } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faSignOutAlt } from '@fortawesome/free-solid-svg-icons';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import SearchContainer from '@/components/dashboard/search/SearchContainer';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import http from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import Avatar from '@/components/Avatar';
import BrandTitle from '@/components/elements/BrandTitle';
import NotificationBell from '@/components/NotificationBell';
import SupportBubble from '@/components/tickets/SupportBubble';
import ScreenShare from '@/components/screen/ScreenShare';

const Bar = styled.header`
    ${tw`sticky top-0 z-40 w-full`};
    background: rgba(10, 10, 10, 0.8);
    border-bottom: 1px solid #1f1f1f;
    -webkit-backdrop-filter: saturate(150%) blur(12px);
    backdrop-filter: saturate(150%) blur(12px);

    & .pd-links {
        ${tw`items-center gap-0.5`};

        &.pd-links-wide {
            ${tw`hidden md:flex`};
        }

        &.pd-links-phone {
            ${tw`flex md:hidden`};
        }

        & > a {
            ${tw`relative px-2.5 py-1.5 rounded-md text-sm no-underline text-neutral-300 transition-colors duration-150`};

            &:hover {
                ${tw`text-neutral-50 bg-neutral-700`};
            }

            &.active {
                ${tw`text-neutral-50`};

                &::after {
                    content: '';
                    position: absolute;
                    left: 0.625rem;
                    right: 0.625rem;
                    bottom: -0.625rem;
                    height: 2px;
                    border-radius: 2px;
                    background: rgb(var(--pd-accent-500));
                }
            }
        }

        &.pd-links-phone > a.active::after {
            bottom: -0.5rem;
        }
    }

    & .pd-search {
        ${tw`flex items-center gap-2 h-8 rounded-md text-sm text-neutral-500 transition-colors duration-150`};
        ${tw`w-8 justify-center md:w-56 md:justify-start md:px-2.5 md:border md:border-neutral-600 md:bg-neutral-900`};

        &:hover {
            ${tw`text-neutral-300 md:border-neutral-500`};
        }

        & > svg {
            ${tw`w-3.5 h-3.5 flex-shrink-0`};
        }
    }

    & .pd-search-label {
        ${tw`hidden md:inline`};
    }

    & .pd-search-key {
        ${tw`hidden md:inline ml-auto rounded border border-neutral-600 px-1 text-2xs text-neutral-500`};
    }
`;

const RightNavigation = styled.div`
    & > a,
    & > button:not(.pd-search),
    & > .navigation-link {
        ${tw`relative flex flex-shrink-0 items-center justify-center h-8 w-8 rounded-md no-underline text-neutral-300 cursor-pointer transition-colors duration-150`};

        &:hover {
            ${tw`text-neutral-50 bg-neutral-700`};
        }

        &.active {
            ${tw`text-neutral-50 bg-neutral-700`};
        }
    }
`;

export default () => {
    const adminAccess = useStoreState((state: ApplicationStore) => state.user.data!.adminAccess);
    const shopOpen = useStoreState((state: ApplicationStore) => !!state.settings.data?.shop?.enabled);
    const statusOpen = useStoreState((state: ApplicationStore) => !!state.settings.data?.status?.enabled);
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    const { pathname } = useLocation();

    // Tells the panel which page this is, now and every half minute while the page is on screen, so the
    // administration can see who is on the panel and where. Only the address of the page is sent.
    useEffect(() => {
        const beat = () => {
            if (!document.hidden) {
                http.post('/api/client/presence', { path: pathname }).catch(() => undefined);
            }
        };
        beat();
        const timer = window.setInterval(beat, 30000);
        document.addEventListener('visibilitychange', beat);

        return () => {
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', beat);
        };
    }, [pathname]);

    const onTriggerLogout = () => {
        setIsLoggingOut(true);
        http.post('/auth/logout').finally(() => {
            // @ts-expect-error this is valid
            window.location = '/';
        });
    };

    const links = (
        <>
            <NavLink to={'/'} exact>
                Servers
            </NavLink>
            {shopOpen && <NavLink to={'/shop'}>Shop</NavLink>}
            {statusOpen && (
                <a href={'/status'} target={'_blank'} rel={'noopener noreferrer'}>
                    Status
                </a>
            )}
            {adminAccess && (
                <a href={'/admin'} rel={'noreferrer'}>
                    Admin
                </a>
            )}
        </>
    );

    return (
        <>
            <SupportBubble />
            <ScreenShare />
            <Bar>
                <SpinnerOverlay visible={isLoggingOut} />
                <div
                    className={
                        'relative mx-auto w-full flex items-center gap-x-3 md:gap-x-6 h-14 max-w-[1280px] px-4 sm:px-6'
                    }
                >
                    <div id={'logo'} className={'min-w-0 flex-1 md:flex-none'}>
                        <Link to={'/'} className={'flex items-center gap-2.5 min-w-0 no-underline'}>
                            <span aria-hidden className={'pd-mark w-6 h-6 rounded-md flex-shrink-0'} />
                            <BrandTitle className={'truncate'} />
                        </Link>
                    </div>
                    <nav className={'pd-links pd-links-wide'}>{links}</nav>
                    <div className={'hidden md:block flex-1'} />
                    <RightNavigation className={'flex h-14 flex-shrink-0 items-center justify-center gap-0.5 sm:gap-1'}>
                        <SearchContainer />
                        <NotificationBell />
                        <Tooltip placement={'bottom'} content={'Account Settings'}>
                            <NavLink to={'/account'}>
                                <span className={'flex items-center w-5 h-5'}>
                                    <Avatar.User />
                                </span>
                            </NavLink>
                        </Tooltip>
                        <Tooltip placement={'bottom'} content={'Sign Out'}>
                            <button onClick={onTriggerLogout}>
                                <FontAwesomeIcon icon={faSignOutAlt} />
                            </button>
                        </Tooltip>
                    </RightNavigation>
                </div>
                {/* On a phone, the links get their own row under the name and the tools. */}
                <nav className={'pd-links pd-links-phone px-2 sm:px-3.5 pb-2'}>{links}</nav>
            </Bar>
        </>
    );
};
