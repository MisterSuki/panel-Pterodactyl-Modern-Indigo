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
    ${tw`relative w-full mx-auto px-4`};
    max-width: 27rem;

    ${breakpoint('sm')`
        max-width: 29rem;
    `};
`;

// A soft light behind the card, fading out with no visible edge, so the glass has something to diffuse. Painted once
// (no blur filter, no animation).
const Glow = styled.div`
    ${tw`absolute pointer-events-none`};
    inset: -18% -45%;
    background: radial-gradient(38% 34% at 30% 32%, rgb(var(--pd-accent-500) / 0.34) 0%, rgb(var(--pd-accent-500) / 0) 100%),
        radial-gradient(34% 32% at 72% 58%, rgba(191, 90, 242, 0.26) 0%, rgba(191, 90, 242, 0) 100%),
        radial-gradient(30% 26% at 42% 86%, rgba(255, 55, 95, 0.16) 0%, rgba(255, 55, 95, 0) 100%);
`;

// The card: thick Liquid Glass with a bright specular rim on the top edge, like an iOS sheet.
const GlassCard = styled.div`
    ${tw`relative w-full rounded-3xl p-6 sm:p-8 overflow-hidden`};
    /* A dark base under the light tint, so the texts stay readable over a bright background (animated or picture). */
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0.04) 100%), rgba(12, 12, 20, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.16);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4), inset 0 -1px 0 rgba(255, 255, 255, 0.05),
        0 30px 70px -24px rgba(0, 0, 0, 0.75);
    -webkit-backdrop-filter: saturate(180%) blur(30px);
    backdrop-filter: saturate(180%) blur(30px);

    /* The light sheen across the top of the glass. */
    &::before {
        content: '';
        ${tw`absolute inset-x-0 top-0 pointer-events-none`};
        height: 45%;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0) 100%);
    }

    & > * {
        ${tw`relative`};
    }

    & input:not([type='checkbox']):not([type='radio']) {
        background: rgba(0, 0, 0, 0.22) !important;
        border-color: rgba(255, 255, 255, 0.12) !important;
        border-radius: 14px !important;
    }

    & input:not([type='checkbox']):not([type='radio']):focus {
        background: rgba(0, 0, 0, 0.3) !important;
        border-color: rgb(var(--pd-accent-500) / 0.8) !important;
        box-shadow: 0 0 0 3px rgb(var(--pd-accent-500) / 0.25) !important;
    }
`;

export default forwardRef<HTMLFormElement, Props>(({ title, ...props }, ref) => (
    <div css={tw`min-h-screen w-full flex items-center justify-center py-12 overflow-hidden`}>
        <Container>
            <Glow />
            <div css={tw`relative flex flex-col items-center mb-6 select-none`}>
                <BrandTitle size={'hero'} />
            </div>
            {title && (
                <h2 css={tw`relative text-xl text-center text-neutral-200 font-medium mb-6 tracking-tight`}>{title}</h2>
            )}
            <FlashMessageRender css={tw`relative mb-4`} />
            <Form {...props} ref={ref}>
                <GlassCard>{props.children}</GlassCard>
            </Form>
            <p css={tw`relative text-center text-neutral-300 text-xs mt-6`} style={{ textShadow: '0 1px 8px rgba(0, 0, 0, 0.8)' }}>
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
            <div css={tw`relative`} style={{ textShadow: '0 1px 8px rgba(0, 0, 0, 0.8)' }}>
                <LanguageLinks />
            </div>
        </Container>
    </div>
));
