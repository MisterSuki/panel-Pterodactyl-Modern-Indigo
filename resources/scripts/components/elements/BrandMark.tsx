import React from 'react';
import classNames from 'classnames';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';

interface Props {
    // The size and corners, as Tailwind classes ("w-6 h-6 rounded-md").
    className?: string;
}

// The logo chosen in Administration > Appearance, or, without one, the square in the accent colour.
export default ({ className }: Props) => {
    const logo = useStoreState((state: ApplicationStore) => state.settings.data?.logo || null);

    if (logo) {
        return (
            <img src={logo} alt={''} aria-hidden className={classNames('object-contain flex-shrink-0', className)} />
        );
    }

    return <span aria-hidden className={classNames('pd-mark flex-shrink-0', className)} />;
};
