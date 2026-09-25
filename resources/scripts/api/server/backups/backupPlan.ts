import useSWR from 'swr';
import http from '@/api/http';
import { BackupFrequency, ServerBackupPlan } from '@/api/server/types';
import { rawDataToServerBackupPlan } from '@/api/transformers';

export interface BackupPlanValues {
    enabled: boolean;
    frequency: BackupFrequency;
    hour: number;
    keep: number;
    ignored: string;
}

// The automatic backups of a server. The plan is null while none was ever set up.
export const useBackupPlan = (uuid: string) =>
    useSWR<ServerBackupPlan | null>(['server:backup-plan', uuid], async () => {
        const { data } = await http.get(`/api/client/servers/${uuid}/backups/auto`);

        return data.attributes ? rawDataToServerBackupPlan(data.attributes) : null;
    });

export const saveBackupPlan = async (uuid: string, values: BackupPlanValues): Promise<ServerBackupPlan> => {
    const { data } = await http.put(`/api/client/servers/${uuid}/backups/auto`, {
        enabled: values.enabled,
        frequency: values.frequency,
        hour: values.frequency === '6h' || values.frequency === '12h' ? null : values.hour,
        keep: values.keep,
        ignored: values.ignored,
    });

    return rawDataToServerBackupPlan(data.attributes);
};
