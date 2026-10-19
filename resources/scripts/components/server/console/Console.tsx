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
import {
    CheckIcon,
    ChevronDoubleRightIcon,
    ClipboardCopyIcon,
    ArrowUpIcon,
    PauseIcon,
    PlayIcon,
    SearchIcon,
    TrashIcon,
} from '@heroicons/react/solid';
import StatusPill from '@/components/server/console/StatusPill';

import 'xterm/css/xterm.css';
import styles from './style.module.css';

const theme = {
    // Transparent, so the glass of the console box shows through. Colours are the iOS system palette.
    background: 'rgba(0, 0, 0, 0)',
    foreground: '#e5e5ea',
    cursor: 'transparent',
    black: th`colors.black`.toString(),
    red: '#ff453a',
    green: '#30d158',
    yellow: '#ffd60a',
    blue: '#0a84ff',
    magenta: '#bf5af2',
    cyan: '#64d2ff',
    white: '#e5e5ea',
    brightBlack: 'rgba(255, 255, 255, 0.35)',
    brightRed: '#ff6961',
    brightGreen: '#5ee17c',
    brightYellow: '#ffe066',
    brightBlue: '#409cff',
    brightMagenta: '#da8fff',
    brightCyan: '#8ae1ff',
    brightWhite: '#ffffff',
    selection: 'rgba(10, 132, 255, 0.35)',
};

// Lines that carry no colour of their own are tinted by what they say, so a problem stands out in a long log.
// Lines that already have colours (the ones the game server or the panel colour themselves) are left as they are.
const STACK_FRAME = /^\s+at\s/;
const ERROR_LINE =
    /(error|erreur|exception|fatal|fail|échec|échou|non réussi|injoignable|unreachable|refused|denied|forbidden|not found|not allowed|no such|timed out|timeout|crash|panic)/i;
const WARNING_LINE = /\bwarn(ing)?\b|avertissement|deprecated|obsolète/i;
const SUCCESS_LINE = /\b(started|ready|success(ful(ly)?)?|connected|listening|loaded|démarré|prêt|réussi|connecté)\b/i;
// "[12:30:05]" in front of a line is a time, and "[Olympus]" or "[INFO]" is who or what is speaking.
const LEADING_TAG = /^(\[[^\]\r\n]{1,40}\])(.*)$/;
const TIME_TAG = /^\[\d{1,2}:\d{2}/;

// True colour (24-bit) escapes in the iOS palette: a soft grey for stack frames and times, system cyan for "[tags]".
const DIM = '\u001b[38;2;110;110;118m';
const TAG = '\u001b[38;2;100;210;255m';
const RESET = '\u001b[0m';

const tint = (line: string): string => {
    if (line.includes('\u001b')) {
        return line;
    }
    if (STACK_FRAME.test(line)) {
        return DIM + line;
    }

    const color = ERROR_LINE.test(line)
        ? '\u001b[38;2;255;105;97m'
        : WARNING_LINE.test(line)
        ? '\u001b[38;2;255;214;10m'
        : SUCCESS_LINE.test(line)
        ? '\u001b[38;2;48;209;88m'
        : '';

    const tag = LEADING_TAG.exec(line);
    if (tag) {
        return (TIME_TAG.test(tag[1]) ? DIM : TAG) + tag[1] + RESET + color + tag[2];
    }

    return color + line;
};

const ToolButton = ({
    title,
    onClick,
    active,
    children,
}: {
    title: string;
    onClick: () => void;
    active?: boolean;
    children: React.ReactNode;
}) => (
    <button
        type={'button'}
        onClick={onClick}
        title={title}
        aria-label={title}
        className={classNames(
            'inline-flex items-center gap-1.5 h-7 rounded-full px-2.5 text-xs font-medium transition-colors duration-150',
            active
                ? 'bg-yellow-400/20 text-yellow-200 hover:bg-yellow-400/30'
                : 'text-gray-300 hover:text-white hover:bg-white/10'
        )}
    >
        {children}
    </button>
);

// The most lines kept aside while the console is paused.
const MAX_HELD_LINES = 5000;

// On a phone the log gets a smaller font, so more of each line fits before it wraps.
const narrow = typeof window !== 'undefined' && window.innerWidth < 640;

const terminalProps: ITerminalOptions = {
    disableStdin: true,
    cursorStyle: 'underline',
    allowTransparency: true,
    fontSize: narrow ? 11 : 13,
    lineHeight: narrow ? 1.3 : 1.4,
    letterSpacing: narrow ? 0 : 0.2,
    scrollback: 2000,
    fontFamily: '"SF Mono", "JetBrains Mono", "Cascadia Code", "Fira Code", Menlo, Consolas, monospace',
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
    // The bar that was loaded into the terminal, the one from the render where it was opened.
    const loadedSearchBar = useRef<SearchBarAddon | null>(null);
    const commandInput = useRef<HTMLInputElement>(null);
    const [copied, setCopied] = useState(false);
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

    // Puts everything that is on the console, and above it, in the clipboard as plain text.
    const copyConsole = () => {
        const buffer = terminal.buffer.active;
        const lines: string[] = [];
        for (let i = 0; i < buffer.length; i++) {
            const line = buffer.getLine(i);
            if (!line) {
                continue;
            }
            const text = line.translateToString(true);
            // A long line that the console wrapped is one line again.
            if (line.isWrapped && lines.length > 0) {
                lines[lines.length - 1] += text;
            } else {
                lines.push(text);
            }
        }

        const done = () => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        };
        if (navigator.clipboard) {
            navigator.clipboard.writeText(lines.join('\n').replace(/\s+$/, '')).then(done, () => undefined);
        }
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
        writeln(
            prelude
                ? TERMINAL_PRELUDE + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m'
                : tint(line.replace(/(?:\r\n|\r|\n)$/im, '')) + '\u001b[0m'
        );

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

        if (e.key === 'Enter') {
            sendCommand(e.currentTarget);
        }
    };

    const sendCommand = (input: HTMLInputElement | null) => {
        const command = input ? input.value : '';
        if (!input || command.length === 0) {
            return;
        }

        setHistory((prevHistory) => [command, ...prevHistory!].slice(0, 32));
        setHistoryIndex(-1);

        instance && instance.send('send command', command);
        input.value = '';
        input.focus();
    };

    useEffect(() => {
        if (connected && ref.current && !terminal.element) {
            terminal.loadAddon(fitAddon);
            terminal.loadAddon(searchAddon);
            terminal.loadAddon(searchBar);
            loadedSearchBar.current = searchBar;
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
        <div className={styles.terminal}>
            <SpinnerOverlay visible={!connected} size={'large'} />
            <div className={styles.header}>
                <span className={'flex items-center gap-3'}>
                    <span className={'flex items-center gap-1.5'} aria-hidden>
                        <span className={styles.light} style={{ background: '#ff5f57' }} />
                        <span className={styles.light} style={{ background: '#febc2e' }} />
                        <span className={styles.light} style={{ background: '#28c840' }} />
                    </span>
                    <span className={'text-sm font-semibold text-gray-100 select-none'}>Console</span>
                </span>
                <span className={'flex items-center gap-2'}>
                    <span className={styles.tools}>
                        <ToolButton title={'Search in the console'} onClick={() => loadedSearchBar.current?.show()}>
                            <SearchIcon className={'w-3.5 h-3.5'} />
                            <span className={'hidden md:inline'}>Search</span>
                        </ToolButton>
                        <ToolButton title={'Copy the whole console'} onClick={copyConsole} active={copied}>
                            {copied ? (
                                <CheckIcon className={'w-3.5 h-3.5'} />
                            ) : (
                                <ClipboardCopyIcon className={'w-3.5 h-3.5'} />
                            )}
                            <span className={'hidden md:inline'}>{copied ? 'Copied' : 'Copy'}</span>
                        </ToolButton>
                        <span className={'w-px h-4 bg-white/10 mx-0.5'} aria-hidden />
                        <ToolButton
                            title={
                                paused
                                    ? 'Show the new lines and follow the console again'
                                    : 'Freeze the console so it can be read'
                            }
                            onClick={togglePause}
                            active={paused}
                        >
                            {paused ? <PlayIcon className={'w-3.5 h-3.5'} /> : <PauseIcon className={'w-3.5 h-3.5'} />}
                            <span className={'hidden md:inline'}>{paused ? 'Resume' : 'Pause'}</span>
                            {paused && heldCount > 0 && (
                                <span className={'rounded-full bg-yellow-400/20 px-1.5 text-2xs tabular-nums'}>
                                    {heldCount >= MAX_HELD_LINES ? `${MAX_HELD_LINES}+` : heldCount} new
                                </span>
                            )}
                        </ToolButton>
                        <ToolButton title={'Empty the console'} onClick={clearConsole}>
                            <TrashIcon className={'w-3.5 h-3.5'} />
                            <span className={'hidden md:inline'}>Clear</span>
                        </ToolButton>
                    </span>
                    <StatusPill status={status} className={'hidden sm:inline-flex'} />
                </span>
            </div>
            <div className={styles.container}>
                <div className={'h-full'}>
                    <div id={styles.terminal} ref={ref} />
                </div>
            </div>
            {canSendCommands && (
                <div className={styles.prompt}>
                    <input
                        ref={commandInput}
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
                    <button
                        type={'button'}
                        title={'Send the command'}
                        aria-label={'Send the command'}
                        disabled={!instance || !connected}
                        onClick={() => sendCommand(commandInput.current)}
                        className={styles.command_send}
                    >
                        <ArrowUpIcon className={'w-4 h-4'} />
                    </button>
                </div>
            )}
        </div>
    );
};
