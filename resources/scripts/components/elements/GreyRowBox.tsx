import styled from 'styled-components/macro';
import tw from 'twin.macro';

export default styled.div.attrs({ className: 'lg-rim' })<{ $hoverable?: boolean }>`
    ${tw`relative flex rounded-2xl no-underline text-neutral-300 items-center bg-neutral-800 p-4 border border-neutral-700 transition-colors duration-150 overflow-hidden`};

    ${(props) => props.$hoverable !== false && tw`hover:border-neutral-500`};

    & .icon {
        ${tw`rounded-lg w-10 h-10 flex items-center justify-center bg-neutral-900 border border-neutral-600 text-neutral-300 p-2.5`};
    }
`;
