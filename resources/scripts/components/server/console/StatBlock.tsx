import React from 'react';
import Icon from '@/components/elements/Icon';
import { IconDefinition } from '@fortawesome/free-solid-svg-icons';
import classNames from 'classnames';
import styles from './style.module.css';
import CopyOnClick from '@/components/elements/CopyOnClick';

interface StatBlockProps {
    title: string;
    copyOnClick?: string;
    color?: string | undefined;
    icon: IconDefinition;
    children: React.ReactNode;
    className?: string;
    // A small trend line drawn behind the value (see Sparkline).
    sparkline?: React.ReactNode;
}

export default ({ title, copyOnClick, icon, color, className, children, sparkline }: StatBlockProps) => {
    return (
        <CopyOnClick text={copyOnClick}>
            <div className={classNames(styles.stat_block, 'pd-neon overflow-hidden', className)}>
                {sparkline}
                <div className={'relative flex flex-col justify-center overflow-hidden w-full'}>
                    <p className={'flex items-center gap-2 leading-tight text-xs text-gray-400'}>
                        <Icon icon={icon} className={'w-3 h-3 text-gray-500'} />
                        <span className={'truncate'}>{title}</span>
                        {color && color !== 'bg-gray-700' && (
                            <span className={classNames('ml-auto w-1.5 h-1.5 rounded-full flex-shrink-0', color)} />
                        )}
                    </p>
                    {/* Plain CSS sizing: the old fit-to-width script measured the page again on every stats update. */}
                    <div
                        className={
                            'w-full mt-1.5 text-lg sm:text-xl leading-7 font-semibold text-gray-50 truncate tabular-nums tracking-tight'
                        }
                    >
                        {children}
                    </div>
                </div>
            </div>
        </CopyOnClick>
    );
};
