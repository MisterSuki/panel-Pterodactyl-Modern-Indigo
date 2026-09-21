import React, { useEffect, useRef } from 'react';
import { ServerContext } from '@/state/server';
import { SocketEvent } from '@/components/server/events';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import { Line } from 'react-chartjs-2';
import { gradientFill, lineEffects, useChart, useChartTickLabel } from '@/components/server/console/chart';
import { bytesToString, mbToBytes } from '@/lib/formatters';
import { theme } from 'twin.macro';
import ChartBlock from '@/components/server/console/ChartBlock';

const plugins = [lineEffects];

const formatValue = (value: number | null, format: (value: number) => string): string | null =>
    value === null ? null : format(value);

export default () => {
    const status = ServerContext.useStoreState((state) => state.status.value);
    const limits = ServerContext.useStoreState((state) => state.server.data!.limits);
    const previous = useRef<Record<'tx' | 'rx', number>>({ tx: -1, rx: -1 });

    const cpu = useChartTickLabel('CPU', limits.cpu, '%', 0, theme('colors.primary.400'));
    const memory = useChartTickLabel(
        'Memory',
        limits.memory,
        (value) => bytesToString(mbToBytes(value)),
        undefined,
        theme('colors.cyan.400')
    );
    const network = useChart('Network', {
        sets: 2,
        // The first line is what goes out, the second what comes in (see the callback below).
        format: (value, index) => `${index === 0 ? '\u2191' : '\u2193'} ${bytesToString(value)}/s`,
        options: {
            scales: {
                y: {
                    ticks: {
                        callback(value) {
                            return bytesToString(typeof value === 'string' ? parseInt(value, 10) : value) + '/s';
                        },
                    },
                },
            },
        },
        callback(opts, index) {
            return {
                ...opts,
                label: !index ? 'Network Out' : 'Network In',
                borderColor: !index ? theme('colors.cyan.400') : theme('colors.yellow.400'),
                backgroundColor: gradientFill(!index ? theme('colors.cyan.400') : theme('colors.yellow.400'), 0.25),
            };
        },
    });

    useEffect(() => {
        if (status === 'offline') {
            // The lines come down to zero instead of vanishing.
            cpu.settle();
            memory.settle();
            network.settle();
            previous.current = { tx: -1, rx: -1 };
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
        network.push([
            previous.current.tx < 0 ? 0 : Math.max(0, values.network.tx_bytes - previous.current.tx),
            previous.current.rx < 0 ? 0 : Math.max(0, values.network.rx_bytes - previous.current.rx),
        ]);

        previous.current = { tx: values.network.tx_bytes, rx: values.network.rx_bytes };
    });

    const offline = status === 'offline';
    const inbound = network.latest(1);
    const outbound = network.latest(0);

    return (
        <>
            <ChartBlock
                title={'CPU Load'}
                color={theme('colors.primary.400')}
                value={offline ? null : formatValue(cpu.latest(), (value) => `${value.toFixed(1)}%`)}
            >
                <Line {...cpu.props} plugins={plugins} />
            </ChartBlock>
            <ChartBlock
                title={'Memory'}
                color={theme('colors.cyan.400')}
                value={offline ? null : formatValue(memory.latest(), (value) => bytesToString(mbToBytes(value)))}
            >
                <Line {...memory.props} plugins={plugins} />
            </ChartBlock>
            <ChartBlock
                title={'Network'}
                color={theme('colors.yellow.400')}
                legend={
                    <>
                        <span className={'flex items-center gap-1.5 mr-3 text-xs text-gray-300 tabular-nums'}>
                            <span className={'w-2 h-2 rounded-full bg-yellow-400'} />
                            {offline || inbound === null ? 'In' : `In ${bytesToString(inbound)}/s`}
                        </span>
                        <span className={'flex items-center gap-1.5 text-xs text-gray-300 tabular-nums'}>
                            <span className={'w-2 h-2 rounded-full bg-cyan-400'} />
                            {offline || outbound === null ? 'Out' : `Out ${bytesToString(outbound)}/s`}
                        </span>
                    </>
                }
            >
                <Line {...network.props} plugins={plugins} />
            </ChartBlock>
        </>
    );
};
