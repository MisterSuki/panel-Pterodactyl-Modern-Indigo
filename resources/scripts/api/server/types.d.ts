export type ServerStatus =
    | 'installing'
    | 'install_failed'
    | 'reinstall_failed'
    | 'suspended'
    | 'restoring_backup'
    | null;

export interface ServerBackup {
    uuid: string;
    isSuccessful: boolean;
    isLocked: boolean;
    name: string;
    ignoredFiles: string;
    checksum: string;
    bytes: number;
    createdAt: Date;
    completedAt: Date | null;
}

export interface ServerEggVariable {
    name: string;
    description: string;
    envVariable: string;
    defaultValue: string;
    serverValue: string | null;
    isEditable: boolean;
    rules: string[];
}

export type BackupFrequency = '6h' | '12h' | '24h' | '7d';

export interface ServerBackupPlan {
    enabled: boolean;
    frequency: BackupFrequency;
    hour: number | null;
    keep: number;
    ignored: string | null;
    nextRunAt: Date | null;
    lastRunAt: Date | null;
    lastError: string | null;
}
