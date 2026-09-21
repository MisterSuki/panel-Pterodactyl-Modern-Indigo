import React from 'react';
import styled from 'styled-components/macro';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';

interface Props {
    // "nav" is the small one of the top bar, "hero" the big one of the login and registration pages.
    size?: 'nav' | 'hero';
    className?: string;
}

// The name of the panel as a raised, three dimensional title: a bright gradient on the face, and the sides of the
// letters built from a few dark indigo layers stacked one under the other, with a soft shadow on the ground.
const Title = styled.span<{ $size: 'nav' | 'hero' }>`
    display: inline-block;
    font-family: 'IBM Plex Sans', 'Roboto', system-ui, sans-serif;
    font-weight: ${({ $size }) => ($size === 'hero' ? 800 : 700)};
    font-size: ${({ $size }) => ($size === 'hero' ? '3rem' : '1.25rem')};
    line-height: 1.15;
    letter-spacing: ${({ $size }) => ($size === 'hero' ? '-0.02em' : '-0.01em')};
    text-align: center;
    user-select: none;
    background-image: linear-gradient(180deg, #c7d2fe 0%, #818cf8 38%, #a78bfa 68%, #22d3ee 125%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
    transition: filter 200ms ease, transform 200ms ease;
    filter: ${({ $size }) =>
        $size === 'hero'
            ? 'drop-shadow(0 1px 0 #4f46e5) drop-shadow(0 2px 0 #4338ca) drop-shadow(0 3px 0 #3730a3) drop-shadow(0 4px 0 #312e81) drop-shadow(0 5px 0 #272566) drop-shadow(0 14px 14px rgba(0, 0, 0, 0.55)) drop-shadow(0 0 26px rgba(99, 102, 241, 0.4))'
            : 'drop-shadow(0 1px 0 #4f46e5) drop-shadow(0 2px 0 #3730a3) drop-shadow(0 5px 5px rgba(0, 0, 0, 0.5))'};
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
