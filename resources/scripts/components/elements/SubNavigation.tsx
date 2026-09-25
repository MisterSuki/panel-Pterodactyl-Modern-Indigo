import styled from 'styled-components/macro';
import tw from 'twin.macro';

const SubNavigation = styled.div`
    ${tw`w-full bg-neutral-900/90 border-b border-white/5 overflow-x-auto`};

    & > div {
        ${tw`flex items-center gap-1 text-sm mx-auto px-2 py-2`};
        max-width: 1200px;

        & > a,
        & > div {
            ${tw`inline-flex items-center py-1.5 px-3 rounded-lg text-neutral-400 no-underline whitespace-nowrap transition-all duration-150`};

            &:hover {
                ${tw`text-neutral-100 bg-white/5`};
            }

            &:active,
            &.active {
                ${tw`text-primary-300 bg-primary-500/10`};
            }
        }
    }
`;

export default SubNavigation;
