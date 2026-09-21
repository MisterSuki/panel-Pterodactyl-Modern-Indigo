import React, { useState } from 'react';
import tw from 'twin.macro';
import { State, useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import ContentBox from '@/components/elements/ContentBox';
import Select from '@/components/elements/Select';
import Label from '@/components/elements/Label';
import updateLanguage from '@/api/account/updateLanguage';
import useFlash from '@/plugins/useFlash';

const FLASH_KEY = 'account:language';

// The languages the panel exists in, as given by the page: { en: 'English', fr: 'Français' }.
const availableLanguages = (): Record<string, string> =>
    (window as any).SiteConfiguration?.locales || { en: 'English' };

export default ({ className }: { className?: string }) => {
    const current = useStoreState((state: State<ApplicationStore>) => state.user.data!.language);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const [saving, setSaving] = useState(false);

    const languages = availableLanguages();

    const onChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
        const language = event.currentTarget.value;
        clearFlashes(FLASH_KEY);
        setSaving(true);

        updateLanguage(language)
            // The texts of the page are swapped when it loads, so it is loaded again in the new language.
            .then(() => window.location.reload())
            .catch((error) => {
                clearAndAddHttpError({ key: FLASH_KEY, error });
                setSaving(false);
            });
    };

    return (
        <ContentBox title={'Language'} showFlashes={FLASH_KEY} className={className}>
            <Label htmlFor={'account_language'}>Panel language</Label>
            <Select id={'account_language'} value={current || 'en'} onChange={onChange} disabled={saving}>
                {Object.keys(languages).map((code) => (
                    <option key={code} value={code}>
                        {languages[code]}
                    </option>
                ))}
            </Select>
            <p css={tw`text-xs text-neutral-400 mt-2`}>
                The language is saved on your account and used on every device.
            </p>
        </ContentBox>
    );
};
