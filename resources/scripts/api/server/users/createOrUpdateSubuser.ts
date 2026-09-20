import http from '@/api/http';
import { rawDataToServerSubuser } from '@/api/server/users/getServerSubusers';
import { Subuser } from '@/state/server/subusers';

// A new subuser is invited by email, or by the Discord ID of an account that already exists.
interface Params {
    email?: string;
    discordId?: string;
    permissions: string[];
}

export default (uuid: string, params: Params, subuser?: Subuser): Promise<Subuser> => {
    return new Promise((resolve, reject) => {
        const { discordId, ...rest } = params;

        http.post(`/api/client/servers/${uuid}/users${subuser ? `/${subuser.uuid}` : ''}`, {
            ...rest,
            ...(discordId ? { discord_id: discordId } : {}),
        })
            .then((data) => resolve(rawDataToServerSubuser(data.data)))
            .catch(reject);
    });
};
