import React from 'react';
import tw from 'twin.macro';

// Lets a visitor who is not signed in pick the language, on the login pages. It is remembered in the browser.
export default () => {
    const languages: Record<string, string> = (window as any).SiteConfiguration?.locales || {};
    const codes = Object.keys(languages);
    if (codes.length < 2) {
        return null;
    }

    const current = (window as any).PterodactylLanguage || (window as any).SiteConfiguration?.locale || 'en';

    return (
        <p css={tw`text-center text-xs mt-3 text-neutral-500`} data-no-translate>
            {codes.map((code, index) => (
                <React.Fragment key={code}>
                    {index > 0 && ' · '}
                    <a
                        href={'#'}
                        onClick={(event) => {
                            event.preventDefault();
                            (window as any).PterodactylSetLanguage?.(code);
                        }}
                        css={[
                            tw`no-underline hover:text-neutral-200`,
                            code === current ? tw`text-neutral-200` : tw`text-neutral-500`,
                        ]}
                    >
                        {languages[code]}
                    </a>
                </React.Fragment>
            ))}
        </p>
    );
};
