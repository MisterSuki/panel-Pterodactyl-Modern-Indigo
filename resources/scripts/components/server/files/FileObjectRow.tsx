import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { encodePathSegments } from '@/helpers';
import { differenceInHours, format, formatDistanceToNow } from 'date-fns';
import React, { memo } from 'react';
import { FileObject } from '@/api/server/files/loadDirectory';
import FileDropdownMenu from '@/components/server/files/FileDropdownMenu';
import { ServerContext } from '@/state/server';
import { NavLink, useRouteMatch } from 'react-router-dom';
import isEqual from 'react-fast-compare';
import SelectFileCheckbox from '@/components/server/files/SelectFileCheckbox';
import { usePermissions } from '@/plugins/usePermissions';
import { join } from 'pathe';
import { bytesToString } from '@/lib/formatters';
import { isSqliteFile } from '@/api/server/files/sqlite';
import { dateLocale } from '@/components/elements/activity/kinds';
import fileIcon, { extensionOf } from '@/components/server/files/fileIcon';
import styles from './style.module.css';

const Clickable: React.FC<{ file: FileObject }> = memo(({ file, children }) => {
    const [canRead] = usePermissions(['file.read']);
    const [canReadContents] = usePermissions(['file.read-content']);
    const directory = ServerContext.useStoreState((state) => state.files.directory);

    const match = useRouteMatch();

    // SQLite databases open in their own viewer, the other files in the text editor.
    const sqlite = file.isFile && isSqliteFile(file.name);

    return (file.isFile && ((!file.isEditable() && !sqlite) || !canReadContents)) || (!file.isFile && !canRead) ? (
        <div className={styles.details}>{children}</div>
    ) : (
        <NavLink
            className={styles.details}
            to={`${match.url}${file.isFile ? (sqlite ? '/sqlite' : '/edit') : ''}#${encodePathSegments(
                join(directory, file.name)
            )}`}
        >
            {children}
        </NavLink>
    );
}, isEqual);

// The name with its extension a little dimmer, like in the Finder.
const Name = ({ file }: { file: FileObject }) => {
    const ext = file.isFile ? extensionOf(file.name) : '';
    if (!ext || ext.length > 10) {
        return <span className={styles.name}>{file.name}</span>;
    }

    return (
        <span className={styles.name}>
            {file.name.slice(0, -(ext.length + 1))}
            <span className={styles.ext}>.{ext}</span>
        </span>
    );
};

const FileObjectRow = ({ file }: { file: FileObject }) => {
    const look = fileIcon(file);
    const recent = Math.abs(differenceInHours(file.modifiedAt, new Date())) <= 48;

    return (
        <div
            className={styles.file_row}
            key={file.name}
            onContextMenu={(e) => {
                e.preventDefault();
                window.dispatchEvent(new CustomEvent(`pterodactyl:files:ctx:${file.key}`, { detail: e.clientX }));
            }}
        >
            <SelectFileCheckbox name={file.name} />
            <Clickable file={file}>
                <span className={styles.icon} style={{ color: look.color }}>
                    <span className={styles.icon_tint} style={{ background: look.color }} />
                    <FontAwesomeIcon icon={look.icon} />
                </span>
                <Name file={file} />
                <span className={styles.size}>{file.isFile ? bytesToString(file.size) : ''}</span>
                <span className={styles.date} title={format(file.modifiedAt, 'PPPp', { locale: dateLocale() })}>
                    {recent
                        ? formatDistanceToNow(file.modifiedAt, { addSuffix: true, locale: dateLocale() })
                        : format(file.modifiedAt, 'd MMM yyyy, HH:mm', { locale: dateLocale() })}
                </span>
            </Clickable>
            <FileDropdownMenu file={file} />
        </div>
    );
};

export default memo(FileObjectRow, (prevProps, nextProps) => {
    /* eslint-disable @typescript-eslint/no-unused-vars */
    const { isArchiveType, isEditable, ...prevFile } = prevProps.file;
    const { isArchiveType: nextIsArchiveType, isEditable: nextIsEditable, ...nextFile } = nextProps.file;
    /* eslint-enable @typescript-eslint/no-unused-vars */

    return isEqual(prevFile, nextFile);
});
