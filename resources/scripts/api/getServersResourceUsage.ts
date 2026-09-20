import http from '@/api/http';
import { ServerStats } from '@/api/server/getServerResourceUsage';

// The live usage of several servers in one request, by server uuid. Servers that could not be reached are left out.
export default (uuids: string[]): Promise<Record<string, ServerStats>> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/resources', { params: { servers: uuids.join(',') } })
            .then(({ data }) => {
                const result: Record<string, ServerStats> = {};
                Object.keys(data.data || {}).forEach((uuid) => {
                    const attributes = data.data[uuid];
                    result[uuid] = {
                        status: attributes.current_state,
                        isSuspended: attributes.is_suspended,
                        memoryUsageInBytes: attributes.resources.memory_bytes,
                        cpuUsagePercent: attributes.resources.cpu_absolute,
                        diskUsageInBytes: attributes.resources.disk_bytes,
                        networkRxInBytes: attributes.resources.network_rx_bytes,
                        networkTxInBytes: attributes.resources.network_tx_bytes,
                        uptime: attributes.resources.uptime,
                    };
                });

                resolve(result);
            })
            .catch(reject);
    });
};
