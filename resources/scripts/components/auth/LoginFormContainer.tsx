import React, { forwardRef } from 'react';
import LanguageLinks from '@/components/auth/LanguageLinks';
import { Form } from 'formik';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import FlashMessageRender from '@/components/FlashMessageRender';
import CopyrightLine from '@/components/elements/CopyrightLine';
import BrandTitle from '@/components/elements/BrandTitle';

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
};

// A narrow column in the middle of a plain page: the mark and name of the panel, the title, the form. No card.
const Column = styled.div`
    ${tw`relative w-full mx-auto px-6`};
    max-width: 24rem;

    & label {
        ${tw`normal-case tracking-normal text-sm text-neutral-300 font-medium`};
    }
`;

export default forwardRef<HTMLFormElement, Props>(({ title, ...props }, ref) => {
    const name = useStoreState((state: ApplicationStore) => state.settings.data?.name || '');

    return (
        <div css={tw`min-h-screen w-full flex flex-col`}>
            <div css={tw`flex-1 flex items-center justify-center py-16`}>
                <Column>
                    <div css={tw`flex flex-col items-center mb-8 select-none`}>
                        <span aria-hidden className={'pd-mark'} css={tw`w-10 h-10 rounded-lg mb-5`} />
                        {title ? (
                            <h1 css={tw`text-2xl text-center text-neutral-50 font-semibold`}>{title}</h1>
                        ) : (
                            <BrandTitle size={'hero'} />
                        )}
                        {title && <p css={tw`mt-2 text-sm text-neutral-400`}>{name}</p>}
                    </div>
                    <FlashMessageRender css={tw`mb-4`} />
                    <Form {...props} ref={ref}>
                        <div css={tw`w-full`}>{props.children}</div>
                    </Form>
                </Column>
            </div>
            <div css={tw`pb-6 px-6 text-center`}>
                <p css={tw`text-xs text-neutral-500`}>
                    <CopyrightLine
                        fallback={
                            <>
                                {name}&nbsp;&copy; {new Date().getFullYear()}
                            </>
                        }
                    />
                </p>
                <LanguageLinks />
            </div>
        </div>
    );
});
