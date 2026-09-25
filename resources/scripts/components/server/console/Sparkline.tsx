import React, { useMemo } from 'react';

interface Props {
    // The values over time, oldest first. Fewer than two points draws nothing.
    points: number[];
    color: string;
    // The top of the scale (a limit). Left out, the line fills to its own highest value.
    max?: number | null;
}

// A tiny trend line drawn behind a number, so the last minute or so is visible at a glance without scrolling to the big
// charts. Plain SVG: no chart library, nothing measured on every update.
export default ({ points, color, max }: Props) => {
    const W = 100;
    const H = 28;
    const id = useMemo(() => 'spark-' + Math.random().toString(36).slice(2), []);

    const path = useMemo(() => {
        if (points.length < 2) {
            return null;
        }
        const top = max && max > 0 ? max : Math.max(...points, 1);
        const step = W / (points.length - 1);
        const coords = points.map((value, index) => ({
            x: index * step,
            y: H - 2 - (Math.min(value, top) / top) * (H - 4),
        }));

        // A smooth line through the points (Catmull-Rom turned into bezier curves), so a reading every couple of seconds
        // reads as a curve instead of a zigzag, like the big charts.
        const tension = 0.5;
        let line = `M${coords[0].x.toFixed(2)},${coords[0].y.toFixed(2)}`;
        for (let i = 0; i < coords.length - 1; i++) {
            const p0 = coords[i - 1] || coords[i];
            const p1 = coords[i];
            const p2 = coords[i + 1];
            const p3 = coords[i + 2] || p2;
            const c1x = p1.x + ((p2.x - p0.x) / 6) * tension;
            const c1y = p1.y + ((p2.y - p0.y) / 6) * tension;
            const c2x = p2.x - ((p3.x - p1.x) / 6) * tension;
            const c2y = p2.y - ((p3.y - p1.y) / 6) * tension;
            line += ` C${c1x.toFixed(2)},${c1y.toFixed(2)} ${c2x.toFixed(2)},${c2y.toFixed(2)} ${p2.x.toFixed(
                2
            )},${p2.y.toFixed(2)}`;
        }
        const area = `${line} L${W},${H} L0,${H} Z`;

        return { line, area };
    }, [points, max]);

    if (!path) {
        return null;
    }

    return (
        <svg
            viewBox={`0 0 ${W} ${H}`}
            preserveAspectRatio={'none'}
            className={'absolute inset-x-0 bottom-0 w-full h-7 opacity-40 pointer-events-none'}
            aria-hidden={'true'}
        >
            <defs>
                <linearGradient id={id} x1={'0'} y1={'0'} x2={'0'} y2={'1'}>
                    <stop offset={'0%'} stopColor={color} stopOpacity={0.35} />
                    <stop offset={'100%'} stopColor={color} stopOpacity={0} />
                </linearGradient>
            </defs>
            <path d={path.area} fill={`url(#${id})`} />
            <path d={path.line} fill={'none'} stroke={color} strokeWidth={1.5} vectorEffect={'non-scaling-stroke'} />
        </svg>
    );
};
