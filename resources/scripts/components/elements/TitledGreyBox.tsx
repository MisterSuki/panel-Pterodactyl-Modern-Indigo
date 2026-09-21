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
    <div css={tw`rounded-xl shadow-card border border-white/5 bg-neutral-800 overflow-hidden`} className={className}>
        <div css={tw`bg-white/[0.03] p-3 border-b border-white/5`}>
            {typeof title === 'string' ? (
                <p css={tw`text-xs uppercase tracking-wide font-medium text-neutral-300`}>
                    {icon && <FontAwesomeIcon icon={icon} css={tw`mr-2 text-primary-400`} />}
                    {title}
                </p>
            ) : (
                title
            )}
        </div>
        <div css={tw`p-3`}>{children}</div>
    </div>
);

export default memo(TitledGreyBox, isEqual);
