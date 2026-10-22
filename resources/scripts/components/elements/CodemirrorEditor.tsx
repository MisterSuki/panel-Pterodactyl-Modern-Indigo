import React, { useCallback, useEffect, useState } from 'react';
import CodeMirror from 'codemirror';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import modes from '@/modes';

require('codemirror/lib/codemirror.css');
require('codemirror/theme/ayu-mirage.css');
require('codemirror/addon/edit/closebrackets');
require('codemirror/addon/edit/closetag');
require('codemirror/addon/edit/matchbrackets');
require('codemirror/addon/edit/matchtags');
require('codemirror/addon/edit/trailingspace');
require('codemirror/addon/fold/foldcode');
require('codemirror/addon/fold/foldgutter.css');
require('codemirror/addon/fold/foldgutter');
require('codemirror/addon/fold/brace-fold');
require('codemirror/addon/fold/comment-fold');
require('codemirror/addon/fold/indent-fold');
require('codemirror/addon/fold/markdown-fold');
require('codemirror/addon/fold/xml-fold');
require('codemirror/addon/hint/css-hint');
require('codemirror/addon/hint/html-hint');
require('codemirror/addon/hint/javascript-hint');
require('codemirror/addon/hint/show-hint.css');
require('codemirror/addon/hint/show-hint');
require('codemirror/addon/hint/sql-hint');
require('codemirror/addon/hint/xml-hint');
require('codemirror/addon/mode/simple');
require('codemirror/addon/dialog/dialog.css');
require('codemirror/addon/dialog/dialog');
require('codemirror/addon/scroll/annotatescrollbar');
require('codemirror/addon/scroll/scrollpastend');
require('codemirror/addon/scroll/simplescrollbars.css');
require('codemirror/addon/scroll/simplescrollbars');
require('codemirror/addon/search/jump-to-line');
require('codemirror/addon/search/match-highlighter');
require('codemirror/addon/search/matchesonscrollbar.css');
require('codemirror/addon/search/matchesonscrollbar');
require('codemirror/addon/search/search');
require('codemirror/addon/search/searchcursor');
require('codemirror/addon/selection/active-line');

require('codemirror/mode/brainfuck/brainfuck');
require('codemirror/mode/clike/clike');
require('codemirror/mode/css/css');
require('codemirror/mode/dart/dart');
require('codemirror/mode/diff/diff');
require('codemirror/mode/dockerfile/dockerfile');
require('codemirror/mode/erlang/erlang');
require('codemirror/mode/gfm/gfm');
require('codemirror/mode/go/go');
require('codemirror/mode/handlebars/handlebars');
require('codemirror/mode/htmlembedded/htmlembedded');
require('codemirror/mode/htmlmixed/htmlmixed');
require('codemirror/mode/http/http');
require('codemirror/mode/javascript/javascript');
require('codemirror/mode/jsx/jsx');
require('codemirror/mode/julia/julia');
require('codemirror/mode/lua/lua');
require('codemirror/mode/markdown/markdown');
require('codemirror/mode/nginx/nginx');
require('codemirror/mode/perl/perl');
require('codemirror/mode/php/php');
require('codemirror/mode/properties/properties');
require('codemirror/mode/protobuf/protobuf');
require('codemirror/mode/pug/pug');
require('codemirror/mode/python/python');
require('codemirror/mode/rpm/rpm');
require('codemirror/mode/ruby/ruby');
require('codemirror/mode/rust/rust');
require('codemirror/mode/sass/sass');
require('codemirror/mode/shell/shell');
require('codemirror/mode/smarty/smarty');
require('codemirror/mode/sql/sql');
require('codemirror/mode/swift/swift');
require('codemirror/mode/toml/toml');
require('codemirror/mode/twig/twig');
require('codemirror/mode/vue/vue');
require('codemirror/mode/xml/xml');
require('codemirror/mode/yaml/yaml');

// The editor sits in a window frame drawn by the page (FileEditContainer): here only its own look, an iOS dark theme
// ("pd-glass") with the system colours, the monospace font of the console and the active line lit.
const EditorContainer = styled.div`
    min-height: 16rem;
    height: calc(100vh - 22rem);
    ${tw`relative`};

    > div {
        ${tw`h-full`};
    }

    .CodeMirror {
        font-family: 'SF Mono', 'JetBrains Mono', 'Cascadia Code', 'Fira Code', Menlo, Consolas, monospace;
        font-size: 13px;
        line-height: 1.65;
        height: 100%;
    }

    .cm-s-pd-glass.CodeMirror {
        background: transparent;
        color: #e5e5ea;
    }

    .cm-s-pd-glass .CodeMirror-gutters {
        background: rgba(0, 0, 0, 0.18);
        border-right: 1px solid rgba(255, 255, 255, 0.06);
    }

    .cm-s-pd-glass .CodeMirror-linenumber {
        color: rgba(235, 235, 240, 0.28);
        padding: 0 12px 0 14px !important;
    }

    .cm-s-pd-glass .CodeMirror-activeline-background {
        background: rgba(255, 255, 255, 0.045);
    }

    .cm-s-pd-glass .CodeMirror-activeline-gutter .CodeMirror-linenumber {
        color: rgb(var(--pd-accent-300));
    }

    .cm-s-pd-glass .CodeMirror-cursor {
        border-left: 2px solid rgb(var(--pd-accent-400));
    }

    .cm-s-pd-glass div.CodeMirror-selected,
    .cm-s-pd-glass.CodeMirror-focused div.CodeMirror-selected {
        background: rgb(var(--pd-accent-500) / 0.28);
    }

    .cm-s-pd-glass .CodeMirror-line::selection,
    .cm-s-pd-glass .CodeMirror-line > span::selection,
    .cm-s-pd-glass .CodeMirror-line > span > span::selection {
        background: rgb(var(--pd-accent-500) / 0.28);
    }

    .cm-s-pd-glass .CodeMirror-matchingbracket {
        color: #30d158 !important;
        text-decoration: none;
        background: rgba(48, 209, 88, 0.15);
        border-radius: 3px;
    }

    .cm-s-pd-glass .cm-matchhighlight {
        background: rgba(255, 214, 10, 0.12);
        border-radius: 3px;
    }

    .cm-s-pd-glass .cm-trailingspace {
        background: rgba(255, 69, 58, 0.25);
    }

    /* The colours of the code, from the iOS system palette. */
    .cm-s-pd-glass .cm-property,
    .cm-s-pd-glass .cm-attribute,
    .cm-s-pd-glass .cm-qualifier {
        color: #64d2ff;
    }

    .cm-s-pd-glass .cm-string,
    .cm-s-pd-glass .cm-string-2 {
        color: #7ee787;
    }

    /* JSON keys are strings and properties at once: shown as keys. */
    .cm-s-pd-glass .cm-string.cm-property {
        color: #64d2ff;
    }

    .cm-s-pd-glass .cm-number {
        color: #ff9f0a;
    }

    .cm-s-pd-glass .cm-atom,
    .cm-s-pd-glass .cm-builtin {
        color: #bf5af2;
    }

    .cm-s-pd-glass .cm-keyword,
    .cm-s-pd-glass .cm-tag {
        color: #ff7ab2;
    }

    .cm-s-pd-glass .cm-def,
    .cm-s-pd-glass .cm-variable-2,
    .cm-s-pd-glass .cm-variable-3,
    .cm-s-pd-glass .cm-type {
        color: #5ac8fa;
    }

    .cm-s-pd-glass .cm-variable {
        color: #f5f5f7;
    }

    .cm-s-pd-glass .cm-operator,
    .cm-s-pd-glass .cm-punctuation,
    .cm-s-pd-glass .cm-bracket {
        color: #aeaeb2;
    }

    .cm-s-pd-glass .cm-comment,
    .cm-s-pd-glass .cm-meta {
        color: #8e8e93;
        font-style: italic;
    }

    .cm-s-pd-glass .cm-header,
    .cm-s-pd-glass .cm-strong {
        color: #ffd60a;
        font-weight: 600;
    }

    .cm-s-pd-glass .cm-link {
        color: rgb(var(--pd-accent-300));
    }

    .cm-s-pd-glass .cm-error {
        color: #ff453a;
    }

    .CodeMirror-foldmarker {
        color: rgb(var(--pd-accent-300));
        text-shadow: none;
        margin: 0 0.25rem;
    }

    .CodeMirror-foldgutter-open,
    .CodeMirror-foldgutter-folded {
        color: rgba(235, 235, 240, 0.35);
    }

    .CodeMirror-overlayscroll-vertical div,
    .CodeMirror-overlayscroll-horizontal div {
        background: rgba(255, 255, 255, 0.2);
    }

    .CodeMirror-dialog {
        background: rgba(30, 30, 38, 0.98);
        color: #f5f5f7;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding: 8px 12px;
    }

    .CodeMirror-dialog input {
        color: #f5f5f7;
        font-family: inherit;
    }
`;

export interface Props {
    style?: React.CSSProperties;
    initialContent?: string;
    mode: string;
    filename?: string;
    onModeChanged: (mode: string) => void;
    fetchContent: (callback: () => Promise<string>) => void;
    onContentSaved: () => void;
    onContentChanged?: (content: string) => void;
    // Where the cursor is and how big the text is, for the status bar of the page.
    onStatus?: (status: { line: number; column: number; lines: number; length: number }) => void;
    // Called on every change made by the person (not when the text is loaded).
    onEdited?: () => void;
}

const findModeByFilename = (filename: string) => {
    for (let i = 0; i < modes.length; i++) {
        const info = modes[i];

        if (info.file && info.file.test(filename)) {
            return info;
        }
    }

    const dot = filename.lastIndexOf('.');
    const ext = dot > -1 && filename.substring(dot + 1, filename.length);

    if (ext) {
        for (let i = 0; i < modes.length; i++) {
            const info = modes[i];
            if (info.ext) {
                for (let j = 0; j < info.ext.length; j++) {
                    if (info.ext[j] === ext) {
                        return info;
                    }
                }
            }
        }
    }

    return undefined;
};

export default ({
    style,
    initialContent,
    filename,
    mode,
    fetchContent,
    onContentSaved,
    onModeChanged,
    onContentChanged,
    onStatus,
    onEdited,
}: Props) => {
    const [editor, setEditor] = useState<CodeMirror.Editor>();

    const ref = useCallback((node) => {
        if (!node) return;

        const e = CodeMirror.fromTextArea(node, {
            mode: 'text/plain',
            theme: 'pd-glass',
            indentUnit: 4,
            smartIndent: true,
            tabSize: 4,
            indentWithTabs: false,
            lineWrapping: true,
            lineNumbers: true,
            foldGutter: true,
            fixedGutter: true,
            scrollbarStyle: 'overlay',
            coverGutterNextToScrollbar: false,
            readOnly: false,
            showCursorWhenSelecting: false,
            autofocus: false,
            spellcheck: true,
            autocorrect: false,
            autocapitalize: false,
            lint: false,
            // @ts-expect-error this property is actually used, the d.ts file for CodeMirror is incorrect.
            autoCloseBrackets: true,
            matchBrackets: true,
            gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
            // Addon options (active line, selection matches, trailing spaces).
            styleActiveLine: true,
            highlightSelectionMatches: { showToken: /\w/, annotateScrollbar: true },
            showTrailingSpace: true,
        });

        setEditor(e);
    }, []);

    useEffect(() => {
        if (filename === undefined) {
            return;
        }

        onModeChanged(findModeByFilename(filename)?.mime || 'text/plain');
    }, [filename]);

    useEffect(() => {
        editor && editor.setOption('mode', mode);
    }, [editor, mode]);

    useEffect(() => {
        if (editor) {
            editor.setValue(initialContent || '');
            // Reset the history so that "Ctrl+Z" doesn't delete the intial content
            // we just set above.
            editor.setHistory({ done: [], undone: [] });
        }
    }, [editor, initialContent]);

    useEffect(() => {
        if (!editor || (!onStatus && !onEdited)) return;

        const report = () => {
            const cursor = editor.getCursor();
            onStatus &&
                onStatus({
                    line: cursor.line + 1,
                    column: cursor.ch + 1,
                    lines: editor.lineCount(),
                    length: editor.getValue().length,
                });
        };
        const changed = (_: CodeMirror.Editor, change: CodeMirror.EditorChange) => {
            if (change.origin !== 'setValue') onEdited && onEdited();
            report();
        };
        editor.on('cursorActivity', report);
        editor.on('change', changed);
        report();

        return () => {
            editor.off('cursorActivity', report);
            editor.off('change', changed);
        };
    }, [editor, onStatus, onEdited]);

    useEffect(() => {
        if (!editor || !onContentChanged) return;

        const onChange = () => onContentChanged(editor.getValue());

        editor.on('change', onChange);

        return () => editor.off('change', onChange);
    }, [editor, onContentChanged]);

    useEffect(() => {
        if (!editor) {
            fetchContent(() => Promise.reject(new Error('no editor session has been configured')));
            return;
        }

        editor.addKeyMap({
            'Ctrl-S': () => onContentSaved(),
            'Cmd-S': () => onContentSaved(),
        });

        fetchContent(() => Promise.resolve(editor.getValue()));
    }, [editor, fetchContent, onContentSaved]);

    return (
        <EditorContainer style={style}>
            <textarea ref={ref} />
        </EditorContainer>
    );
};
