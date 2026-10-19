import React from 'react';
import styled from 'styled-components/macro';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';

interface Props {
    // "nav" is the small one of the top bar, "hero" the big one of the login and registration pages.
    size?: 'nav' | 'hero';
    className?: string;
}

// The name of the panel, iOS style: the system font in heavy weight, white fading to a light blue, with a soft glow.
const Title = styled.span<{ $size: 'nav' | 'hero' }>`
    display: inline-block;
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI Variable Display', 'Segoe UI', Roboto,
        system-ui, sans-serif;
    font-weight: ${({ $size }) => ($size === 'hero' ? 800 : 700)};
    font-size: ${({ $size }) => ($size === 'hero' ? '2.75rem' : '1.25rem')};
    line-height: 1.15;
    letter-spacing: ${({ $size }) => ($size === 'hero' ? '-0.035em' : '-0.02em')};
    max-width: 100%;
    white-space: ${({ $size }) => ($size === 'nav' ? 'nowrap' : 'normal')};
    text-align: center;
    user-select: none;
    background-image: linear-gradient(180deg, #ffffff 0%, #ffffff 35%, #b9dcff 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
    filter: ${({ $size }) =>
        $size === 'hero' ? 'drop-shadow(0 6px 24px rgba(10, 132, 255, 0.45))' : 'drop-shadow(0 2px 8px rgba(10, 132, 255, 0.35))'};
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
