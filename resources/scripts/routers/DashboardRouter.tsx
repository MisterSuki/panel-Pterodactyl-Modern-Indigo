import React from 'react';
import { NavLink, Route, Switch } from 'react-router-dom';
import NavigationBar from '@/components/NavigationBar';
import DashboardContainer from '@/components/dashboard/DashboardContainer';
import TicketsContainer from '@/components/tickets/TicketsContainer';
import TicketView from '@/components/tickets/TicketView';
import ShopContainer from '@/components/shop/ShopContainer';
import { NotFound } from '@/components/elements/ScreenBlock';
import TransitionRouter from '@/TransitionRouter';
import SubNavigation from '@/components/elements/SubNavigation';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { accountTabIcons } from '@/routers/tabIcons';
import { useLocation } from 'react-router';
import Spinner from '@/components/elements/Spinner';
import routes from '@/routers/routes';

export default () => {
    const location = useLocation();

    const pages = (
        <TransitionRouter>
            <React.Suspense fallback={<Spinner centered />}>
                <Switch location={location}>
                    <Route path={'/'} exact>
                        <DashboardContainer />
                    </Route>
                    <Route path={'/shop'} exact>
                        <ShopContainer />
                    </Route>
                    <Route path={'/tickets'} exact>
                        <TicketsContainer />
                    </Route>
                    <Route path={'/tickets/:id'} exact>
                        <TicketView />
                    </Route>
                    {routes.account.map(({ path, component: Component }) => (
                        <Route key={path} path={`/account/${path}`.replace('//', '/')} exact>
                            <Component />
                        </Route>
                    ))}
                    <Route path={'*'}>
                        <NotFound />
                    </Route>
                </Switch>
            </React.Suspense>
        </TransitionRouter>
    );

    return (
        <>
            <NavigationBar />
            {location.pathname.startsWith('/account') ? (
                <SubNavigation
                    tabs={
                        <div>
                            {routes.account
                                .filter((route) => !!route.name)
                                .map(({ path, name, exact = false }) => (
                                    <NavLink key={path} to={`/account/${path}`.replace('//', '/')} exact={exact}>
                                        {accountTabIcons[path] && <FontAwesomeIcon icon={accountTabIcons[path]} />}
                                        <span>{name}</span>
                                    </NavLink>
                                ))}
                        </div>
                    }
                >
                    {pages}
                </SubNavigation>
            ) : (
                pages
            )}
        </>
    );
};
