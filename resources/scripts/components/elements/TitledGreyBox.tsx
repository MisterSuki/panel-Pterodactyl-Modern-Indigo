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
        css={tw`relative rounded-2xl border border-neutral-700 bg-neutral-800 overflow-hidden`}
        className={['lg-rim', className].filter(Boolean).join(' ')}
    >
        <div css={tw`px-4 py-3 border-b border-neutral-700`}>
            {typeof title === 'string' ? (
                <p css={tw`flex items-center gap-2.5 text-sm font-semibold text-neutral-50`}>
                    {icon && <FontAwesomeIcon icon={icon} css={tw`text-neutral-400 text-xs`} />}
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
