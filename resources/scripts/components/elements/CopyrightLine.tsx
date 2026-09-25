import React from 'react';
import tw from 'twin.macro';

interface CustomCopyright {
    text: string;
    url: string | null;
}

// The line an administrator wrote in Admin > Settings, or null while the default one is used.
const customCopyright = (): CustomCopyright | null => (window as any).PterodactylCopyright || null;

/**
 * The copyright at the bottom of the pages: the custom line when there is one, and otherwise what `fallback` draws.
 */
const CopyrightLine = ({ fallback }: { fallback: React.ReactNode }) => {
    const custom = customCopyright();
    if (!custom) {
        return <>{fallback}</>;
    }

    return custom.url ? (
        <a
            rel={'noopener nofollow noreferrer'}
            href={custom.url}
            target={'_blank'}
            css={tw`no-underline text-neutral-500 hover:text-neutral-300`}
        >
            {custom.text}
        </a>
    ) : (
        <>{custom.text}</>
    );
};

export default CopyrightLine;
