import * as React from 'react';
import { useState } from 'react';
import { Link, NavLink } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCogs, faLayerGroup, faSignOutAlt } from '@fortawesome/free-solid-svg-icons';
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

const RightNavigation = styled.div`
    & > a,
    & > button,
    & > .navigation-link {
        ${tw`relative flex items-center justify-center h-9 w-9 mx-1 rounded-lg no-underline text-neutral-400 cursor-pointer transition-all duration-150`};

        &:active,
        &:hover {
            ${tw`text-neutral-50 bg-neutral-700/60`};
        }

        &:active,
        &:hover,
        &.active {
            ${tw`text-primary-400`};
        }

        &.active {
            ${tw`bg-primary-500/10`};
        }
    }
`;

export default () => {
    const adminAccess = useStoreState((state: ApplicationStore) => state.user.data!.adminAccess);
    const [isLoggingOut, setIsLoggingOut] = useState(false);

    const onTriggerLogout = () => {
        setIsLoggingOut(true);
        http.post('/auth/logout').finally(() => {
            // @ts-expect-error this is valid
            window.location = '/';
        });
    };

    return (
        <div
            className={'sticky top-0 z-40 w-full bg-neutral-900/95 border-b border-white/5 shadow-nav overflow-x-auto'}
        >
            <SpinnerOverlay visible={isLoggingOut} />
            <div className={'mx-auto w-full flex items-center h-14 max-w-[1200px] px-2 sm:px-4'}>
                <div id={'logo'} className={'flex-1'}>
                    <Link
                        to={'/'}
                        className={
                            'inline-flex items-center px-2 no-underline hover:brightness-110 transition-[filter] duration-150'
                        }
                    >
                        <BrandTitle />
                    </Link>
                </div>
                <RightNavigation className={'flex h-full items-center justify-center'}>
                    <SearchContainer />
                    <Tooltip placement={'bottom'} content={'Dashboard'}>
                        <NavLink to={'/'} exact>
                            <FontAwesomeIcon icon={faLayerGroup} />
                        </NavLink>
                    </Tooltip>
                    {adminAccess && (
                        <Tooltip placement={'bottom'} content={'Admin'}>
                            <a href={'/admin'} rel={'noreferrer'}>
                                <FontAwesomeIcon icon={faCogs} />
                            </a>
                        </Tooltip>
                    )}
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
        </div>
    );
};
