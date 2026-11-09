import React, { memo } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { IconProp } from '@fortawesome/fontawesome-svg-core';
import tw from 'twin.macro';
import isEqual from 'react-fast-compare';

interface Props {
    icon?: IconProp;
    title: string | React.ReactNode;
    className?: string;
    children: React.ReactNode;
}

const TitledGreyBox = ({ icon, title, children, className }: Props) => (
    <div
        css={tw`relative rounded-xl shadow-card border border-white/5 bg-neutral-800 overflow-hidden`}
        className={['lg-rim', className].filter(Boolean).join(' ')}
    >
        <div css={tw`px-4 pt-3.5 pb-3 border-b border-white/[0.06]`}>
            {typeof title === 'string' ? (
                <p css={tw`flex items-center gap-2.5 text-[0.9375rem] font-semibold text-neutral-50`}>
                    {icon && (
                        <span
                            css={tw`flex items-center justify-center w-6 h-6 rounded-md text-white bg-primary-500`}
                            style={{ boxShadow: 'inset 0 1px 0 rgba(255,255,255,0.25)' }}
                        >
                            <FontAwesomeIcon icon={icon} css={tw`text-xs`} />
                        </span>
                    )}
                    {title}
                </p>
            ) : (
                title
            )}
        </div>
        <div css={tw`p-4`}>{children}</div>
    </div>
);

export default memo(TitledGreyBox, isEqual);
