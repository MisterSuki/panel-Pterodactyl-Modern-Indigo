import * as React from 'react';
import tw, { TwStyle } from 'twin.macro';
import styled from 'styled-components/macro';

export type FlashMessageType = 'success' | 'info' | 'warning' | 'error';

interface Props {
    title?: string;
    children: string;
    type?: FlashMessageType;
}

const styling = (type?: FlashMessageType): TwStyle | string => {
    switch (type) {
        case 'error':
            return tw`bg-red-500/10 border-red-500/30 text-red-200`;
        case 'info':
            return tw`bg-primary-500/10 border-primary-500/30 text-primary-200`;
        case 'success':
            return tw`bg-green-500/10 border-green-500/30 text-green-200`;
        case 'warning':
            return tw`bg-yellow-500/10 border-yellow-500/30 text-yellow-200`;
        default:
            return '';
    }
};

const getBackground = (type?: FlashMessageType): TwStyle | string => {
    switch (type) {
        case 'error':
            return tw`bg-red-500 text-red-50`;
        case 'info':
            return tw`bg-primary-500 text-primary-50`;
        case 'success':
            return tw`bg-green-500 text-green-50`;
        case 'warning':
            return tw`bg-yellow-500 text-yellow-50`;
        default:
            return '';
    }
};

const Container = styled.div<{ $type?: FlashMessageType }>`
    ${tw`p-3 border items-center leading-normal rounded-xl shadow-card flex w-full text-sm`};
    ${(props) => styling(props.$type)};
`;
Container.displayName = 'MessageBox.Container';

const MessageBox = ({ title, children, type }: Props) => (
    <Container css={tw`lg:inline-flex`} $type={type} role={'alert'}>
        {title && (
            <span
                className={'title'}
                css={[tw`flex rounded-full px-2 py-1 text-2xs font-semibold mr-3 leading-none`, getBackground(type)]}
            >
                {title}
            </span>
        )}
        <span css={tw`mr-2 text-left flex-auto`}>{children}</span>
    </Container>
);
MessageBox.displayName = 'MessageBox';

export default MessageBox;
