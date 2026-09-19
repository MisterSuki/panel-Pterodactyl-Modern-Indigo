import {
    Chart as ChartJS,
    ChartData,
    ChartDataset,
    ChartOptions,
    Filler,
    LinearScale,
    LineElement,
    PointElement,
} from 'chart.js';
import { DeepPartial } from 'ts-essentials';
import { useMemo, useState } from 'react';
import { deepmerge } from 'deepmerge-ts';
import { theme } from 'twin.macro';
import { hexToRgba } from '@/lib/helpers';

ChartJS.register(LineElement, PointElement, Filler, LinearScale);

const options: ChartOptions<'line'> = {
    responsive: true,
    animation: false,
    plugins: {
        legend: { display: false },
        title: { display: false },
        tooltip: { enabled: false },
    },
    layout: {
        padding: 0,
    },
    scales: {
        x: {
            min: 0,
            max: 19,
            type: 'linear',
            grid: {
                display: false,
                drawBorder: false,
            },
            ticks: {
                display: false,
            },
        },
        y: {
            min: 0,
            type: 'linear',
            grid: {
                display: true,
                color: 'rgba(148, 163, 184, 0.08)',
                drawBorder: false,
            },
            ticks: {
                display: true,
                count: 3,
                color: theme('colors.gray.200'),
                font: {
                    family: theme('fontFamily.sans'),
                    size: 11,
                    weight: '400',
                },
            },
        },
    },
    elements: {
        point: {
            radius: 0,
        },
        line: {
            tension: 0.35,
            borderWidth: 2,
            borderCapStyle: 'round',
            borderJoinStyle: 'round',
        },
    },
};

// Fades the area under a line from the line colour down to nothing. It is a function so that
// the gradient is rebuilt to the real size of the chart every time it is drawn.
function gradientFill(color: string, top = 0.38): (context: { chart: ChartJS }) => CanvasGradient | string {
    return ({ chart }) => {
        const { ctx, chartArea } = chart;
        if (!chartArea) {
            return hexToRgba(color, 0.15);
        }

        const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
        gradient.addColorStop(0, hexToRgba(color, top));
        gradient.addColorStop(1, hexToRgba(color, 0));

        return gradient;
    };
}

function getOptions(opts?: DeepPartial<ChartOptions<'line'>> | undefined): ChartOptions<'line'> {
    return deepmerge(options, opts || {});
}

type ChartDatasetCallback = (value: ChartDataset<'line'>, index: number) => ChartDataset<'line'>;

function getEmptyData(label: string, sets = 1, callback?: ChartDatasetCallback | undefined): ChartData<'line'> {
    const next = callback || ((value) => value);

    return {
        labels: Array(20)
            .fill(0)
            .map((_, index) => index),
        datasets: Array(sets)
            .fill(0)
            .map((_, index) =>
                next(
                    {
                        fill: true,
                        label,
                        data: Array(20).fill(-5),
                        borderColor: theme('colors.primary.400'),
                        backgroundColor: gradientFill(theme('colors.primary.400')),
                    },
                    index
                )
            ),
    };
}

interface UseChartOptions {
    sets: number;
    options?: DeepPartial<ChartOptions<'line'>> | number | undefined;
    callback?: ChartDatasetCallback | undefined;
}

function useChart(label: string, opts?: UseChartOptions, deps: unknown[] = []) {
    // The options never change between two stats updates. Building them once keeps the chart from
    // re-reading its whole configuration every second, and keeps the same object between renders.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    const options = useMemo(
        () =>
            getOptions(
                typeof opts?.options === 'number'
                    ? { scales: { y: { min: 0, suggestedMax: opts.options } } }
                    : opts?.options
            ),
        deps
    );
    const [data, setData] = useState(() => getEmptyData(label, opts?.sets || 1, opts?.callback));

    const push = (items: number | null | (number | null)[]) =>
        setData((state) => {
            const values = Array.isArray(items) ? items : [items];

            return {
                ...state,
                datasets: state.datasets.map((dataset, index) => {
                    const item = values[index];

                    return {
                        ...dataset,
                        data: [...dataset.data.slice(1), typeof item === 'number' ? Number(item.toFixed(2)) : item],
                    } as ChartDataset<'line'>;
                }),
            };
        });

    const clear = () =>
        setData((state) => ({
            ...state,
            datasets: state.datasets.map((dataset) => ({ ...dataset, data: Array(20).fill(-5) })),
        }));

    // The last value that was pushed, or null while the chart is empty.
    const latest = (index = 0): number | null => {
        const value = data.datasets[index]?.data[data.datasets[index].data.length - 1];

        return typeof value === 'number' && value >= 0 ? value : null;
    };

    return { props: { data, options }, push, clear, latest };
}

function useChartTickLabel(label: string, max: number, tickLabel: string, roundTo?: number, color?: string) {
    return useChart(
        label,
        {
            sets: 1,
            callback: color
                ? (opts) => ({ ...opts, borderColor: color, backgroundColor: gradientFill(color) })
                : undefined,
            options: {
                scales: {
                    y: {
                        suggestedMax: max,
                        ticks: {
                            callback(value) {
                                return `${roundTo ? Number(value).toFixed(roundTo) : value}${tickLabel}`;
                            },
                        },
                    },
                },
            },
        },
        [max]
    );
}

export { useChart, useChartTickLabel, getOptions, getEmptyData, gradientFill };
