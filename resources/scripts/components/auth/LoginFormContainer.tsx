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

// Coloured shapes sitting behind the card. Seen through the frosted glass they turn soft, which is what makes the
// transparency visible. Static gradients only (no blur filter, no animation), so the page stays light.
const Orb = styled.span<{ $color: string; $size: number }>`
    ${tw`absolute rounded-full pointer-events-none`};
    width: ${(p) => p.$size}px;
    height: ${(p) => p.$size}px;
    background: radial-gradient(circle, ${(p) => p.$color} 0%, ${(p) => p.$color} 52%, transparent 72%);
`;

// The card: clear Liquid Glass. Its fields are glass too, lighter than the dark wells used elsewhere.
const GlassCard = styled.div`
    ${tw`relative z-10 w-full rounded-3xl p-6 sm:p-8`};

    & input:not([type='checkbox']):not([type='radio']) {
        background: rgba(255, 255, 255, 0.08) !important;
        border-color: rgba(255, 255, 255, 0.16) !important;
        border-radius: 14px !important;
    }

    & input:not([type='checkbox']):not([type='radio']):focus {
        background: rgba(255, 255, 255, 0.12) !important;
        border-color: rgba(10, 132, 255, 0.7) !important;
        box-shadow: 0 0 0 3px rgba(10, 132, 255, 0.25) !important;
    }
`;

export default forwardRef<HTMLFormElement, Props>(({ title, ...props }, ref) => (
    <div css={tw`min-h-screen w-full flex items-center justify-center py-12 overflow-x-hidden`}>
        <Container>
            <div css={tw`flex flex-col items-center mb-8 select-none`}>
                <BrandTitle size={'hero'} />
            </div>
            {title && <h2 css={tw`text-2xl text-center text-neutral-50 font-semibold mb-6 tracking-tight`}>{title}</h2>}
            <FlashMessageRender css={tw`mb-4`} />
            <Form {...props} ref={ref}>
                <div css={tw`relative`}>
                    <Orb $color={'rgba(10, 132, 255, 0.85)'} $size={230} style={{ top: -70, left: -90 }} />
                    <Orb $color={'rgba(191, 90, 242, 0.75)'} $size={190} style={{ top: '38%', right: -100 }} />
                    <Orb $color={'rgba(255, 55, 95, 0.7)'} $size={240} style={{ bottom: -150, left: -90 }} />
                    <GlassCard className={'lg-glass'}>{props.children}</GlassCard>
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
