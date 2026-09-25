import React from 'react';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import CSSTransition, { CSSTransitionProps } from 'react-transition-group/CSSTransition';

interface Props extends Omit<CSSTransitionProps, 'timeout' | 'classNames'> {
    timeout: number;
}

const Container = styled.div<{ timeout: number }>`
    .fade-enter,
    .fade-exit,
    .fade-appear {
        will-change: opacity, transform;
    }

    .fade-enter,
    .fade-appear {
        ${tw`opacity-0`};
        transform: translate3d(0, 6px, 0);

        &.fade-enter-active,
        &.fade-appear-active {
            ${tw`opacity-100`};
            transform: translate3d(0, 0, 0);
            transition: opacity ${(props) => props.timeout}ms ease-out, transform ${(props) => props.timeout}ms ease-out;
        }
    }

    .fade-exit {
        ${tw`opacity-100`};

        &.fade-exit-active {
            ${tw`opacity-0 transition-opacity ease-in`};
            transition-duration: ${(props) => props.timeout}ms;
        }
    }
`;

const Fade: React.FC<Props> = ({ timeout, children, ...props }) => (
    <Container timeout={timeout}>
        <CSSTransition timeout={timeout} classNames={'fade'} {...props}>
            {children}
        </CSSTransition>
    </Container>
);
Fade.displayName = 'Fade';

export default Fade;
