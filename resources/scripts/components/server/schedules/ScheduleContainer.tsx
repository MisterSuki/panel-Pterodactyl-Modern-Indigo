import React, { useEffect, useState } from 'react';
import getServerSchedules from '@/api/server/schedules/getServerSchedules';
import { ServerContext } from '@/state/server';
import Spinner from '@/components/elements/Spinner';
import { useHistory, useRouteMatch } from 'react-router-dom';
import FlashMessageRender from '@/components/FlashMessageRender';
import ScheduleRow from '@/components/server/schedules/ScheduleRow';
import { httpErrorToHuman } from '@/api/http';
import EditScheduleModal from '@/components/server/schedules/EditScheduleModal';
import Can from '@/components/elements/Can';
import useFlash from '@/plugins/useFlash';
import tw from 'twin.macro';
import GreyRowBox from '@/components/elements/GreyRowBox';
import { Button } from '@/components/elements/button/index';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCalendarAlt, faPlus } from '@fortawesome/free-solid-svg-icons';

export default () => {
    const match = useRouteMatch();
    const history = useHistory();

    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, addError } = useFlash();
    const [loading, setLoading] = useState(true);
    const [visible, setVisible] = useState(false);

    const schedules = ServerContext.useStoreState((state) => state.schedules.data);
    const setSchedules = ServerContext.useStoreActions((actions) => actions.schedules.setSchedules);

    useEffect(() => {
        clearFlashes('schedules');
        getServerSchedules(uuid)
            .then((schedules) => setSchedules(schedules))
            .catch((error) => {
                addError({ message: httpErrorToHuman(error), key: 'schedules' });
                console.error(error);
            })
            .then(() => setLoading(false));
    }, []);

    const active = schedules.filter((schedule) => schedule.isActive).length;

    return (
        <ServerContentBlock title={'Schedules'}>
            <FlashMessageRender byKey={'schedules'} css={tw`mb-4`} />
            <Can action={'schedule.create'}>
                <EditScheduleModal visible={visible} onModalDismissed={() => setVisible(false)} />
            </Can>

            <div css={tw`mb-5 flex flex-wrap items-end justify-between gap-3`}>
                <div>
                    <h1 css={tw`text-2xl font-semibold text-neutral-50 flex items-center gap-3`}>
                        Schedules
                        {schedules.length > 0 && (
                            <span
                                css={tw`rounded-full bg-primary-500/20 border border-primary-500/30 px-2.5 py-0.5 text-xs font-medium text-primary-300`}
                            >
                                {active}/{schedules.length} <span>active</span>
                            </span>
                        )}
                    </h1>
                    <p css={tw`text-sm text-neutral-400 mt-1`}>
                        Automate your server: run backups, restarts and commands at the times you choose.
                    </p>
                </div>
                {schedules.length > 0 && (
                    <Can action={'schedule.create'}>
                        <Button type={'button'} onClick={() => setVisible(true)}>
                            <FontAwesomeIcon icon={faPlus} css={tw`mr-2`} />
                            New schedule
                        </Button>
                    </Can>
                )}
            </div>

            {!schedules.length && loading ? (
                <Spinner size={'large'} centered />
            ) : schedules.length === 0 ? (
                <div
                    css={tw`rounded-2xl border border-white/5 bg-neutral-800 shadow-card px-6 py-12 flex flex-col items-center text-center`}
                >
                    <div
                        css={tw`w-16 h-16 rounded-2xl bg-primary-500/20 text-primary-300 flex items-center justify-center mb-4`}
                    >
                        <FontAwesomeIcon icon={faCalendarAlt} size={'lg'} />
                    </div>
                    <h2 css={tw`text-lg font-semibold text-neutral-100`}>No schedule yet</h2>
                    <p css={tw`mt-2 max-w-md text-sm text-neutral-400`}>
                        Create a schedule to automate backups, restarts or console commands. Each schedule runs its
                        tasks on a cron timing you choose.
                    </p>
                    <Can action={'schedule.create'}>
                        <Button type={'button'} onClick={() => setVisible(true)} css={tw`mt-6`}>
                            <FontAwesomeIcon icon={faPlus} css={tw`mr-2`} />
                            Create schedule
                        </Button>
                    </Can>
                </div>
            ) : (
                schedules.map((schedule) => (
                    <GreyRowBox
                        as={'a'}
                        key={schedule.id}
                        href={`${match.url}/${schedule.id}`}
                        css={tw`cursor-pointer mb-2 flex-wrap`}
                        onClick={(e: any) => {
                            e.preventDefault();
                            history.push(`${match.url}/${schedule.id}`);
                        }}
                    >
                        <ScheduleRow schedule={schedule} />
                    </GreyRowBox>
                ))
            )}
        </ServerContentBlock>
    );
};
