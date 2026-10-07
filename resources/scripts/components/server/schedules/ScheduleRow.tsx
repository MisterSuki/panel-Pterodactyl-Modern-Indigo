import React from 'react';
import { Schedule } from '@/api/server/schedules/getServerSchedules';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCalendarAlt, faClock, faHistory, faPowerOff, faTasks } from '@fortawesome/free-solid-svg-icons';
import { format } from 'date-fns';
import tw from 'twin.macro';
import ScheduleCronRow from '@/components/server/schedules/ScheduleCronRow';

const Chip = ({ icon, children }: { icon: any; children: React.ReactNode }) => (
    <span css={tw`inline-flex items-center gap-1.5 text-xs text-neutral-400`}>
        <FontAwesomeIcon icon={icon} css={tw`text-neutral-500`} fixedWidth />
        {children}
    </span>
);

export default ({ schedule }: { schedule: Schedule }) => {
    const taskCount = schedule.tasks.length;
    const statusClass = schedule.isProcessing
        ? tw`bg-blue-500/20 text-blue-300 border-blue-500/30`
        : schedule.isActive
        ? tw`bg-green-500/20 text-green-300 border-green-500/30`
        : tw`bg-neutral-500/20 text-neutral-300 border-neutral-500/30`;
    const statusLabel = schedule.isProcessing ? 'Processing' : schedule.isActive ? 'Active' : 'Inactive';

    return (
        <>
            <div css={tw`flex items-center min-w-0 flex-1`}>
                <div
                    css={tw`hidden sm:flex w-11 h-11 rounded-xl bg-primary-500/20 text-primary-300 items-center justify-center flex-shrink-0`}
                >
                    <FontAwesomeIcon icon={faCalendarAlt} />
                </div>
                <div css={tw`sm:ml-4 min-w-0`}>
                    <p css={tw`font-medium text-neutral-100 truncate`}>{schedule.name}</p>
                    <div css={tw`mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1`}>
                        <Chip icon={faClock}>
                            <span>Next run</span>:{' '}
                            {schedule.nextRunAt && schedule.isActive ? (
                                <span css={tw`text-neutral-300`}>{format(schedule.nextRunAt, "MMM do 'at' h:mma")}</span>
                            ) : (
                                '—'
                            )}
                        </Chip>
                        <Chip icon={faHistory}>
                            <span>Last run</span>:{' '}
                            {schedule.lastRunAt ? (
                                <span css={tw`text-neutral-300`}>{format(schedule.lastRunAt, "MMM do 'at' h:mma")}</span>
                            ) : (
                                <span>never</span>
                            )}
                        </Chip>
                        <Chip icon={faTasks}>
                            {taskCount} <span>{taskCount === 1 ? 'task' : 'tasks'}</span>
                        </Chip>
                        {schedule.onlyWhenOnline && (
                            <Chip icon={faPowerOff}>
                                <span>Only when online</span>
                            </Chip>
                        )}
                    </div>
                </div>
            </div>
            <ScheduleCronRow cron={schedule.cron} css={tw`mx-auto lg:mx-8 w-full lg:w-auto mt-4 lg:mt-0 hidden lg:flex`} />
            <div css={tw`mt-3 sm:mt-0 sm:ml-4 flex-shrink-0`}>
                <p
                    css={[
                        tw`py-1 px-3 rounded-full border text-2xs font-semibold uppercase tracking-wide text-center`,
                        statusClass,
                    ]}
                >
                    <span>{statusLabel}</span>
                </p>
            </div>
        </>
    );
};
