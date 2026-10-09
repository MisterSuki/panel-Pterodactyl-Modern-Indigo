import React, { useEffect, useState } from 'react';
import { ServerContext } from '@/state/server';
import Modal from '@/components/elements/Modal';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import { SocketEvent } from '@/components/server/events';
import { useStoreState } from 'easy-peasy';

// The Minecraft/Java start-up builds "-Xmx{memory}M" from the server's memory limit. When that limit is 0 (unlimited),
// it becomes "-Xmx0M" and the JVM refuses to start. We spot that crash in the console and explain the fix in plain words.
const MATCH = ['invalid maximum heap size', 'could not create the java virtual machine', '-xmx0m'];

const MemoryCrashFeature = () => {
    const [visible, setVisible] = useState(false);

    const status = ServerContext.useStoreState((state) => state.status.value);
    const memory = ServerContext.useStoreState((state) => state.server.data!.limits.memory);
    const internalId = ServerContext.useStoreState((state) => state.server.data!.internalId);
    const { connected, instance } = ServerContext.useStoreState((state) => state.socket);
    const isAdmin = useStoreState((state) => state.user.data!.rootAdmin);

    useEffect(() => {
        if (!connected || !instance || status === 'running') return;

        const listener = (line: string) => {
            if (MATCH.some((p) => line.toLowerCase().includes(p))) {
                setVisible(true);
            }
        };

        instance.addListener(SocketEvent.CONSOLE_OUTPUT, listener);

        return () => {
            instance.removeListener(SocketEvent.CONSOLE_OUTPUT, listener);
        };
    }, [connected, instance, status]);

    return (
        <Modal visible={visible} onDismissed={() => setVisible(false)} closeOnBackground={false}>
            <h2 css={tw`text-2xl mb-4 text-neutral-100`}>The server has no memory limit</h2>
            <p css={tw`mt-2 text-neutral-300`}>
                <span>The server tried to start with a maximum memory of 0 MB, which Java refuses.</span>
            </p>
            <p css={tw`mt-2`}>
                <code css={tw`text-xs text-red-300`}>Invalid maximum heap size: -Xmx0M</code>
            </p>
            {memory === 0 ? (
                isAdmin ? (
                    <p css={tw`mt-4 text-neutral-300`}>
                        <span>
                            Its memory limit is set to 0 (unlimited), but a Minecraft server needs a fixed amount of
                            memory. Set a memory limit for this server (for example 2048 MB), then start it again.
                        </span>
                    </p>
                ) : (
                    <p css={tw`mt-4 text-neutral-300`}>
                        <span>
                            Its memory limit is set to 0 (unlimited), but a Minecraft server needs a fixed amount of
                            memory. Ask your host to set a memory limit for this server.
                        </span>
                    </p>
                )
            ) : (
                <p css={tw`mt-4 text-neutral-300`}>
                    <span>The memory limit looks fine, so check the Startup tab and the egg configuration.</span>
                </p>
            )}
            <div css={tw`mt-8 flex flex-col sm:flex-row justify-end sm:space-x-4 space-y-4 sm:space-y-0`}>
                {isAdmin && memory === 0 && (
                    // eslint-disable-next-line react/jsx-no-target-blank
                    <a href={`/admin/servers/view/${internalId}/build`} target={'_blank'} css={tw`w-full sm:w-auto`}>
                        <Button isSecondary css={tw`w-full`}>
                            Open server settings
                        </Button>
                    </a>
                )}
                <Button onClick={() => setVisible(false)} css={tw`w-full sm:w-auto`}>
                    I understand
                </Button>
            </div>
        </Modal>
    );
};

export default MemoryCrashFeature;
