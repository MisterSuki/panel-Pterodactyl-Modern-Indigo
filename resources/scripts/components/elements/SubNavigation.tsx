import styled from 'styled-components/macro';
import tw from 'twin.macro';

// The server tabs, as a floating Liquid Glass capsule with an iOS segmented-control look: the active tab is a lighter
// glass pill. It is not sticky, so its blur never has to be redrawn while the page scrolls.
const SubNavigation = styled.div`
    ${tw`w-full px-2 sm:px-4 pt-3`};

    & > div {
        ${tw`flex items-center gap-1 text-sm mx-auto p-1.5 rounded-full overflow-x-auto`};
        max-width: 1200px;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0.05) 100%);
        border: 1px solid rgba(255, 255, 255, 0.16);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.35), 0 10px 30px -12px rgba(0, 0, 0, 0.5);
        -webkit-backdrop-filter: saturate(180%) blur(20px);
        backdrop-filter: saturate(180%) blur(20px);
        scrollbar-width: none;

        &::-webkit-scrollbar {
            display: none;
        }

        & > a,
        & > div {
            ${tw`inline-flex items-center py-1.5 px-4 rounded-full text-neutral-300 no-underline whitespace-nowrap transition-all duration-150`};

            &:hover {
                ${tw`text-white bg-white/10`};
            }

            &:active,
            &.active {
                ${tw`text-white`};
                background: rgba(255, 255, 255, 0.18);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.35), 0 2px 8px -2px rgba(0, 0, 0, 0.4);
            }
        }
    }
`;

export default SubNavigation;
