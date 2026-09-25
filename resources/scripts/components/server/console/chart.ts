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

// A chart that follows the mouse: where the cursor is, and how to write a value.
type Hoverable = ChartJS<'line'> & { $hover?: { x: number }; $format?: (value: number, index: number) => string };

interface Point {
    x: number;
    y: number;
}

// The value of a line at a moment, worked out between the two points around it.
const valueAt = (points: Point[], x: number): number | null => {
    if (points.length === 0 || x < points[0].x) {
        return null;
    }
    for (let i = 0; i < points.length - 1; i++) {
        const a = points[i];
        const b = points[i + 1];
        if (x >= a.x && x <= b.x) {
            return b.x === a.x ? b.y : a.y + ((b.y - a.y) * (x - a.x)) / (b.x - a.x);
        }
    }

    return points[points.length - 1].y;
};

const roundedBox = (ctx: CanvasRenderingContext2D, x: number, y: number, w: number, h: number, r: number) => {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
};

// Draws, at the place of the mouse, a dashed line, a dot on every curve and a box that says what each one was worth at
// that moment and how long ago that was. The chart moves on its own, so the values follow it under a still mouse.
const drawHover = (chart: Hoverable) => {
    const hover = chart.$hover;
    const area = chart.chartArea;
    if (!hover || !area || hover.x < area.left || hover.x > area.right) {
        return;
    }

    const moment = chart.scales.x.getValueForPixel(hover.x);
    if (moment === undefined || moment === null) {
        return;
    }

    const rows: { color: string; text: string; y: number }[] = [];
    chart.data.datasets.forEach((dataset, index) => {
        const value = valueAt(dataset.data as unknown as Point[], moment);
        if (value === null) {
            return;
        }
        rows.push({
            color: String(dataset.borderColor),
            text: chart.$format ? chart.$format(value, index) : value.toFixed(1),
            y: chart.scales.y.getPixelForValue(value),
        });
    });
    if (rows.length === 0) {
        return;
    }

    const { ctx } = chart;
    ctx.save();
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.28)';
    ctx.lineWidth = 1;
    ctx.setLineDash([4, 4]);
    ctx.beginPath();
    ctx.moveTo(hover.x, area.top);
    ctx.lineTo(hover.x, area.bottom);
    ctx.stroke();
    ctx.setLineDash([]);

    rows.forEach((row) => {
        ctx.fillStyle = row.color;
        ctx.beginPath();
        ctx.arc(hover.x, row.y, 3.5, 0, Math.PI * 2);
        ctx.fill();
    });

    const seconds = Math.max(0, Math.round((chart.scales.x.max as number) - moment));
    const title = seconds === 0 ? 'now' : `-${seconds} s`;
    const family = String(theme('fontFamily.sans'));
    ctx.font = `600 12px ${family}`;
    const widest = Math.max(...rows.map((row) => ctx.measureText(row.text).width));
    ctx.font = `10px ${family}`;
    const width = Math.max(widest + 26, ctx.measureText(title).width + 20);
    const height = 24 + rows.length * 17;
    const left = hover.x + 14 + width > area.right ? hover.x - 14 - width : hover.x + 14;
    const top = area.top + 2;

    ctx.shadowColor = 'rgba(0, 0, 0, 0.55)';
    ctx.shadowBlur = 14;
    ctx.fillStyle = 'rgba(12, 16, 28, 0.96)';
    roundedBox(ctx, left, top, width, height, 8);
    ctx.fill();
    ctx.shadowBlur = 0;
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.12)';
    ctx.stroke();

    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#94a3b8';
    ctx.fillText(title, left + 10, top + 12);
    ctx.font = `600 12px ${family}`;
    rows.forEach((row, index) => {
        const y = top + 30 + index * 17;
        ctx.fillStyle = row.color;
        ctx.beginPath();
        ctx.arc(left + 13, y, 3.5, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#f1f5f9';
        ctx.fillText(row.text, left + 23, y);
    });
    ctx.restore();
};

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
    afterDraw(chart) {
        drawHover(chart as Hoverable);
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
interface DrawState {
    // Whether something was drawn since the samples were last emptied.
    drawn: boolean;
    // Whether the picture stopped changing: the last value is older than the whole window, so the line is flat.
    settled: boolean;
}

function draw(chart: ChartJS<'line'> | null, samples: Sample[], state: DrawState): void {
    if (!chart) {
        return;
    }

    if (samples.length === 0) {
        // Nothing to show: empty the chart once, and then leave it alone.
        if (state.drawn) {
            chart.data.datasets.forEach((dataset) => (dataset.data = []));
            chart.update('none');
            state.drawn = false;
            state.settled = false;
        }

        return;
    }

    const end = seconds() - DELAY_SECONDS;
    const start = end - WINDOW_SECONDS;

    // A server that stopped sends nothing more. Once its last value has slid out of the window, the flat line that is
    // left is drawn one last time and then left alone (the mouse still moves the values on it, see the hover).
    if (samples[samples.length - 1].t < start - 1) {
        if (state.settled) {
            return;
        }
        state.settled = true;
    } else {
        state.settled = false;
    }

    const x = chart.options.scales?.x;
    if (x) {
        x.min = start;
        x.max = end;
    }

    chart.data.datasets.forEach((dataset, index) => {
        const points: { x: number; y: number }[] = [];
        let next: Sample | undefined;
        let known: number | undefined;
        for (const sample of samples) {
            const value = sample.values[index];
            if (typeof value !== 'number') {
                continue;
            }
            if (sample.t <= end) {
                known = value;
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
        } else if (known !== undefined) {
            // The last value is older than the window: the line stays at that value, all along.
            points.push({ x: start, y: known }, { x: end, y: known });
        }

        dataset.data = points;
    });

    state.drawn = true;
    chart.update('none');
}

interface UseChartOptions {
    // How a value is written in the box that follows the mouse, for the line with that number.
    format?: (value: number, index: number) => string;
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
    const state = useRef<DrawState>({ drawn: false, settled: false });
    const [latestValues, setLatestValues] = useState<(number | null)[]>([]);

    useEffect(() => {
        let frame = window.requestAnimationFrame(function tick() {
            draw(ref.current, samples.current, state.current);
            frame = window.requestAnimationFrame(tick);
        });

        return () => window.cancelAnimationFrame(frame);
    }, []);

    // How the values are written in the box that follows the mouse.
    useEffect(() => {
        if (ref.current) {
            (ref.current as Hoverable).$format = opts?.format;
        }
    });

    // The mouse over the chart: the chart draws the values of the place where it is (see drawHover).
    useEffect(() => {
        const chart = ref.current as Hoverable | null;
        const canvas = chart?.canvas;
        if (!chart || !canvas) {
            return;
        }

        // A chart that has stopped moving is not drawn by itself any more, so it is drawn when the mouse moves.
        const move = (event: MouseEvent) => {
            chart.$hover = { x: event.clientX - canvas.getBoundingClientRect().left };
            if (state.current.settled) {
                chart.draw();
            }
        };
        const leave = () => {
            chart.$hover = undefined;
            if (state.current.settled) {
                chart.draw();
            }
        };
        canvas.addEventListener('mousemove', move);
        canvas.addEventListener('mouseleave', leave);
        canvas.style.cursor = 'crosshair';

        return () => {
            canvas.removeEventListener('mousemove', move);
            canvas.removeEventListener('mouseleave', leave);
        };
    }, []);

    const push = (items: number | null | (number | null)[]) => {
        const values = (Array.isArray(items) ? items : [items]).map((item) =>
            typeof item === 'number' ? Number(item.toFixed(2)) : null
        );

        const now = seconds();
        samples.current.push({ t: now, values });
        state.current.settled = false;
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

    // The server stopped: the lines come down to zero on their own, over the next second, and stay there. Nothing is
    // done for a chart that has never had a value, which would only draw a flat line out of nothing.
    const settle = () => {
        if (samples.current.length > 0) {
            push(Array(opts?.sets || 1).fill(0));
        }
    };

    return { props: { data, options, ref }, push, clear, settle, latest };
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
            format: (value) => (typeof tickLabel === 'function' ? tickLabel(value) : `${value.toFixed(1)}${tickLabel}`),
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
