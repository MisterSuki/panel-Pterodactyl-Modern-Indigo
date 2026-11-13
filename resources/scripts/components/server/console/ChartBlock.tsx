import React from 'react';
import classNames from 'classnames';
import styles from '@/components/server/console/style.module.css';

interface ChartBlockProps {
    title: string;
    legend?: React.ReactNode;
    // The current value, shown next to the title.
    value?: string | null;
    // Colour of the small dot before the title, matching the line of the chart.
    color?: string;
    children: React.ReactNode;
}

export default ({ title, legend, value, color, children }: ChartBlockProps) => (
    <div className={classNames(styles.chart_container, 'group')}>
        <div className={'flex items-center justify-between gap-3 px-4 pt-3 pb-1'}>
            <div className={'flex items-center gap-2.5 min-w-0'}>
                {color && <span className={'w-2 h-2 rounded-full flex-shrink-0'} style={{ backgroundColor: color }} />}
                <h3 className={'font-header text-sm font-medium text-gray-300'}>{title}</h3>
            </div>
            {legend ? (
                <p className={'flex items-center'}>{legend}</p>
            ) : (
                value && <span className={'text-sm font-semibold text-gray-50 tabular-nums truncate'}>{value}</span>
            )}
        </div>
        <div className={'z-10 px-2 pb-2'}>{children}</div>
    </div>
);
