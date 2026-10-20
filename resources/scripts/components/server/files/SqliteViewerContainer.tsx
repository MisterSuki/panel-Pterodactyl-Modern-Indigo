import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useHistory, useLocation } from 'react-router';
import tw from 'twin.macro';
import { basename, dirname } from 'pathe';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faCircleNotch,
    faDatabase,
    faDownload,
    faEye,
    faPlay,
    faPlus,
    faSave,
    faTable,
    faTrashAlt,
    faUndo,
} from '@fortawesome/free-solid-svg-icons';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FileManagerBreadcrumbs from '@/components/server/files/FileManagerBreadcrumbs';
import { ServerError } from '@/components/elements/ScreenBlock';
import { Button } from '@/components/elements/button/index';
import Can from '@/components/elements/Can';
import { ServerContext } from '@/state/server';
import { hashToPath } from '@/helpers';
import { bytesToString } from '@/lib/formatters';
import loadDirectory from '@/api/server/files/loadDirectory';
import getFileDownloadUrl from '@/api/server/files/getFileDownloadUrl';
import { downloadBytes, loadSqlJs, uploadBytes } from '@/api/server/files/sqlite';

/* eslint-disable @typescript-eslint/no-explicit-any */

const PAGE_SIZE = 100;

interface TableInfo {
    name: string;
    type: 'table' | 'view';
    count: number | null;
    sql: string | null;
}

interface Grid {
    columns: string[];
    rows: any[][];
    // The rowid of each row, when the rows can be changed (tables that have one).
    rowids: (number | null)[] | null;
}

const quote = (name: string) => '"' + name.replace(/"/g, '""') + '"';

const Cell = ({ value }: { value: any }) => {
    if (value === null || value === undefined) {
        return <span css={tw`italic text-neutral-500`}>NULL</span>;
    }
    if (value instanceof Uint8Array) {
        return <span css={tw`italic text-cyan-400`}>BLOB · {bytesToString(value.length)}</span>;
    }
    const text = String(value);

    return (
        <span title={text.length > 80 ? text : undefined} css={typeof value === 'number' ? tw`text-primary-300` : undefined}>
            {text.length > 120 ? text.slice(0, 120) + '…' : text}
        </span>
    );
};

const Tab = ({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) => (
    <button
        type={'button'}
        onClick={onClick}
        css={[
            tw`px-4 py-1.5 rounded-full text-sm font-medium transition-all duration-150 focus:outline-none`,
            active ? tw`bg-white/20 text-white` : tw`text-neutral-300 hover:text-white hover:bg-white/10`,
        ]}
    >
        {children}
    </button>
);

// A table of results, with an optional way to change a cell and delete a row.
const DataGrid = ({
    grid,
    offset,
    editable,
    onEdit,
    onDelete,
}: {
    grid: Grid;
    offset: number;
    editable: boolean;
    onEdit?: (rowIndex: number, column: number, value: string | null) => void;
    onDelete?: (rowIndex: number) => void;
}) => {
    const [editing, setEditing] = useState<{ row: number; column: number; value: string } | null>(null);

    const commit = (value: string | null) => {
        if (!editing || !onEdit) return;
        onEdit(editing.row, editing.column, value);
        setEditing(null);
    };

    if (grid.columns.length === 0) {
        return <p css={tw`text-sm text-neutral-400 p-4`}>No columns.</p>;
    }

    return (
        <div css={tw`overflow-auto rounded-xl border border-white/10 bg-black/30`} style={{ maxHeight: '60vh' }}>
            <table css={tw`min-w-full text-sm font-mono`}>
                <thead>
                    <tr>
                        <th css={tw`sticky top-0 z-10 bg-neutral-700 px-3 py-2 text-right text-xs text-neutral-400`}>
                            #
                        </th>
                        {grid.columns.map((column) => (
                            <th
                                key={column}
                                css={tw`sticky top-0 z-10 bg-neutral-700 px-3 py-2 text-left text-xs font-semibold text-neutral-200 whitespace-nowrap`}
                                style={{ backgroundColor: '#2c2c34' }}
                            >
                                {column}
                            </th>
                        ))}
                        {editable && (
                            <th
                                css={tw`sticky top-0 z-10 px-3 py-2`}
                                style={{ backgroundColor: '#2c2c34' }}
                            />
                        )}
                    </tr>
                </thead>
                <tbody>
                    {grid.rows.length === 0 && (
                        <tr>
                            <td colSpan={grid.columns.length + 2} css={tw`px-3 py-6 text-center text-neutral-400`}>
                                <span>No rows.</span>
                            </td>
                        </tr>
                    )}
                    {grid.rows.map((row, r) => (
                        <tr key={r} css={tw`border-t border-white/5 hover:bg-white/5`}>
                            <td css={tw`px-3 py-1.5 text-right text-xs text-neutral-500 whitespace-nowrap`}>
                                {offset + r + 1}
                            </td>
                            {row.map((value, c) =>
                                editing && editing.row === r && editing.column === c ? (
                                    <td key={c} css={tw`px-1 py-1`}>
                                        <div css={tw`flex items-center gap-1`}>
                                            <input
                                                autoFocus
                                                value={editing.value}
                                                onChange={(e) => setEditing({ ...editing, value: e.currentTarget.value })}
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') commit(editing.value);
                                                    if (e.key === 'Escape') setEditing(null);
                                                }}
                                                style={{ minWidth: '10rem' }}
                                                css={tw`w-full rounded-lg bg-black/40 border border-primary-500 px-2 py-1 text-sm text-white focus:outline-none`}
                                            />
                                            <button
                                                type={'button'}
                                                onClick={() => commit(null)}
                                                css={tw`px-2 py-1 rounded-lg text-xs text-neutral-300 bg-white/10 hover:bg-white/20`}
                                            >
                                                NULL
                                            </button>
                                        </div>
                                    </td>
                                ) : (
                                    <td
                                        key={c}
                                        onDoubleClick={() =>
                                            editable &&
                                            !(value instanceof Uint8Array) &&
                                            setEditing({ row: r, column: c, value: value === null ? '' : String(value) })
                                        }
                                        css={[
                                            tw`px-3 py-1.5 whitespace-nowrap text-neutral-100`,
                                            editable && tw`cursor-text`,
                                        ]}
                                    >
                                        <Cell value={value} />
                                    </td>
                                )
                            )}
                            {editable && (
                                <td css={tw`px-2 py-1 text-right`}>
                                    <button
                                        type={'button'}
                                        onClick={() => onDelete && onDelete(r)}
                                        css={tw`p-1.5 rounded-lg text-red-400 hover:bg-red-500/20`}
                                    >
                                        <FontAwesomeIcon icon={faTrashAlt} />
                                    </button>
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
};

export default () => {
    const history = useHistory();
    const { hash } = useLocation();
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const status = ServerContext.useStoreState((state) => state.status.value);

    const filePath = hashToPath(hash);
    const directory = dirname(filePath);
    const name = basename(filePath);

    const db = useRef<any>(null);
    const [size, setSize] = useState(0);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<{ type: 'error' | 'success'; text: string } | null>(null);
    const [tables, setTables] = useState<TableInfo[]>([]);
    const [selected, setSelected] = useState<string | null>(null);
    const [tab, setTab] = useState<'data' | 'structure' | 'sql'>('data');
    const [page, setPage] = useState(0);
    const [grid, setGrid] = useState<Grid | null>(null);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [hasWal, setHasWal] = useState(false);
    const [sql, setSql] = useState('');
    const [result, setResult] = useState<{ grid: Grid | null; count: number | null; word: string; extra?: string } | null>(
        null
    );
    const [version, setVersion] = useState(0);

    const readTables = useCallback(() => {
        if (!db.current) return;
        const res = db.current.exec(
            "SELECT name, type, sql FROM sqlite_master WHERE type IN ('table', 'view') AND name NOT LIKE 'sqlite_%' ORDER BY type, name"
        );
        const list: TableInfo[] = (res[0]?.values || []).map((row: any[]) => {
            let count: number | null = null;
            try {
                count = Number(db.current.exec(`SELECT COUNT(*) FROM ${quote(row[0])}`)[0].values[0][0]);
            } catch {
                count = null;
            }

            return { name: row[0], type: row[1], sql: row[2], count };
        });
        setTables(list);
        setSelected((current) => (current && list.some((t) => t.name === current) ? current : list[0]?.name || null));
    }, []);

    // Downloads the file and opens it in the browser.
    const open = useCallback(() => {
        setLoading(true);
        setError(null);
        setNotice(null);
        Promise.all([loadSqlJs(), downloadBytes(uuid, filePath)])
            .then(([SQL, bytes]) => {
                if (db.current) db.current.close();
                db.current = new SQL.Database(bytes);
                db.current.exec('SELECT count(*) FROM sqlite_master');
                setSize(bytes.length);
                setDirty(false);
                setPage(0);
                readTables();
                setVersion((v) => v + 1);
            })
            .catch((e) =>
                setError(
                    /file is not a database|malformed/i.test(String(e?.message))
                        ? 'This file is not a SQLite database, or it is damaged.'
                        : e?.message || String(e)
                )
            )
            .then(() => setLoading(false));

        // A "-wal" file next to it holds changes the game did not write into the database yet.
        loadDirectory(uuid, directory)
            .then((files) => setHasWal(files.some((f) => f.name === name + '-wal' && f.size > 0)))
            .catch(() => setHasWal(false));
    }, [uuid, filePath]);

    useEffect(() => {
        open();

        return () => {
            if (db.current) db.current.close();
            db.current = null;
        };
    }, [open]);

    const current = tables.find((t) => t.name === selected) || null;

    // The rows of the page of the table that is open.
    useEffect(() => {
        if (!db.current || !current) {
            setGrid(null);

            return;
        }
        const from = quote(current.name);
        const limit = `LIMIT ${PAGE_SIZE} OFFSET ${page * PAGE_SIZE}`;
        try {
            const res = db.current.exec(`SELECT rowid AS "__pd_rowid__", * FROM ${from} ${limit}`);
            const columns: string[] = res[0]?.columns || [];
            if (current.type === 'table' && columns[0] === '__pd_rowid__') {
                const values = res[0]?.values || [];
                setGrid({
                    columns: columns.slice(1),
                    rows: values.map((row: any[]) => row.slice(1)),
                    rowids: values.map((row: any[]) => row[0]),
                });

                return;
            }
        } catch {
            // Views and WITHOUT ROWID tables have no rowid: shown without editing.
        }
        try {
            const res = db.current.exec(`SELECT * FROM ${from} ${limit}`);
            const info = db.current.exec(`PRAGMA table_info(${from})`);
            setGrid({
                columns: res[0]?.columns || (info[0]?.values || []).map((row: any[]) => row[1]),
                rows: res[0]?.values || [],
                rowids: null,
            });
        } catch (e: any) {
            setGrid({ columns: [], rows: [], rowids: null });
            setNotice({ type: 'error', text: e?.message || String(e) });
        }
    }, [selected, page, version, tables]);

    const structure = useMemo(() => {
        if (!db.current || !current) return null;
        try {
            const res = db.current.exec(`PRAGMA table_info(${quote(current.name)})`);

            return (res[0]?.values || []).map((row: any[]) => ({
                name: row[1],
                type: row[2] || '—',
                notnull: !!row[3],
                fallback: row[4],
                pk: !!row[5],
            }));
        } catch {
            return null;
        }
    }, [current, version]);

    const changed = (text: string) => {
        setDirty(true);
        setNotice({ type: 'success', text });
        readTables();
        setVersion((v) => v + 1);
    };

    const run = (statement: string, params: any[], done: string) => {
        try {
            db.current.run(statement, params);
            changed(done);
        } catch (e: any) {
            setNotice({ type: 'error', text: e?.message || String(e) });
        }
    };

    const editCell = (rowIndex: number, column: number, raw: string | null) => {
        if (!grid?.rowids || !current) return;
        const old = grid.rows[rowIndex][column];
        let value: any = raw;
        if (raw !== null && typeof old === 'number' && raw.trim() !== '' && !isNaN(Number(raw))) {
            value = Number(raw);
        }
        run(
            `UPDATE ${quote(current.name)} SET ${quote(grid.columns[column])} = ? WHERE rowid = ?`,
            [value, grid.rowids[rowIndex]],
            'The value was changed. Save to write it into the file.'
        );
    };

    const deleteRow = (rowIndex: number) => {
        if (!grid?.rowids || !current) return;
        run(`DELETE FROM ${quote(current.name)} WHERE rowid = ?`, [grid.rowids[rowIndex]], 'The row was deleted. Save to write it into the file.');
    };

    const addRow = () => {
        if (!current) return;
        run(`INSERT INTO ${quote(current.name)} DEFAULT VALUES`, [], 'An empty row was added at the end of the table. Save to write it into the file.');
    };

    const runSql = () => {
        if (!db.current || sql.trim() === '') return;
        try {
            const before = db.current.getRowsModified ? db.current.getRowsModified() : 0;
            const res = db.current.exec(sql);
            const modified = db.current.getRowsModified ? db.current.getRowsModified() : 0;
            const last = res[res.length - 1];
            const writes = /^\s*(insert|update|delete|replace|create|drop|alter|vacuum|reindex)\b/im.test(sql);
            const count = last ? last.values.length : writes ? modified || before : null;
            setResult({
                grid: last ? { columns: last.columns, rows: last.values.slice(0, 1000), rowids: null } : null,
                count,
                word: last
                    ? count === 1
                        ? 'row'
                        : 'rows'
                    : writes
                    ? count === 1
                        ? 'row changed'
                        : 'rows changed'
                    : 'Done.',
                extra: last && last.values.length > 1000 ? '(1000 shown)' : undefined,
            });
            if (writes) changed('The query changed the database. Save to write it into the file.');
            else setNotice(null);
        } catch (e: any) {
            setResult(null);
            setNotice({ type: 'error', text: e?.message || String(e) });
        }
    };

    const save = () => {
        if (!db.current) return;
        setSaving(true);
        setNotice(null);
        const bytes: Uint8Array = db.current.export();
        uploadBytes(uuid, directory, name, bytes)
            .then(() => {
                setDirty(false);
                setSize(bytes.length);
                setNotice({ type: 'success', text: 'The database was saved on the server.' });
            })
            .catch((e) => setNotice({ type: 'error', text: e?.message || String(e) }))
            .then(() => setSaving(false));
    };

    const download = () => {
        getFileDownloadUrl(uuid, filePath)
            .then((url) => {
                window.location.href = url;
            })
            .catch((e) => setNotice({ type: 'error', text: e?.message || String(e) }));
    };

    if (error) {
        return <ServerError title={'Could not open the database'} message={error} onBack={() => history.goBack()} />;
    }

    const pages = current?.count ? Math.max(1, Math.ceil(current.count / PAGE_SIZE)) : 1;

    return (
        <ServerContentBlock title={name}>
            <div css={tw`mb-4`}>
                <FileManagerBreadcrumbs withinFileEditor />
            </div>

            <div css={tw`flex flex-wrap items-center gap-3 mb-4`}>
                <span
                    css={tw`flex items-center justify-center w-11 h-11 rounded-2xl bg-primary-500 bg-opacity-20 text-primary-300 text-lg flex-shrink-0`}
                >
                    <FontAwesomeIcon icon={faDatabase} />
                </span>
                <div css={tw`min-w-0 flex-1`}>
                    <h1 css={tw`text-xl text-neutral-50 font-semibold truncate`}>{name}</h1>
                    <p css={tw`text-xs text-neutral-400`}>
                        <span>SQLite database</span> · {bytesToString(size)} · {tables.length}{' '}
                        <span>{tables.length > 1 ? 'tables' : 'table'}</span>
                        {dirty && (
                            <span css={tw`ml-2 px-2 py-0.5 rounded-full bg-yellow-500/20 text-yellow-300`}>
                                Not saved
                            </span>
                        )}
                    </p>
                </div>
                <div css={tw`flex flex-wrap gap-2`}>
                    <Button.Text type={'button'} size={Button.Sizes.Small} onClick={download}>
                        <FontAwesomeIcon icon={faDownload} css={tw`mr-1.5`} /> <span>Download</span>
                    </Button.Text>
                    <Button.Text type={'button'} size={Button.Sizes.Small} onClick={open} disabled={loading || saving}>
                        <FontAwesomeIcon icon={faUndo} css={tw`mr-1.5`} /> <span>Reload</span>
                    </Button.Text>
                    <Can action={['file.update', 'file.create']}>
                        <Button type={'button'} size={Button.Sizes.Small} onClick={save} disabled={!dirty || saving}>
                            <FontAwesomeIcon icon={saving ? faCircleNotch : faSave} spin={saving} css={tw`mr-1.5`} />{' '}
                            <span>Save</span>
                        </Button>
                    </Can>
                </div>
            </div>

            {dirty && status !== 'offline' && (
                <div css={tw`mb-4 rounded-xl border border-yellow-500/30 bg-yellow-500/10 px-4 py-3 text-sm text-yellow-200`}>
                    The server is running. Stop it before saving, or it may overwrite your changes (or keep using the old
                    data).
                </div>
            )}
            {hasWal && (
                <div css={tw`mb-4 rounded-xl border border-cyan-500/30 bg-cyan-500/10 px-4 py-3 text-sm text-cyan-200`}>
                    A &quot;-wal&quot; file is next to this database: the latest changes may not be in it yet. Stop the
                    server to see everything.
                </div>
            )}
            {notice && (
                <div
                    css={[
                        tw`mb-4 rounded-xl border px-4 py-3 text-sm break-words`,
                        notice.type === 'error'
                            ? tw`border-red-500/30 bg-red-500/10 text-red-200`
                            : tw`border-green-500/30 bg-green-500/10 text-green-200`,
                    ]}
                >
                    {notice.text}
                </div>
            )}

            {loading ? (
                <div css={tw`flex flex-col items-center justify-center py-24 text-neutral-400`}>
                    <FontAwesomeIcon icon={faCircleNotch} spin css={tw`text-2xl mb-3`} />
                    <span>Opening the database…</span>
                </div>
            ) : (
                <div css={tw`grid grid-cols-1 lg:grid-cols-4 gap-4`}>
                    <div css={tw`lg:col-span-1 rounded-2xl bg-neutral-800 border border-white/10 shadow-card p-3 self-start`}>
                        <p css={tw`px-2 pb-2 text-xs uppercase tracking-wide text-neutral-400 font-semibold`}>Tables</p>
                        {tables.length === 0 && <p css={tw`px-2 py-3 text-sm text-neutral-400`}>No tables.</p>}
                        <div css={tw`flex flex-col gap-1 overflow-y-auto`} style={{ maxHeight: '60vh' }}>
                            {tables.map((t) => (
                                <button
                                    key={t.name}
                                    type={'button'}
                                    onClick={() => {
                                        setSelected(t.name);
                                        setPage(0);
                                        if (tab === 'sql') setTab('data');
                                    }}
                                    css={[
                                        tw`flex items-center gap-2 w-full text-left px-3 py-2 rounded-xl text-sm transition-colors duration-150 focus:outline-none`,
                                        selected === t.name && tab !== 'sql'
                                            ? tw`bg-primary-500 bg-opacity-20 text-white`
                                            : tw`text-neutral-300 hover:bg-white/10 hover:text-white`,
                                    ]}
                                >
                                    <FontAwesomeIcon
                                        icon={t.type === 'view' ? faEye : faTable}
                                        css={tw`text-xs text-neutral-400 flex-shrink-0`}
                                    />
                                    <span css={tw`truncate flex-1 font-mono`}>{t.name}</span>
                                    {t.count !== null && (
                                        <span css={tw`text-xs text-neutral-500 tabular-nums`}>{t.count}</span>
                                    )}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div css={tw`lg:col-span-3 rounded-2xl bg-neutral-800 border border-white/10 shadow-card p-4 min-w-0`}>
                        <div css={tw`flex flex-wrap items-center gap-2 mb-4`}>
                            <div css={tw`flex gap-1 p-1 rounded-full bg-black/30 border border-white/10`}>
                                <Tab active={tab === 'data'} onClick={() => setTab('data')}>
                                    Data
                                </Tab>
                                <Tab active={tab === 'structure'} onClick={() => setTab('structure')}>
                                    Structure
                                </Tab>
                                <Tab active={tab === 'sql'} onClick={() => setTab('sql')}>
                                    SQL
                                </Tab>
                            </div>
                            {tab !== 'sql' && current && (
                                <span css={tw`ml-auto font-mono text-sm text-neutral-300 truncate`}>{current.name}</span>
                            )}
                        </div>

                        {tab === 'data' && current && grid && (
                            <>
                                <DataGrid
                                    grid={grid}
                                    offset={page * PAGE_SIZE}
                                    editable={!!grid.rowids}
                                    onEdit={editCell}
                                    onDelete={deleteRow}
                                />
                                <div css={tw`flex flex-wrap items-center gap-2 mt-3 text-sm text-neutral-400`}>
                                    {grid.rowids ? (
                                        <span css={tw`text-xs`}>Double-click a cell to change it.</span>
                                    ) : (
                                        <span css={tw`text-xs`}>Read only (view or table without rowid).</span>
                                    )}
                                    <div css={tw`ml-auto flex items-center gap-2`}>
                                        {grid.rowids && (
                                            <Button.Text type={'button'} size={Button.Sizes.Small} onClick={addRow}>
                                                <FontAwesomeIcon icon={faPlus} css={tw`mr-1.5`} /> <span>Add a row</span>
                                            </Button.Text>
                                        )}
                                        <Button.Text
                                            type={'button'}
                                            size={Button.Sizes.Small}
                                            disabled={page === 0}
                                            onClick={() => setPage(page - 1)}
                                        >
                                            ‹
                                        </Button.Text>
                                        <span css={tw`tabular-nums text-xs`}>
                                            {page + 1} / {pages}
                                        </span>
                                        <Button.Text
                                            type={'button'}
                                            size={Button.Sizes.Small}
                                            disabled={page + 1 >= pages}
                                            onClick={() => setPage(page + 1)}
                                        >
                                            ›
                                        </Button.Text>
                                    </div>
                                </div>
                            </>
                        )}
                        {tab === 'data' && !current && <p css={tw`text-sm text-neutral-400`}>No tables.</p>}

                        {tab === 'structure' && current && (
                            <>
                                {structure && (
                                    <DataGrid
                                        grid={{
                                            columns: ['Column', 'Type', 'Not null', 'Default', 'Key'],
                                            rows: structure.map((c: any) => [
                                                c.name,
                                                c.type,
                                                c.notnull ? '✓' : '',
                                                c.fallback,
                                                c.pk ? 'PK' : '',
                                            ]),
                                            rowids: null,
                                        }}
                                        offset={0}
                                        editable={false}
                                    />
                                )}
                                {current.sql && (
                                    <pre
                                        css={tw`mt-4 p-4 rounded-xl bg-black/40 border border-white/10 text-xs text-neutral-200 font-mono whitespace-pre-wrap break-words`}
                                    >
                                        {current.sql}
                                    </pre>
                                )}
                            </>
                        )}

                        {tab === 'sql' && (
                            <>
                                <textarea
                                    value={sql}
                                    onChange={(e) => setSql(e.currentTarget.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                                            e.preventDefault();
                                            runSql();
                                        }
                                    }}
                                    spellCheck={false}
                                    placeholder={'SELECT * FROM ' + (current?.name || 'table') + ' LIMIT 50;'}
                                    css={tw`w-full h-36 rounded-xl bg-black/40 border border-white/10 p-3 font-mono text-sm text-neutral-100 focus:outline-none focus:border-primary-500`}
                                />
                                <div css={tw`flex items-center gap-3 mt-2 mb-4`}>
                                    <Button type={'button'} size={Button.Sizes.Small} onClick={runSql}>
                                        <FontAwesomeIcon icon={faPlay} css={tw`mr-1.5`} /> <span>Run</span>
                                    </Button>
                                    <span css={tw`text-xs text-neutral-400`}>Ctrl + Enter</span>
                                    {result && (
                                        <span css={tw`ml-auto text-xs text-neutral-300`}>
                                            {result.count !== null && <>{result.count} </>}
                                            <span>{result.word}</span>
                                            {result.extra && (
                                                <>
                                                    {' '}
                                                    <span>{result.extra}</span>
                                                </>
                                            )}
                                        </span>
                                    )}
                                </div>
                                {result?.grid && <DataGrid grid={result.grid} offset={0} editable={false} />}
                            </>
                        )}
                    </div>
                </div>
            )}
        </ServerContentBlock>
    );
};
