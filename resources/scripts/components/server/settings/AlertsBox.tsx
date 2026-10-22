import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCircleNotch, faPaperPlane, faSave } from '@fortawesome/free-solid-svg-icons';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { Button } from '@/components/elements/button/index';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import { AlertSettings, getAlerts, saveAlerts, testAlert } from '@/api/server/insights';

const EVENTS: { key: keyof AlertSettings['events']; label: string; help: string }[] = [
    { key: 'crash', label: 'Crash', help: 'The server stops by itself, or crashes and is restarted.' },
    { key: 'memory', label: 'Memory almost full', help: '90 % of the memory is used.' },
    { key: 'disk', label: 'Disk almost full', help: '90 % of the disk space is used.' },
    { key: 'start', label: 'Server started', help: 'The server has started.' },
    { key: 'stop', label: 'Server stopped', help: 'The server was stopped from the panel.' },
];

const Toggle = ({ on, onChange }: { on: boolean; onChange: () => void }) => (
    <button
        type={'button'}
        onClick={onChange}
        css={[
            tw`relative inline-flex flex-shrink-0 w-11 h-6 rounded-full transition-colors duration-200 focus:outline-none`,
            on ? tw`bg-green-500` : tw`bg-white/20`,
        ]}
    >
        <span
            css={tw`absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform duration-200`}
            style={{ transform: `translateX(${on ? 20 : 0}px)` }}
        />
    </button>
);

/**
 * Which alerts the server sends, to the bell of the panel and to a Discord channel (with a webhook).
 */
export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const [settings, setSettings] = useState<AlertSettings | null>(null);
    const [webhook, setWebhook] = useState('');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        getAlerts(uuid)
            .then(setSettings)
            .catch((error) => clearAndAddHttpError({ key: 'settings:alerts', error }));
    }, [uuid]);

    if (!settings) {
        return (
            <TitledGreyBox title={'Alerts'} css={tw`mb-6 md:mb-10`}>
                <div css={tw`flex justify-center py-6 text-neutral-400`}>
                    <FontAwesomeIcon icon={faCircleNotch} spin />
                </div>
            </TitledGreyBox>
        );
    }

    const save = (removeWebhook = false) => {
        clearFlashes('settings:alerts');
        setBusy(true);
        saveAlerts(uuid, { events: settings.events, webhook: removeWebhook ? '' : webhook, keepWebhook: !removeWebhook })
            .then((next) => {
                setSettings(next);
                setWebhook('');
                addFlash({ key: 'settings:alerts', type: 'success', message: 'The alerts were saved.' });
            })
            .catch((error) => clearAndAddHttpError({ key: 'settings:alerts', error }))
            .then(() => setBusy(false));
    };

    const test = () => {
        clearFlashes('settings:alerts');
        setBusy(true);
        testAlert(uuid)
            .then(() => addFlash({ key: 'settings:alerts', type: 'success', message: 'A test message was sent to Discord.' }))
            .catch((error) => clearAndAddHttpError({ key: 'settings:alerts', error }))
            .then(() => setBusy(false));
    };

    const flip = (key: keyof AlertSettings['events']) =>
        setSettings({ ...settings, events: { ...settings.events, [key]: !settings.events[key] } });

    return (
        <TitledGreyBox title={'Alerts'} css={tw`mb-6 md:mb-10`}>
            <div css={tw`space-y-3`}>
                {EVENTS.map((event) => (
                    <div key={event.key} css={tw`flex items-center gap-3`}>
                        <div css={tw`flex-1 min-w-0`}>
                            <p css={tw`text-sm text-neutral-100 font-medium`}>{event.label}</p>
                            <p css={tw`text-xs text-neutral-400`}>{event.help}</p>
                        </div>
                        <Toggle on={settings.events[event.key]} onChange={() => flip(event.key)} />
                    </div>
                ))}
                <div css={tw`flex items-center gap-3 pt-3 border-t border-white/10`}>
                    <div css={tw`flex-1 min-w-0`}>
                        <p css={tw`text-sm text-neutral-100 font-medium`}>Bell of the panel</p>
                        <p css={tw`text-xs text-neutral-400`}>The owner and the users of the server get them in the panel.</p>
                    </div>
                    <Toggle on={settings.events.bell} onChange={() => flip('bell')} />
                </div>
                <div css={tw`pt-3 border-t border-white/10`}>
                    <p css={tw`text-sm text-neutral-100 font-medium mb-1`}>Discord</p>
                    <p css={tw`text-xs text-neutral-400 mb-2`}>
                        Discord channel → Edit channel → Integrations → Webhooks → New webhook → Copy webhook URL.
                    </p>
                    <input
                        type={'password'}
                        autoComplete={'off'}
                        value={webhook}
                        onChange={(e) => setWebhook(e.currentTarget.value.trim())}
                        placeholder={settings.webhookSet ? settings.webhookHint || '' : 'https://discord.com/api/webhooks/…'}
                        css={tw`w-full rounded-xl bg-black/30 border border-white/10 px-3 py-2.5 text-sm text-neutral-100 focus:outline-none focus:border-primary-500`}
                    />
                    {settings.webhookSet && (
                        <p css={tw`text-xs text-green-300 mt-1.5`}>
                            <span>A webhook is saved.</span>{' '}
                            <button
                                type={'button'}
                                css={tw`underline text-neutral-300 hover:text-white`}
                                onClick={() => save(true)}
                            >
                                Remove it
                            </button>
                        </p>
                    )}
                </div>
            </div>
            <div css={tw`flex flex-wrap justify-end gap-2 mt-4`}>
                {settings.webhookSet && (
                    <Button.Text type={'button'} onClick={test} disabled={busy}>
                        <FontAwesomeIcon icon={faPaperPlane} css={tw`mr-1.5`} /> <span>Send a test</span>
                    </Button.Text>
                )}
                <Button type={'button'} onClick={() => save()} disabled={busy}>
                    <FontAwesomeIcon icon={busy ? faCircleNotch : faSave} spin={busy} css={tw`mr-1.5`} /> <span>Save</span>
                </Button>
            </div>
        </TitledGreyBox>
    );
};
