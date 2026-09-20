import React, { forwardRef } from 'react';
import LanguageLinks from '@/components/auth/LanguageLinks';
import { Form } from 'formik';
import styled from 'styled-components/macro';
import { breakpoint } from '@/theme';
import FlashMessageRender from '@/components/FlashMessageRender';
import tw from 'twin.macro';
import CopyrightLine from '@/components/elements/CopyrightLine';
import BrandTitle from '@/components/elements/BrandTitle';

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
};

const Container = styled.div`
    ${tw`w-full mx-auto px-4`};
    max-width: 26rem;

    ${breakpoint('sm')`
        max-width: 28rem;
    `};
`;

export default forwardRef<HTMLFormElement, Props>(({ title, ...props }, ref) => (
    <div css={tw`min-h-screen w-full flex items-center justify-center py-12`}>
        <Container>
            <div css={tw`flex flex-col items-center mb-8 select-none`}>
                <BrandTitle size={'hero'} />
            </div>
            {title && <h2 css={tw`text-2xl text-center text-neutral-50 font-semibold mb-6 tracking-tight`}>{title}</h2>}
            <FlashMessageRender css={tw`mb-4`} />
            <Form {...props} ref={ref}>
                <div
                    css={tw`w-full bg-neutral-800/70 backdrop-blur-xl border border-white/10 shadow-card rounded-2xl p-6 sm:p-8`}
                >
                    {props.children}
                </div>
            </Form>
            <p css={tw`text-center text-neutral-500 text-xs mt-6`}>
                <CopyrightLine
                    fallback={
                        <>
                            &copy; 2015 - {new Date().getFullYear()}&nbsp;
                            <a
                                rel={'noopener nofollow noreferrer'}
                                href={'https://pterodactyl.io'}
                                target={'_blank'}
                                css={tw`no-underline text-neutral-500 hover:text-neutral-300`}
                            >
                                Pterodactyl Software
                            </a>
                        </>
                    }
                />
            </p>
            <LanguageLinks />
        </Container>
    </div>
));
