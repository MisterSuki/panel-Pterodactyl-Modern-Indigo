import React, { useState } from 'react';
import tw from 'twin.macro';
import useSWR from 'swr';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faDownload, faFilm } from '@fortawesome/free-solid-svg-icons';
import http from '@/api/http';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import { ServerContext } from '@/state/server';
import { useFlashKey } from '@/plugins/useFlash';

interface KindPage {
    current: number;
    image: string;
    kinds: { id: number; key: string; name: string; versions: { image: string }[] }[];
}

export const useKinds = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { data } = useSWR<KindPage>(['web-kind', uuid], () =>
        http.get(`/api/client/servers/${uuid}/web/kind`).then(({ data }) => data)
    );

    return { uuid, data };
};

// Emby in the list of the CMS: it is not a PHP application, so "installing" it turns the site into an Emby site
// (its own kind of site), after a confirmation.
export default ({ disabled }: { disabled?: boolean }) => {
    const { uuid, data } = useKinds();
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('server:web');
    const [asking, setAsking] = useState(false);
    const [busy, setBusy] = useState(false);
    const emby = data?.kinds.find((k) => k.key === 'emby');

    if (!emby) return null;

    const apply = () => {
        clearFlashes();
        setBusy(true);
        http.post(`/api/client/servers/${uuid}/web/kind`, { egg: emby.id, image: emby.versions[0]?.image })
            .then(() => window.location.reload())
            .catch((error) => {
                clearAndAddHttpError(error);
                setBusy(false);
                setAsking(false);
            });
    };

    return (
        <div css={tw`flex flex-col rounded-xl border border-white/10 bg-black/20 p-4`}>
            <p css={tw`flex items-center gap-2 text-neutral-50 font-semibold`}>
                <FontAwesomeIcon icon={faFilm} css={tw`text-xs text-neutral-400`} />
                <span>Emby</span>
            </p>
            <p css={tw`text-xs text-neutral-400 mt-1 flex-1`}>
                Media server: your films, series and music in the browser and the Emby apps. The site becomes an Emby
                site.
            </p>
            <Button.Text
                type={'button'}
                size={Button.Sizes.Small}
                css={tw`mt-3`}
                disabled={disabled || busy}
                onClick={() => setAsking(true)}
            >
                <FontAwesomeIcon icon={faDownload} css={tw`mr-1.5`} /> <span>Install</span>
            </Button.Text>
            <Dialog.Confirm
                open={asking}
                title={'Turn the site into Emby?'}
                confirm={busy ? 'Changing...' : 'Install Emby'}
                onClose={() => !busy && setAsking(false)}
                onConfirmed={apply}
            >
                The site is set up again for Emby: its files are kept, Emby is downloaded (a few minutes). Then start
                the site from the Console and open its domain to set Emby up. Give it at least 2 GB of memory.
            </Dialog.Confirm>
        </div>
    );
};

// On an Emby site: where the media go and how to set it up.
export const EmbyHelp = () => {
    const { data } = useKinds();
    const current = data?.kinds.find((k) => k.id === data.current);

    if (!current || current.key !== 'emby') return null;

    return (
        <div css={tw`rounded-2xl bg-neutral-800 border border-white/10 overflow-hidden mt-6`}>
            <div css={tw`px-4 py-3`}>
                <h2 css={tw`flex items-center gap-2 text-base text-neutral-50 font-semibold`}>
                    <FontAwesomeIcon icon={faFilm} css={tw`text-sm text-neutral-400`} />
                    <span>Your Emby server</span>
                </h2>
                <ol css={tw`mt-2 pl-5 list-decimal text-sm text-neutral-300 space-y-1`}>
                    <li>Start the site from the Console (the first start takes a minute).</li>
                    <li>Open the domain of the site: Emby asks for your language and creates your account.</li>
                    <li>
                        Put your films, series and music in the <code>media</code> folder (Files tab or SFTP), then add
                        it as a library in Emby.
                    </li>
                </ol>
                <p css={tw`mt-2 text-xs text-neutral-400`}>
                    Its settings and its library are kept in the data folder. Reinstalling the site from Settings
                    updates Emby and keeps them.
                </p>
            </div>
        </div>
    );
};
