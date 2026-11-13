import React from 'react';
import styled from 'styled-components/macro';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';

interface Props {
    // "nav" is the small one of the top bar, "hero" the big one of the login and registration pages.
    size?: 'nav' | 'hero';
    className?: string;
}

// The name of the panel: Geist, semibold and tight, in plain white.
const Title = styled.span<{ $size: 'nav' | 'hero' }>`
    display: inline-block;
    font-family: Geist, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, system-ui, sans-serif;
    font-weight: 600;
    font-size: ${({ $size }) => ($size === 'hero' ? '1.75rem' : '0.9375rem')};
    line-height: 1.2;
    letter-spacing: ${({ $size }) => ($size === 'hero' ? '-0.04em' : '-0.01em')};
    max-width: 100%;
    white-space: ${({ $size }) => ($size === 'nav' ? 'nowrap' : 'normal')};
    text-align: center;
    user-select: none;
    color: #ededed;
`;

export default ({ size = 'nav', className }: Props) => {
    const name = useStoreState((state: ApplicationStore) => state.settings.data?.name);

    if (!name) {
        return null;
    }

    return (
        <Title $size={size} className={className}>
            {name}
        </Title>
    );
};
