import styled from 'styled-components/macro';
import tw from 'twin.macro';

export default styled.div<{ $hoverable?: boolean }>`
    ${tw`flex rounded-xl no-underline text-neutral-200 items-center bg-neutral-800 p-4 border border-white/5 shadow-card transition-all duration-200 overflow-hidden`};

    ${(props) =>
        props.$hoverable !== false &&
        tw`hover:border-primary-500/40 hover:shadow-card-hover hover:-translate-y-0.5`};

    & .icon {
        ${tw`rounded-lg w-11 h-11 flex items-center justify-center bg-gradient-brand-flat text-white p-3 shadow-glow`};
    }
`;
