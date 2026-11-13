import styled from 'styled-components/macro';
import tw from 'twin.macro';

// The width of the pages, the same as the top bar. Next to the side menu of a server, the menu sets the margins.
const ContentContainer = styled.div`
    max-width: 1280px;
    ${tw`mx-auto px-4 sm:px-6`};
`;
ContentContainer.displayName = 'ContentContainer';

export default ContentContainer;
