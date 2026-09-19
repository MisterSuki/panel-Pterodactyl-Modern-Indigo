import React from 'react';
import classNames from 'classnames';
import styles from '@/components/server/console/style.module.css';

interface ChartBlockProps {
    title: string;
    legend?: React.ReactNode;
    // The current value, shown next to the title.
    value?: string | null;
    children: React.ReactNode;
}

export default ({ title, legend, value, children }: ChartBlockProps) => (
    <div className={classNames(styles.chart_container, 'group')}>
        <div className={'flex items-center justify-between px-4 py-2'}>
            <div className={'flex items-baseline gap-2 min-w-0'}>
                <h3 className={'font-header font-medium transition-colors duration-100 group-hover:text-gray-50'}>
                    {title}
                </h3>
                {value && <span className={'text-xs font-medium text-gray-400 tabular-nums truncate'}>{value}</span>}
            </div>
            {legend && <p className={'text-sm flex items-center'}>{legend}</p>}
        </div>
        <div className={'z-10 ml-2 pr-2 pb-2'}>{children}</div>
    </div>
);
