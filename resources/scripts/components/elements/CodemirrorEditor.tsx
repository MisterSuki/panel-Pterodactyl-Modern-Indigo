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

// The editor sits in a window frame drawn by the page (FileEditContainer). Its look is the one of VS Code, "Dark+":
// the same background, line numbers, selection and colours of the code, so a file reads like it does in VS Code.
const EditorContainer = styled.div`
    min-height: 16rem;
    height: calc(100vh - 24rem);
    ${tw`relative`};

    > div {
        ${tw`h-full`};
    }

    .CodeMirror {
        font-family: 'Cascadia Code', 'JetBrains Mono', 'SF Mono', Consolas, 'Courier New', monospace;
        font-size: 14px;
        line-height: 1.5;
        height: 100%;
    }

    .cm-s-pd-vscode.CodeMirror {
        background: #1e1e1e;
        color: #d4d4d4;
    }

    .cm-s-pd-vscode .CodeMirror-gutters {
        background: #1e1e1e;
        border-right: 0;
    }

    .cm-s-pd-vscode .CodeMirror-linenumber {
        color: #6e7681;
        padding: 0 16px 0 18px !important;
    }

    .cm-s-pd-vscode .CodeMirror-activeline-background {
        background: transparent;
        border-top: 1px solid #2a2a2a;
        border-bottom: 1px solid #2a2a2a;
    }

    .cm-s-pd-vscode .CodeMirror-activeline-gutter .CodeMirror-linenumber {
        color: #cccccc;
    }

    .cm-s-pd-vscode .CodeMirror-cursor {
        border-left: 2px solid #aeafad;
    }

    .cm-s-pd-vscode div.CodeMirror-selected {
        background: #3a3d41;
    }

    .cm-s-pd-vscode.CodeMirror-focused div.CodeMirror-selected {
        background: #264f78;
    }

    .cm-s-pd-vscode .CodeMirror-line::selection,
    .cm-s-pd-vscode .CodeMirror-line > span::selection,
    .cm-s-pd-vscode .CodeMirror-line > span > span::selection {
        background: #264f78;
    }

    .cm-s-pd-vscode .CodeMirror-matchingbracket {
        color: inherit !important;
        text-decoration: none;
        outline: 1px solid #888888;
        background: rgba(0, 100, 0, 0.1);
    }

    .cm-s-pd-vscode .cm-matchhighlight {
        background: rgba(173, 214, 255, 0.15);
    }

    .cm-s-pd-vscode .cm-trailingspace {
        background: rgba(255, 69, 58, 0.25);
    }

    /* The colours of the code: the "Dark+" theme of VS Code. */
    .cm-s-pd-vscode .cm-comment {
        color: #6a9955;
    }

    .cm-s-pd-vscode .cm-keyword,
    .cm-s-pd-vscode .cm-atom,
    .cm-s-pd-vscode .cm-tag,
    .cm-s-pd-vscode .cm-header {
        color: #569cd6;
    }

    .cm-s-pd-vscode .cm-builtin,
    .cm-s-pd-vscode .cm-meta {
        color: #c586c0;
    }

    .cm-s-pd-vscode .cm-string,
    .cm-s-pd-vscode .cm-string-2 {
        color: #ce9178;
    }

    .cm-s-pd-vscode .cm-number {
        color: #b5cea8;
    }

    .cm-s-pd-vscode .cm-property,
    .cm-s-pd-vscode .cm-attribute,
    .cm-s-pd-vscode .cm-variable,
    .cm-s-pd-vscode .cm-variable-2,
    .cm-s-pd-vscode .cm-qualifier {
        color: #9cdcfe;
    }

    /* JSON keys are strings and properties at once: shown as keys, like VS Code. */
    .cm-s-pd-vscode .cm-string.cm-property {
        color: #9cdcfe;
    }

    .cm-s-pd-vscode .cm-def {
        color: #dcdcaa;
    }

    .cm-s-pd-vscode .cm-variable-3,
    .cm-s-pd-vscode .cm-type {
        color: #4ec9b0;
    }

    .cm-s-pd-vscode .cm-operator,
    .cm-s-pd-vscode .cm-punctuation,
    .cm-s-pd-vscode .cm-bracket {
        color: #d4d4d4;
    }

    .cm-s-pd-vscode .cm-header,
    .cm-s-pd-vscode .cm-strong {
        font-weight: 700;
    }

    .cm-s-pd-vscode .cm-em {
        font-style: italic;
    }

    .cm-s-pd-vscode .cm-link {
        color: #3794ff;
        text-decoration: underline;
    }

    .cm-s-pd-vscode .cm-error {
        color: #f44747;
    }

    .CodeMirror-foldmarker {
        color: #c5c5c5;
        background: #3c3c3c;
        border-radius: 3px;
        padding: 0 4px;
        text-shadow: none;
        margin: 0 0.25rem;
    }

    .CodeMirror-foldgutter-open,
    .CodeMirror-foldgutter-folded {
        color: #c5c5c5;
        opacity: 0.6;
    }

    .CodeMirror-overlayscroll-vertical div,
    .CodeMirror-overlayscroll-horizontal div {
        background: rgba(121, 121, 121, 0.4);
        border-radius: 0;
    }

    .CodeMirror-dialog {
        background: #252526;
        color: #cccccc;
        border-bottom: 1px solid #454545;
        padding: 8px 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.36);
    }

    .CodeMirror-dialog input {
        color: #cccccc;
        background: #3c3c3c;
        border: 1px solid #3c3c3c;
        padding: 2px 6px;
        font-family: inherit;
    }
`;

// The server.cfg of FiveM (and the .cfg of Source games): one command per line, "#" or "//" comments, quoted text.
// The command is a keyword, the name a "set" gives is a variable, like the FiveM extension of VS Code shows them.
// The addon is loaded above; its function is not in the types of CodeMirror.
(CodeMirror as unknown as { defineSimpleMode: (name: string, states: unknown) => void }).defineSimpleMode('fivem-cfg', {
    start: [
        { regex: /\s*(#|\/\/).*/, token: 'comment', sol: true },
        { regex: /(?:#|\/\/).*/, token: 'comment' },
        { regex: /(\s*)(set[rsa]?)(\s+)([\w.:-]+)/, token: [null, 'keyword', null, 'property'], sol: true },
        { regex: /(\s*)([A-Za-z_][\w-]*)/, token: [null, 'keyword'], sol: true },
        { regex: /"(?:[^\\"]|\\.)*"?/, token: 'string' },
        { regex: /'(?:[^\\']|\\.)*'?/, token: 'string' },
        { regex: /\$\{?[\w.]+\}?/, token: 'variable-2' },
        { regex: /\b(?:true|false|allow|deny)\b/, token: 'atom' },
        { regex: /-?\d+(?:\.\d+)?\b/, token: 'number' },
        { regex: /\b(?:group|identifier|builtin|command|resource)\.[\w.:-]+/, token: 'variable-3' },
        { regex: /[+-]/, token: 'operator' },
    ],
    meta: { lineComment: '#' },
});

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
            theme: 'pd-vscode',
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
