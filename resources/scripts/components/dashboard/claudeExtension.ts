import { makeZip } from '@/lib/zip';

// The program of the extension: Claude Desktop talks to it on stdin / stdout (MCP), it passes every message to the
// connector of the panel (MCP over HTTP) and gives the answers back. Node of Claude Desktop, no package needed.
const FORWARDER = `'use strict';
// Passes the messages of Claude Desktop (stdio) to the connector of the panel (HTTP), and the answers back.
const readline = require('readline');

const url = process.env.PANEL_MCP_URL;
const write = (message) => process.stdout.write(JSON.stringify(message) + '\\n');
const fail = (id, text) => write({ jsonrpc: '2.0', id, error: { code: -32000, message: text } });

readline.createInterface({ input: process.stdin }).on('line', async (line) => {
    if (!line.trim()) return;
    let message;
    try {
        message = JSON.parse(line);
    } catch (e) {
        return;
    }
    const asks = message && Object.prototype.hasOwnProperty.call(message, 'id');
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json, text/event-stream' },
            body: JSON.stringify(message),
        });
        if (!asks || response.status === 202) return;
        const text = await response.text();
        let answer = null;
        try {
            answer = JSON.parse(text);
        } catch (e) {
            answer = null;
        }
        if (answer && typeof answer === 'object') {
            if (answer.id === null || answer.id === undefined) answer.id = message.id;
            write(answer);
        } else {
            fail(message.id, 'The panel answered ' + response.status + '.');
        }
    } catch (e) {
        if (asks) fail(message.id, 'The panel could not be reached: ' + (e && e.message ? e.message : e));
    }
});
`;

const slug = (text: string) =>
    text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '') || 'panel';

// A Claude Desktop extension (.mcpb, a zip): a double-click on it in Windows or macOS installs the connector of the
// panel in Claude Desktop, with the address of the link inside.
export const claudeExtension = (url: string, panelName: string): Blob => {
    const origin = new URL(url).origin;
    const manifest = {
        // eslint-disable-next-line camelcase
        manifest_version: '0.3',
        name: `${slug(panelName)}-panel`,
        // eslint-disable-next-line camelcase
        display_name: `${panelName} (panel)`,
        version: '1.0.0',
        description: `Your game servers on ${panelName}: state, console, commands, start / stop, files.`,
        author: { name: panelName, url: origin },
        homepage: origin,
        server: {
            type: 'node',
            // eslint-disable-next-line camelcase
            entry_point: 'server/index.js',
            // eslint-disable-next-line camelcase
            mcp_config: {
                command: 'node',
                args: ['${__dirname}/server/index.js'],
                env: { PANEL_MCP_URL: url },
            },
        },
        tools: [
            { name: 'list_servers', description: 'List my servers' },
            { name: 'server_status', description: 'State of a server' },
            { name: 'read_console', description: 'Read the console' },
            { name: 'list_files', description: 'List files' },
            { name: 'read_file', description: 'Read a file' },
            { name: 'edit_file', description: 'Edit a file (can be undone)' },
            { name: 'send_command', description: 'Send a command' },
            { name: 'power', description: 'Start / stop / restart' },
        ],
        compatibility: { platforms: ['win32', 'darwin', 'linux'], runtimes: { node: '>=18.0.0' } },
        // eslint-disable-next-line camelcase
        privacy_policies: [origin],
    };

    const zip = makeZip([
        { name: 'manifest.json', content: JSON.stringify(manifest, null, 2) },
        { name: 'server/index.js', content: FORWARDER },
    ]);

    return new Blob([zip], { type: 'application/zip' });
};
