import React, { createRef } from 'react';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import Fade from '@/components/elements/Fade';
import Portal from '@/components/elements/Portal';

interface Props {
    children: React.ReactNode;
    renderToggle: (onClick: (e: React.MouseEvent<any, MouseEvent>) => void) => React.ReactChild;
}

export const DropdownButtonRow = styled.button<{ danger?: boolean }>`
    ${tw`p-2 flex items-center rounded-lg w-full text-neutral-300`};
    transition: 150ms all ease;

    &:hover {
        ${(props) => (props.danger ? tw`text-red-400 bg-red-500/10` : tw`text-neutral-100 bg-white/5`)};
    }
`;

interface State {
    posX: number;
    posY: number;
    visible: boolean;
}

// The space kept between the menu and the edges of the window, and between the menu and the cursor.
const MARGIN = 8;
const GAP = 6;

class DropdownMenu extends React.PureComponent<Props, State> {
    menu = createRef<HTMLDivElement>();

    state: State = {
        posX: 0,
        posY: 0,
        visible: false,
    };

    componentWillUnmount() {
        this.removeListeners();
    }

    componentDidUpdate(prevProps: Readonly<Props>, prevState: Readonly<State>) {
        const menu = this.menu.current;

        if (this.state.visible && !prevState.visible && menu) {
            document.addEventListener('click', this.windowListener);
            document.addEventListener('contextmenu', this.contextMenuListener);
            // The menu is fixed in the window: it scrolls away from what it belongs to, so it closes instead.
            window.addEventListener('scroll', this.contextMenuListener, true);
            window.addEventListener('resize', this.contextMenuListener);
            this.place(menu);
        }

        if (!this.state.visible && prevState.visible) {
            this.removeListeners();
        }
    }

    // Under the cursor, or above it when there is no room below, and always entirely in the window.
    place = (menu: HTMLDivElement) => {
        const { posX, posY } = this.state;
        const { offsetWidth: width, offsetHeight: height } = menu;

        const below = posY + GAP;
        const top = below + height + MARGIN <= window.innerHeight ? below : posY - GAP - height;
        const left = posX - width;

        menu.style.top = `${Math.round(Math.max(MARGIN, Math.min(top, window.innerHeight - height - MARGIN)))}px`;
        menu.style.left = `${Math.round(Math.max(MARGIN, Math.min(left, window.innerWidth - width - MARGIN)))}px`;
    };

    removeListeners = () => {
        document.removeEventListener('click', this.windowListener);
        document.removeEventListener('contextmenu', this.contextMenuListener);
        window.removeEventListener('scroll', this.contextMenuListener, true);
        window.removeEventListener('resize', this.contextMenuListener);
    };

    onClickHandler = (e: React.MouseEvent<any, MouseEvent>) => {
        e.preventDefault();
        this.triggerMenu(e.clientX, e.clientY);
    };

    contextMenuListener = () => this.setState({ visible: false });

    windowListener = (e: MouseEvent) => {
        const menu = this.menu.current;

        if (e.button === 2 || !this.state.visible || !menu) {
            return;
        }

        if (e.target === menu || menu.contains(e.target as Node)) {
            return;
        }

        if (e.target !== menu && !menu.contains(e.target as Node)) {
            this.setState({ visible: false });
        }
    };

    triggerMenu = (posX: number, posY: number) =>
        this.setState((s) => ({
            posX: !s.visible ? posX : s.posX,
            posY: !s.visible ? posY : s.posY,
            visible: !s.visible,
        }));

    render() {
        return (
            <div>
                {this.props.renderToggle(this.onClickHandler)}
                {/* Outside the page, so a list that hides what overflows (overflow: hidden) cannot cut it. */}
                <Portal>
                    <Fade timeout={150} in={this.state.visible} unmountOnExit>
                        <div
                            ref={this.menu}
                            onClick={(e) => {
                                e.stopPropagation();
                                this.setState({ visible: false });
                            }}
                            style={{ width: '12rem' }}
                            className={'lg-glass-thick'}
                            css={tw`fixed p-2 rounded-xl text-neutral-300 z-50`}
                        >
                            {this.props.children}
                        </div>
                    </Fade>
                </Portal>
            </div>
        );
    }
}

export default DropdownMenu;
