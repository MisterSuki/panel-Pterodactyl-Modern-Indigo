import {
    Chart as ChartJS,
    ChartData,
    ChartDataset,
    ChartOptions,
    Filler,
    LinearScale,
    LineElement,
    PointElement,
    Plugin,
} from 'chart.js';
import { DeepPartial } from 'ts-essentials';
import { useEffect, useMemo, useRef, useState } from 'react';
import { deepmerge } from 'deepmerge-ts';
import { theme } from 'twin.macro';
import { hexToRgba } from '@/lib/helpers';

ChartJS.register(LineElement, PointElement, Filler, LinearScale);

// How many seconds of history a chart shows, and how far behind "now" it is drawn. The stats reach the browser about
// once a second, so drawing a little in the past lets the lines glide the whole time instead of jumping once per
// second: the newest point is always already known, and the tip of the line is worked out between the last two.
const WINDOW_SECONDS = 19;
const DELAY_SECONDS = 1.6;

const options: ChartOptions<'line'> = {
    responsive: true,
    animation: false,
    // The points are given as {x, y} with the time as x, so Chart.js has nothing to parse or sort.
    parsing: false,
    normalized: true,
    plugins: {
        legend: { display: false },
        title: { display: false },
        tooltip: { enabled: false },
    },
    layout: {
        padding: { top: 6, right: 12, bottom: 0, left: 0 },
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
                color: 'rgba(148, 163, 184, 0.10)',
                drawBorder: false,
                borderDash: [3, 5],
            },
            ticks: {
                display: true,
                count: 3,
                padding: 8,
                color: theme('colors.gray.400'),
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

// Gives every line a soft glow and puts a dot with a halo on its latest value, so the eye lands on
// "now". Nothing is drawn while the chart is still empty (those points are stored as -5).
const lineEffects: Plugin<'line'> = {
    id: 'lineEffects',
    beforeDatasetDraw(chart, args) {
        const dataset = chart.data.datasets[args.index];
        chart.ctx.save();
        chart.ctx.shadowColor = hexToRgba(String(dataset.borderColor), 0.55);
        chart.ctx.shadowBlur = 10;
    },
    afterDatasetDraw(chart, args) {
        chart.ctx.restore();

        const dataset = chart.data.datasets[args.index];
        const last = args.meta.data[args.meta.data.length - 1];
        const point = dataset.data[dataset.data.length - 1] as unknown;
        const value = point !== null && typeof point === 'object' ? (point as { y: number }).y : point;
        if (!last || typeof value !== 'number' || value < 0) {
            return;
        }

        const { ctx } = chart;
        const color = String(dataset.borderColor);
        ctx.save();
        ctx.fillStyle = hexToRgba(color, 0.18);
        ctx.beginPath();
        ctx.arc(last.x, last.y, 8, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = color;
        ctx.beginPath();
        ctx.arc(last.x, last.y, 3.5, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    },
};

function getOptions(opts?: DeepPartial<ChartOptions<'line'>> | undefined): ChartOptions<'line'> {
    return deepmerge(options, opts || {});
}

type ChartDatasetCallback = (value: ChartDataset<'line'>, index: number) => ChartDataset<'line'>;

function getEmptyData(label: string, sets = 1, callback?: ChartDatasetCallback | undefined): ChartData<'line'> {
    const next = callback || ((value) => value);

    return {
        datasets: Array(sets)
            .fill(0)
            .map((_, index) =>
                next(
                    {
                        fill: true,
                        label,
                        data: [],
                        borderColor: theme('colors.primary.400'),
                        backgroundColor: gradientFill(theme('colors.primary.400')),
                    },
                    index
                )
            ),
    };
}

interface Sample {
    // When the value arrived, in seconds.
    t: number;
    values: (number | null)[];
}

const seconds = (): number => performance.now() / 1000;

// Draws the window of time that is on screen: the values that arrived, and a last point at the very edge whose value is
// worked out between the two values around that moment. Called once per screen refresh.
function draw(chart: ChartJS<'line'> | null, samples: Sample[], drawn: { current: boolean }): void {
    if (!chart) {
        return;
    }

    if (samples.length === 0) {
        // Nothing to show: empty the chart once, and then leave it alone.
        if (drawn.current) {
            chart.data.datasets.forEach((dataset) => (dataset.data = []));
            chart.update('none');
            drawn.current = false;
        }

        return;
    }

    const end = seconds() - DELAY_SECONDS;
    const start = end - WINDOW_SECONDS;

    const x = chart.options.scales?.x;
    if (x) {
        x.min = start;
        x.max = end;
    }

    chart.data.datasets.forEach((dataset, index) => {
        const points: { x: number; y: number }[] = [];
        let next: Sample | undefined;
        for (const sample of samples) {
            const value = sample.values[index];
            if (typeof value !== 'number') {
                continue;
            }
            if (sample.t <= end) {
                // One value before the left edge is kept, so the line starts at the edge and not after it.
                if (sample.t >= start - 3) {
                    points.push({ x: sample.t, y: value });
                }
            } else if (!next) {
                next = sample;
            }
        }

        const last = points[points.length - 1];
        if (last) {
            const upcoming = next?.values[index];
            const tip =
                next && typeof upcoming === 'number'
                    ? last.y + (upcoming - last.y) * Math.min(1, (end - last.x) / Math.max(0.001, next.t - last.x))
                    : last.y;
            points.push({ x: end, y: tip });
        }

        dataset.data = points;
    });

    drawn.current = true;
    chart.update('none');
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
    // The chart is given this object once and never a new one: from then on the lines are moved straight on the
    // chart, sixty times a second, without React drawing the component again.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    const data = useMemo(() => getEmptyData(label, opts?.sets || 1, opts?.callback), []);
    const ref = useRef<ChartJS<'line'>>(null);
    const samples = useRef<Sample[]>([]);
    const drawn = useRef(false);
    const [latestValues, setLatestValues] = useState<(number | null)[]>([]);

    useEffect(() => {
        let frame = window.requestAnimationFrame(function tick() {
            draw(ref.current, samples.current, drawn);
            frame = window.requestAnimationFrame(tick);
        });

        return () => window.cancelAnimationFrame(frame);
    }, []);

    const push = (items: number | null | (number | null)[]) => {
        const values = (Array.isArray(items) ? items : [items]).map((item) =>
            typeof item === 'number' ? Number(item.toFixed(2)) : null
        );

        const now = seconds();
        samples.current.push({ t: now, values });
        // Only what can still be seen is kept.
        while (samples.current.length > 0 && samples.current[0].t < now - WINDOW_SECONDS - DELAY_SECONDS - 3) {
            samples.current.shift();
        }
        setLatestValues(values);
    };

    const clear = () => {
        samples.current = [];
        setLatestValues([]);
    };

    // The last value that was pushed, or null while the chart is empty.
    const latest = (index = 0): number | null => {
        const value = latestValues[index];

        return typeof value === 'number' && value >= 0 ? value : null;
    };

    return { props: { data, options, ref }, push, clear, latest };
}

function useChartTickLabel(
    label: string,
    max: number,
    tickLabel: string | ((value: number) => string),
    roundTo?: number,
    color?: string
) {
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
                                const number = Number(value);
                                if (typeof tickLabel === 'function') {
                                    return tickLabel(number);
                                }

                                return `${roundTo !== undefined ? number.toFixed(roundTo) : number}${tickLabel}`;
                            },
                        },
                    },
                },
            },
        },
        [max]
    );
}

export { useChart, useChartTickLabel, getOptions, getEmptyData, gradientFill, lineEffects };
