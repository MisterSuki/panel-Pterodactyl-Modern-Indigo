import React from 'react';
import classNames from 'classnames';
import styles from '@/components/server/console/style.module.css';

interface ChartBlockProps {
    title: string;
    legend?: React.ReactNode;
    // The current value, shown next to the title.
    value?: string | null;
    // What comes after the value, smaller: the limit ("/ 2 GiB").
    detail?: string | null;
    // The server is off: the chart is dimmed and says so.
    offline?: boolean;
    // Colour of the small dot before the title, matching the line of the chart.
    color?: string;
    children: React.ReactNode;
}

export default ({ title, legend, value, detail, offline, color, children }: ChartBlockProps) => (
    <div className={classNames(styles.chart_container, 'group')}>
        <div className={'flex items-center justify-between gap-3 px-4 pt-3 pb-1'}>
            <div className={'flex items-center gap-2.5 min-w-0'}>
                {color && <span className={'w-2 h-2 rounded-full flex-shrink-0'} style={{ backgroundColor: color }} />}
                <h3 className={'font-header text-sm font-medium text-gray-300'}>{title}</h3>
            </div>
            {legend ? (
                <p className={'flex items-center'}>{legend}</p>
            ) : (
                value && (
                    <span className={'text-sm font-semibold text-gray-50 tabular-nums truncate'}>
                        {value}
                        {detail && <span className={'ml-1 text-xs font-normal text-gray-400'}>{detail}</span>}
                    </span>
                )
            )}
        </div>
        <div className={'relative z-10 px-2 pb-2'} style={{ height: 150 }}>
            <div className={classNames('h-full transition-opacity duration-300', offline && 'opacity-30')}>
                {children}
            </div>
            {offline && (
                <div className={'absolute inset-0 flex items-center justify-center pointer-events-none'}>
                    <span
                        className={'rounded-full border border-gray-600 bg-gray-900/80 px-3 py-1 text-xs text-gray-300'}
                    >
                        Server stopped
                    </span>
                </div>
            )}
        </div>
    </div>
);
