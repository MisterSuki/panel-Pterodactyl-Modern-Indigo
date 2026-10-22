import {
    faCoffee,
    faCog,
    faDatabase,
    faFileAlt,
    faFileArchive,
    faFileAudio,
    faFileCode,
    faFileImage,
    faFileImport,
    faFilePdf,
    faFileVideo,
    faFolder,
    faScroll,
    faTerminal,
    IconDefinition,
} from '@fortawesome/free-solid-svg-icons';
import { FileObject } from '@/api/server/files/loadDirectory';

export interface FileLook {
    icon: IconDefinition;
    // A colour of the iOS palette, used for the icon and its tinted square.
    color: string;
}

const GROUPS: { ext: string[]; icon: IconDefinition; color: string }[] = [
    { ext: ['json', 'json5', 'jsonc'], icon: faFileCode, color: '#ffd60a' },
    { ext: ['js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx'], icon: faFileCode, color: '#ffd60a' },
    { ext: ['lua', 'luau'], icon: faFileCode, color: '#5e5ce6' },
    { ext: ['py', 'rb', 'php', 'go', 'rs', 'java', 'kt', 'cs', 'c', 'cpp', 'h', 'hpp', 'swift', 'sql'], icon: faFileCode, color: '#64d2ff' },
    { ext: ['html', 'htm', 'xml', 'vue', 'svelte'], icon: faFileCode, color: '#ff9f0a' },
    { ext: ['css', 'scss', 'sass', 'less'], icon: faFileCode, color: '#0a84ff' },
    { ext: ['yml', 'yaml', 'toml', 'ini', 'cfg', 'conf', 'properties', 'env', 'sii', 'cnf'], icon: faCog, color: '#bf5af2' },
    { ext: ['sh', 'bash', 'bat', 'cmd', 'ps1'], icon: faTerminal, color: '#30d158' },
    { ext: ['log', 'out', 'err'], icon: faScroll, color: '#8e8e93' },
    { ext: ['md', 'txt', 'rtf', 'csv'], icon: faFileAlt, color: '#d1d1d6' },
    { ext: ['jar', 'class'], icon: faCoffee, color: '#ff9f0a' },
    { ext: ['zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'bz2', 'xz', 'zst', 'scs', 'pak'], icon: faFileArchive, color: '#bf5af2' },
    { ext: ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'bmp', 'tga', 'dds'], icon: faFileImage, color: '#ff375f' },
    { ext: ['mp3', 'ogg', 'wav', 'flac', 'm4a'], icon: faFileAudio, color: '#ff375f' },
    { ext: ['mp4', 'webm', 'mkv', 'mov', 'avi'], icon: faFileVideo, color: '#ff375f' },
    { ext: ['db', 'sqlite', 'sqlite3', 'db3', 'mdb', 'mv.db'], icon: faDatabase, color: '#30b0c7' },
    { ext: ['pdf'], icon: faFilePdf, color: '#ff453a' },
];

export const extensionOf = (name: string): string => {
    const dot = name.lastIndexOf('.');

    return dot > 0 ? name.slice(dot + 1).toLowerCase() : '';
};

/**
 * The icon and the colour of a row of the file manager, from what the file is (its extension).
 */
export default (file: FileObject): FileLook => {
    if (!file.isFile) {
        return { icon: faFolder, color: 'rgb(var(--pd-accent-400))' };
    }
    if (file.isSymlink) {
        return { icon: faFileImport, color: '#8e8e93' };
    }
    const ext = extensionOf(file.name);
    const group = GROUPS.find((g) => g.ext.includes(ext));
    if (group) {
        return { icon: group.icon, color: group.color };
    }

    return { icon: file.isArchiveType() ? faFileArchive : faFileAlt, color: file.isArchiveType() ? '#bf5af2' : '#aeaeb2' };
};

/**
 * The look of a file from its name only (the editor does not have the whole file object).
 */
export const lookForName = (name: string): FileLook => {
    const group = GROUPS.find((g) => g.ext.includes(extensionOf(name)));

    return group ? { icon: group.icon, color: group.color } : { icon: faFileAlt, color: '#aeaeb2' };
};
