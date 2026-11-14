import * as React from 'react';
import { Fragment, useEffect, useState } from 'react';
import { Link, NavLink, useLocation } from 'react-router-dom';
import { Menu, Transition } from '@headlessui/react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faChevronDown,
    faCode,
    faDesktop,
    faExternalLinkAlt,
    faHistory,
    faKey,
    faPalette,
    faServer,
    faShieldAlt,
    faSignal,
    faSignOutAlt,
    faStore,
    faUser,
} from '@fortawesome/free-solid-svg-icons';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import SearchContainer from '@/components/dashboard/search/SearchContainer';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import http from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import Avatar from '@/components/Avatar';
import BrandTitle from '@/components/elements/BrandTitle';
import NotificationBell from '@/components/NotificationBell';
import SupportBubble from '@/components/tickets/SupportBubble';
import ScreenShare from '@/components/screen/ScreenShare';
import BrandMark from '@/components/elements/BrandMark';

const Bar = styled.header`
    ${tw`sticky top-0 z-40 w-full`};
    background: rgba(10, 10, 10, 0.72);
    border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    -webkit-backdrop-filter: saturate(160%) blur(14px);
    backdrop-filter: saturate(160%) blur(14px);

    & .pd-links {
        ${tw`items-center gap-0.5`};

        &.pd-links-wide {
            ${tw`hidden md:flex p-1 rounded-lg`};
            background: rgba(255, 255, 255, 0.025);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        &.pd-links-phone {
            ${tw`flex md:hidden overflow-x-auto`};
            scrollbar-width: none;
        }

        & > a {
            ${tw`relative flex flex-shrink-0 items-center gap-2 h-7 px-3 rounded-md text-sm font-medium no-underline text-neutral-400 transition-colors duration-150`};

            & > svg {
                ${tw`w-3.5 h-3.5 text-neutral-500 transition-colors duration-150`};
            }

            & > .pd-ext {
                ${tw`w-2.5 h-2.5 -ml-0.5 opacity-60`};
            }

            &:hover {
                ${tw`text-neutral-100`};
                background: rgba(255, 255, 255, 0.05);

                & > svg {
                    ${tw`text-neutral-300`};
                }
            }

            &.active {
                ${tw`text-neutral-50`};
                background: rgba(255, 255, 255, 0.09);
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06), 0 1px 2px rgba(0, 0, 0, 0.4);

                & > svg {
                    color: rgb(var(--pd-accent-400));
                }
            }
        }
    }

    & .pd-divider {
        ${tw`hidden sm:block w-px h-5 mx-1.5`};
        background: rgba(255, 255, 255, 0.1);
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

const AccountMenu = ({ adminAccess, onLogout }: { adminAccess: boolean; onLogout: () => void }) => {
    const username = useStoreState((state: ApplicationStore) => state.user.data?.username || '');
    const email = useStoreState((state: ApplicationStore) => state.user.data?.email || '');
    const item = (active: boolean, danger?: boolean) =>
        [
            'flex items-center gap-3 w-full px-2.5 py-2 rounded-md text-sm no-underline text-left transition-colors duration-100',
            danger
                ? active
                    ? 'bg-red-500 text-white'
                    : 'text-red-400'
                : active
                ? 'bg-neutral-700 text-neutral-50'
                : 'text-neutral-300',
        ].join(' ');
    const links = [
        { to: '/account', icon: faUser, label: 'My account', exact: true },
        { to: '/account/appearance', icon: faPalette, label: 'Appearance' },
        { to: '/account/api', icon: faCode, label: 'API Credentials' },
        { to: '/account/ssh', icon: faKey, label: 'SSH Keys' },
        { to: '/account/activity', icon: faHistory, label: 'Activity' },
    ];

    return (
        <Menu as={'div'} className={'relative'}>
            {({ open }) => (
                <>
                    <Menu.Button
                        className={[
                            'flex items-center gap-2 h-8 pl-1 pr-2 rounded-full border transition-colors duration-150',
                            open
                                ? 'border-neutral-500 bg-neutral-700'
                                : 'border-neutral-700 hover:border-neutral-600 hover:bg-neutral-800',
                        ].join(' ')}
                        aria-label={'Account Settings'}
                    >
                        <span className={'flex items-center w-6 h-6 rounded-full overflow-hidden'}>
                            <Avatar.User />
                        </span>
                        <span className={'hidden lg:block max-w-[8rem] truncate text-sm font-medium text-neutral-200'}>
                            {username}
                        </span>
                        <FontAwesomeIcon
                            icon={faChevronDown}
                            className={[
                                'w-2.5 h-2.5 text-neutral-500 transition-transform duration-150',
                                open ? 'rotate-180' : '',
                            ].join(' ')}
                        />
                    </Menu.Button>
                    <Transition
                        as={Fragment}
                        enter={'transition duration-100 ease-out'}
                        enterFrom={'opacity-0 -translate-y-1'}
                        enterTo={'opacity-100 translate-y-0'}
                        leave={'transition duration-75 ease-in'}
                        leaveFrom={'opacity-100'}
                        leaveTo={'opacity-0'}
                    >
                        <Menu.Items
                            className={
                                'absolute right-0 mt-2 w-64 origin-top-right rounded-xl border border-neutral-700 bg-neutral-800 p-1.5 shadow-xl focus:outline-none z-50'
                            }
                            style={{ background: 'rgb(23 23 23 / 0.98)', boxShadow: '0 16px 40px rgba(0, 0, 0, 0.55)' }}
                        >
                            <div
                                className={'flex items-center gap-3 px-2.5 pt-2 pb-3 mb-1 border-b border-neutral-700'}
                            >
                                <span className={'flex-shrink-0 w-9 h-9 rounded-full overflow-hidden'}>
                                    <Avatar.User />
                                </span>
                                <div className={'min-w-0'}>
                                    <p className={'text-sm font-semibold text-neutral-50 truncate'}>{username}</p>
                                    <p className={'text-xs text-neutral-400 truncate'}>{email}</p>
                                </div>
                            </div>
                            {links.map((link) => (
                                <Menu.Item key={link.to}>
                                    {({ active }) => (
                                        <Link to={link.to} className={item(active)}>
                                            <FontAwesomeIcon
                                                icon={link.icon}
                                                className={'w-3.5 h-3.5 text-neutral-500'}
                                            />
                                            <span>{link.label}</span>
                                        </Link>
                                    )}
                                </Menu.Item>
                            ))}
                            {adminAccess && (
                                <Menu.Item>
                                    {({ active }) => (
                                        <a href={'/admin'} className={item(active)}>
                                            <FontAwesomeIcon
                                                icon={faShieldAlt}
                                                className={'w-3.5 h-3.5 text-neutral-500'}
                                            />
                                            <span>Administration</span>
                                        </a>
                                    )}
                                </Menu.Item>
                            )}
                            <div className={'my-1 border-t border-neutral-700'} />
                            <Menu.Item>
                                {({ active }) => (
                                    <button type={'button'} onClick={onLogout} className={item(active, true)}>
                                        <FontAwesomeIcon icon={faSignOutAlt} className={'w-3.5 h-3.5'} />
                                        <span>Sign Out</span>
                                    </button>
                                )}
                            </Menu.Item>
                        </Menu.Items>
                    </Transition>
                </>
            )}
        </Menu>
    );
};

export default () => {
    const adminAccess = useStoreState((state: ApplicationStore) => state.user.data!.adminAccess);
    const shopOpen = useStoreState((state: ApplicationStore) => !!state.settings.data?.shop?.enabled);
    const vpsCount = useStoreState((state: ApplicationStore) => state.user.data?.vpsCount || 0);
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

    // The servers page also stays active on the page of a server.
    const links = (
        <>
            <NavLink to={'/'} exact isActive={() => pathname === '/' || pathname.startsWith('/server/')}>
                <FontAwesomeIcon icon={faServer} />
                <span>Servers</span>
            </NavLink>
            {vpsCount > 0 && (
                <NavLink to={'/vps'}>
                    <FontAwesomeIcon icon={faDesktop} />
                    <span>VPS</span>
                </NavLink>
            )}
            {shopOpen && (
                <NavLink to={'/shop'}>
                    <FontAwesomeIcon icon={faStore} />
                    <span>Shop</span>
                </NavLink>
            )}
            {statusOpen && (
                <a href={'/status'} target={'_blank'} rel={'noopener noreferrer'}>
                    <FontAwesomeIcon icon={faSignal} />
                    <span>Status</span>
                    <FontAwesomeIcon icon={faExternalLinkAlt} className={'pd-ext'} />
                </a>
            )}
            {adminAccess && (
                <a href={'/admin'} rel={'noreferrer'}>
                    <FontAwesomeIcon icon={faShieldAlt} />
                    <span>Admin</span>
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
                            <BrandMark className={'w-6 h-6 rounded-md'} />
                            <BrandTitle className={'truncate'} />
                        </Link>
                    </div>
                    <nav className={'pd-links pd-links-wide'}>{links}</nav>
                    <div className={'hidden md:block flex-1'} />
                    <RightNavigation className={'flex h-14 flex-shrink-0 items-center justify-center gap-0.5 sm:gap-1'}>
                        <SearchContainer />
                        <NotificationBell />
                        <span className={'pd-divider'} />
                        <AccountMenu adminAccess={adminAccess} onLogout={onTriggerLogout} />
                    </RightNavigation>
                </div>
                {/* On a phone, the links get their own row under the name and the tools. */}
                <nav className={'pd-links pd-links-phone px-2 sm:px-3.5 pb-2'}>{links}</nav>
            </Bar>
        </>
    );
};
