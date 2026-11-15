import React, { useEffect } from 'react';
import { accentHex } from '@/lib/helpers';
import { ServerContext } from '@/state/server';
import { SocketEvent } from '@/components/server/events';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import { Line } from 'react-chartjs-2';
import { lineEffects, useChartTickLabel } from '@/components/server/console/chart';
import { bytesToString, mbToBytes } from '@/lib/formatters';
import { theme } from 'twin.macro';
import ChartBlock from '@/components/server/console/ChartBlock';

const plugins = [lineEffects];

const formatValue = (value: number | null, format: (value: number) => string): string | null =>
    value === null ? null : format(value);

export default () => {
    const status = ServerContext.useStoreState((state) => state.status.value);
    const limits = ServerContext.useStoreState((state) => state.server.data!.limits);

    const cpu = useChartTickLabel('CPU', limits.cpu, '%', 0, accentHex(400));
    const memory = useChartTickLabel(
        'Memory',
        limits.memory,
        (value) => bytesToString(mbToBytes(value)),
        undefined,
        theme('colors.cyan.400')
    );

    useEffect(() => {
        if (status === 'offline') {
            // The lines come down to zero instead of vanishing.
            cpu.settle();
            memory.settle();
        }
    }, [status]);

    useWebsocketEvent(SocketEvent.STATS, (data: string) => {
        let values: any = {};
        try {
            values = JSON.parse(data);
        } catch (e) {
            return;
        }
        cpu.push(values.cpu_absolute);
        memory.push(Math.floor(values.memory_bytes / 1024 / 1024));
    });

    const offline = status === 'offline';

    return (
        <>
            <ChartBlock
                title={'CPU Load'}
                color={accentHex(400)}
                value={offline ? null : formatValue(cpu.latest(), (value) => `${value.toFixed(1)}%`)}
                detail={limits.cpu ? `/ ${limits.cpu}%` : null}
                offline={offline}
            >
                <Line {...cpu.props} plugins={plugins} />
            </ChartBlock>
            <ChartBlock
                title={'Memory'}
                color={theme('colors.cyan.400')}
                value={offline ? null : formatValue(memory.latest(), (value) => bytesToString(mbToBytes(value)))}
                detail={limits.memory ? `/ ${bytesToString(mbToBytes(limits.memory))}` : null}
                offline={offline}
            >
                <Line {...memory.props} plugins={plugins} />
            </ChartBlock>
        </>
    );
};
