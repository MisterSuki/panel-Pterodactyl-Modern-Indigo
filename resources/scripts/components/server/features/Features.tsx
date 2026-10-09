import React, { lazy, useMemo } from 'react';
import features from './index';
import { getObjectKeys } from '@/lib/objects';

type ListItems = [string, React.ComponentType][];

// Watches for the "-Xmx0M" start-up crash. Not tied to a single egg feature, so it loads for any Java/Minecraft server.
const MemoryCrashFeature = lazy(() => import('@feature/MemoryCrashFeature'));

export default ({ enabled }: { enabled: string[] }) => {
    const mapped: ListItems = useMemo(() => {
        return getObjectKeys(features)
            .filter((key) => enabled.map((v) => v.toLowerCase()).includes(key.toLowerCase()))
            .reduce((arr, key) => [...arr, [key, features[key]]], [] as ListItems);
    }, [enabled]);

    const isJava = useMemo(
        () => enabled.map((v) => v.toLowerCase()).some((v) => v === 'eula' || v === 'java_version'),
        [enabled]
    );

    return (
        <React.Suspense fallback={null}>
            {mapped.map(([key, Component]) => (
                <Component key={key} />
            ))}
            {isJava && <MemoryCrashFeature />}
        </React.Suspense>
    );
};
