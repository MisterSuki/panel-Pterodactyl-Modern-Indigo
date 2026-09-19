import React, { useEffect, useMemo, useRef, useState } from 'react';
import { ITerminalOptions, Terminal } from 'xterm';
import { FitAddon } from 'xterm-addon-fit';
import { SearchAddon } from 'xterm-addon-search';
import { SearchBarAddon } from 'xterm-addon-search-bar';
import { WebLinksAddon } from 'xterm-addon-web-links';
import { Unicode11Addon } from 'xterm-addon-unicode11';
import { ScrollDownHelperAddon } from '@/plugins/XtermScrollDownHelperAddon';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import { ServerContext } from '@/state/server';
import { usePermissions } from '@/plugins/usePermissions';
import { theme as th } from 'twin.macro';
import useEventListener from '@/plugins/useEventListener';
import { debounce } from 'debounce';
import { usePersistedState } from '@/plugins/usePersistedState';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import classNames from 'classnames';
import { ChevronDoubleRightIcon, PauseIcon, PlayIcon, TrashIcon } from '@heroicons/react/solid';
import StatusPill from '@/components/server/console/StatusPill';

import 'xterm/css/xterm.css';
import styles from './style.module.css';

const theme = {
    background: th`colors.black`.toString(),
    cursor: 'transparent',
    black: th`colors.black`.toString(),
    red: '#E54B4B',
    green: '#9ECE58',
    yellow: '#FAED70',
    blue: '#396FE2',
    magenta: '#BB80B3',
    cyan: '#2DDAFD',
    white: '#d0d0d0',
    brightBlack: 'rgba(255, 255, 255, 0.2)',
    brightRed: '#FF5370',
    brightGreen: '#C3E88D',
    brightYellow: '#FFCB6B',
    brightBlue: '#82AAFF',
    brightMagenta: '#C792EA',
    brightCyan: '#89DDFF',
    brightWhite: '#ffffff',
    selection: '#FAF089',
};

// The most lines kept aside while the console is paused.
const MAX_HELD_LINES = 5000;

const terminalProps: ITerminalOptions = {
    disableStdin: true,
    cursorStyle: 'underline',
    allowTransparency: true,
    fontSize: 12,
    scrollback: 1000,
    fontFamily: th('fontFamily.mono'),
    rows: 30,
    theme: theme,
};

export default () => {
    const TERMINAL_PRELUDE = '\u001b[1m\u001b[33mcontainer@pterodactyl~ \u001b[0m';
    const ref = useRef<HTMLDivElement>(null);
    const terminal = useMemo(() => new Terminal({ ...terminalProps }), []);
    const fitAddon = new FitAddon();
    const searchAddon = new SearchAddon();
    const searchBar = new SearchBarAddon({ searchAddon });
    const webLinksAddon = new WebLinksAddon();
    const unicode11Addon = new Unicode11Addon();
    const scrollDownHelperAddon = new ScrollDownHelperAddon();
    const { connected, instance } = ServerContext.useStoreState((state) => state.socket);
    const [canSendCommands] = usePermissions(['control.console']);
    const serverId = ServerContext.useStoreState((state) => state.server.data!.id);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data!.isTransferring);
    const [history, setHistory] = usePersistedState<string[]>(`${serverId}:command_history`, []);
    const [historyIndex, setHistoryIndex] = useState(-1);
    // SearchBarAddon has hardcoded z-index: 999 :(
    const zIndex = `
    .xterm-search-bar__addon {
        z-index: 10;
    }`;

    const status = ServerContext.useStoreState((state) => state.status.value);

    // A busy server can print dozens of lines a second, and writing them one by one makes the browser
    // lay the terminal out again for each line. They are collected and written once per screen refresh.
    const pending = useRef<string[]>([]);
    const frame = useRef<number | null>(null);

    const flush = () => {
        frame.current = null;
        if (pending.current.length > 0) {
            terminal.write(pending.current.join(''));
            pending.current = [];
        }
    };

    // While the console is paused, new lines are kept aside instead of being written, so the screen stays
    // still and can be read or copied. They are all written when the console is resumed.
    const [paused, setPaused] = useState(false);
    const [heldCount, setHeldCount] = useState(0);
    const pausedRef = useRef(false);
    const held = useRef<string[]>([]);
    const heldFrame = useRef<number | null>(null);

    const writeln = (line: string) => {
        if (pausedRef.current) {
            held.current.push(line + '\r\n');
            // A console left paused for hours must not fill the memory of the browser.
            if (held.current.length > MAX_HELD_LINES + 1000) {
                held.current.splice(0, held.current.length - MAX_HELD_LINES);
            }
            if (heldFrame.current === null) {
                heldFrame.current = window.requestAnimationFrame(() => {
                    heldFrame.current = null;
                    setHeldCount(held.current.length);
                });
            }

            return;
        }

        pending.current.push(line + '\r\n');
        if (frame.current === null) {
            frame.current = window.requestAnimationFrame(flush);
        }
    };

    const togglePause = () => {
        if (!pausedRef.current) {
            pausedRef.current = true;
            setPaused(true);

            return;
        }

        pausedRef.current = false;
        setPaused(false);
        setHeldCount(0);

        const lines = pending.current.concat(held.current);
        pending.current = [];
        held.current = [];
        if (lines.length > 0) {
            terminal.write(lines.join(''), () => terminal.scrollToBottom());
        } else {
            terminal.scrollToBottom();
        }
    };

    // Empties the console: the screen, the history above it, and anything that was waiting to be written.
    const clearConsole = () => {
        pending.current = [];
        held.current = [];
        setHeldCount(0);
        terminal.clear();
        terminal.write('\u001b[2J\u001b[3J\u001b[H');
    };

    useEffect(
        () => () => {
            if (frame.current !== null) {
                window.cancelAnimationFrame(frame.current);
            }
            if (heldFrame.current !== null) {
                window.cancelAnimationFrame(heldFrame.current);
            }
        },
        []
    );

    const handleConsoleOutput = (line: string, prelude = false) =>
        writeln((prelude ? TERMINAL_PRELUDE : '') + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m');

    const handleTransferStatus = (status: string) => {
        switch (status) {
            // Sent by either the source or target node if a failure occurs.
            case 'failure':
                writeln(TERMINAL_PRELUDE + 'Transfer has failed.\u001b[0m');
                return;
        }
    };

    const handleDaemonErrorOutput = (line: string) =>
        writeln(TERMINAL_PRELUDE + '\u001b[1m\u001b[41m' + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m');

    const handlePowerChangeEvent = (state: string) =>
        writeln(TERMINAL_PRELUDE + 'Server marked as ' + state + '...\u001b[0m');

    const handleCommandKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'ArrowUp') {
            const newIndex = Math.min(historyIndex + 1, history!.length - 1);

            setHistoryIndex(newIndex);
            e.currentTarget.value = history![newIndex] || '';

            // By default up arrow will also bring the cursor to the start of the line,
            // so we'll preventDefault to keep it at the end.
            e.preventDefault();
        }

        if (e.key === 'ArrowDown') {
            const newIndex = Math.max(historyIndex - 1, -1);

            setHistoryIndex(newIndex);
            e.currentTarget.value = history![newIndex] || '';
        }

        const command = e.currentTarget.value;
        if (e.key === 'Enter' && command.length > 0) {
            setHistory((prevHistory) => [command, ...prevHistory!].slice(0, 32));
            setHistoryIndex(-1);

            instance && instance.send('send command', command);
            e.currentTarget.value = '';
        }
    };

    useEffect(() => {
        if (connected && ref.current && !terminal.element) {
            terminal.loadAddon(fitAddon);
            terminal.loadAddon(searchAddon);
            terminal.loadAddon(searchBar);
            terminal.loadAddon(webLinksAddon);
            terminal.loadAddon(unicode11Addon);
            terminal.loadAddon(scrollDownHelperAddon);

            terminal.open(ref.current);

            // Activate Unicode 11 for proper emoji and special character width handling
            terminal.unicode.activeVersion = '11';

            fitAddon.fit();
            searchBar.addNewStyle(zIndex);

            // Add support for capturing keys
            terminal.attachCustomKeyEventHandler((e: KeyboardEvent) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
                    document.execCommand('copy');
                    return false;
                } else if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    searchBar.show();
                    return false;
                } else if (e.key === 'Escape') {
                    searchBar.hidden();
                }
                return true;
            });
        }
    }, [terminal, connected]);

    useEventListener(
        'resize',
        debounce(() => {
            if (terminal.element) {
                fitAddon.fit();
            }
        }, 100)
    );

    useEffect(() => {
        const listeners: Record<string, (s: string) => void> = {
            [SocketEvent.STATUS]: handlePowerChangeEvent,
            [SocketEvent.CONSOLE_OUTPUT]: handleConsoleOutput,
            [SocketEvent.INSTALL_OUTPUT]: handleConsoleOutput,
            [SocketEvent.TRANSFER_LOGS]: handleConsoleOutput,
            [SocketEvent.TRANSFER_STATUS]: handleTransferStatus,
            [SocketEvent.DAEMON_MESSAGE]: (line) => handleConsoleOutput(line, true),
            [SocketEvent.DAEMON_ERROR]: handleDaemonErrorOutput,
        };

        if (connected && instance) {
            // Do not clear the console if the server is being transferred.
            if (!isTransferring) {
                terminal.clear();
            }

            Object.keys(listeners).forEach((key: string) => {
                instance.addListener(key, listeners[key]);
            });
            instance.send(SocketRequest.SEND_LOGS);
        }

        return () => {
            if (instance) {
                Object.keys(listeners).forEach((key: string) => {
                    instance.removeListener(key, listeners[key]);
                });
            }
        };
    }, [connected, instance]);

    return (
        <div className={classNames(styles.terminal, 'relative')}>
            <SpinnerOverlay visible={!connected} size={'large'} />
            <div className={classNames(styles.header, styles.overflows_container)}>
                <span className={'flex items-center gap-3'}>
                    <span className={'flex items-center gap-1.5'} aria-hidden>
                        <span className={'w-2.5 h-2.5 rounded-full bg-red-400/70'} />
                        <span className={'w-2.5 h-2.5 rounded-full bg-yellow-300/70'} />
                        <span className={'w-2.5 h-2.5 rounded-full bg-green-400/70'} />
                    </span>
                    <span className={'text-xs font-medium text-gray-400 select-none'}>Console</span>
                </span>
                <span className={'flex items-center gap-1.5'}>
                    <button
                        type={'button'}
                        onClick={togglePause}
                        title={
                            paused
                                ? 'Show the new lines and follow the console again'
                                : 'Freeze the console so it can be read'
                        }
                        className={classNames(
                            'inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium transition-colors duration-150',
                            paused
                                ? 'bg-yellow-400/15 text-yellow-300 hover:bg-yellow-400/25'
                                : 'text-gray-300 hover:text-white hover:bg-white/10'
                        )}
                    >
                        {paused ? <PlayIcon className={'w-3.5 h-3.5'} /> : <PauseIcon className={'w-3.5 h-3.5'} />}
                        <span className={'hidden sm:inline'}>{paused ? 'Resume' : 'Pause'}</span>
                        {paused && heldCount > 0 && (
                            <span className={'rounded-full bg-yellow-400/20 px-1.5 text-2xs tabular-nums'}>
                                {heldCount >= MAX_HELD_LINES ? `${MAX_HELD_LINES}+` : heldCount} new
                            </span>
                        )}
                    </button>
                    <button
                        type={'button'}
                        onClick={clearConsole}
                        title={'Empty the console'}
                        className={
                            'inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium text-gray-300 hover:text-white hover:bg-white/10 transition-colors duration-150'
                        }
                    >
                        <TrashIcon className={'w-3.5 h-3.5'} />
                        <span className={'hidden sm:inline'}>Clear</span>
                    </button>
                    <StatusPill status={status} className={'ml-1'} />
                </span>
            </div>
            <div
                className={classNames(styles.container, styles.overflows_container, { 'rounded-b': !canSendCommands })}
            >
                <div className={'h-full'}>
                    <div id={styles.terminal} ref={ref} />
                </div>
            </div>
            {canSendCommands && (
                <div className={classNames('relative', styles.overflows_container)}>
                    <input
                        className={classNames('peer', styles.command_input)}
                        type={'text'}
                        placeholder={'Type a command...'}
                        aria-label={'Console command input.'}
                        disabled={!instance || !connected}
                        onKeyDown={handleCommandKeyDown}
                        autoCorrect={'off'}
                        autoCapitalize={'none'}
                    />
                    <div
                        className={classNames(
                            'text-gray-100 peer-focus:text-gray-50 peer-focus:animate-pulse',
                            styles.command_icon
                        )}
                    >
                        <ChevronDoubleRightIcon className={'w-4 h-4'} />
                    </div>
                </div>
            )}
        </div>
    );
};
