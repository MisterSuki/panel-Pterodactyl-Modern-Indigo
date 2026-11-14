import React from 'react';
import tw from 'twin.macro';

// A value with a thin bar under it: white, then amber past 75 %, red past 90 %.
export default ({
    label,
    value,
    detail,
    ratio,
}: {
    label: string;
    value: string;
    detail?: string;
    ratio: number | null;
}) => {
    const percent = ratio === null ? 0 : Math.max(0, Math.min(1, ratio)) * 100;

    return (
        <div css={tw`min-w-0`}>
            <div css={tw`flex items-baseline justify-between gap-2`}>
                <p css={tw`text-xs text-neutral-500`}>{label}</p>
                {detail && <p css={tw`text-xs text-neutral-500 tabular-nums truncate`}>{detail}</p>}
            </div>
            <p css={tw`mt-0.5 font-mono text-sm text-neutral-50 tabular-nums truncate`}>{value}</p>
            <div css={tw`mt-2 h-1 rounded-full bg-neutral-700 overflow-hidden`}>
                <div
                    css={tw`h-full rounded-full transition-all duration-500`}
                    style={{
                        width: `${ratio === null ? 0 : Math.max(2, percent)}%`,
                        background: percent >= 90 ? '#e5484d' : percent >= 75 ? '#f5a524' : '#ededed',
                    }}
                />
            </div>
        </div>
    );
};
