import React, { useContext, useEffect, useState } from 'react';
import { Schedule } from '@/api/server/schedules/getServerSchedules';
import Field from '@/components/elements/Field';
import { Form, Formik, FormikHelpers, useFormikContext } from 'formik';
import FormikSwitch from '@/components/elements/FormikSwitch';
import createOrUpdateSchedule from '@/api/server/schedules/createOrUpdateSchedule';
import { ServerContext } from '@/state/server';
import { httpErrorToHuman } from '@/api/http';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import tw from 'twin.macro';
import classNames from 'classnames';
import { Button } from '@/components/elements/button/index';
import ModalContext from '@/context/ModalContext';
import asModal from '@/hoc/asModal';
import Switch from '@/components/elements/Switch';
import ScheduleCheatsheetCards from '@/components/server/schedules/ScheduleCheatsheetCards';

interface Props {
    schedule?: Schedule;
}

interface Values {
    name: string;
    dayOfWeek: string;
    month: string;
    dayOfMonth: string;
    hour: string;
    minute: string;
    enabled: boolean;
    onlyWhenOnline: boolean;
}

type Cron = Pick<Values, 'minute' | 'hour' | 'dayOfMonth' | 'month' | 'dayOfWeek'>;

// One-click timings that fill the five cron fields. The labels are translated through the dictionary.
const PRESETS: { label: string; cron: Cron }[] = [
    { label: 'Every 5 minutes', cron: { minute: '*/5', hour: '*', dayOfMonth: '*', month: '*', dayOfWeek: '*' } },
    { label: 'Every 15 minutes', cron: { minute: '*/15', hour: '*', dayOfMonth: '*', month: '*', dayOfWeek: '*' } },
    { label: 'Every 30 minutes', cron: { minute: '*/30', hour: '*', dayOfMonth: '*', month: '*', dayOfWeek: '*' } },
    { label: 'Hourly', cron: { minute: '0', hour: '*', dayOfMonth: '*', month: '*', dayOfWeek: '*' } },
    { label: 'Every 6 hours', cron: { minute: '0', hour: '*/6', dayOfMonth: '*', month: '*', dayOfWeek: '*' } },
    { label: 'Every 12 hours', cron: { minute: '0', hour: '*/12', dayOfMonth: '*', month: '*', dayOfWeek: '*' } },
    { label: 'Daily at midnight', cron: { minute: '0', hour: '0', dayOfMonth: '*', month: '*', dayOfWeek: '*' } },
    { label: 'Weekly (Monday)', cron: { minute: '0', hour: '0', dayOfMonth: '*', month: '*', dayOfWeek: '1' } },
    { label: 'Monthly (1st)', cron: { minute: '0', hour: '0', dayOfMonth: '1', month: '*', dayOfWeek: '*' } },
];

const sameCron = (a: Cron, b: Cron) =>
    a.minute === b.minute && a.hour === b.hour && a.dayOfMonth === b.dayOfMonth && a.month === b.month && a.dayOfWeek === b.dayOfWeek;

// The cron timing: quick presets, the five fields and a short summary of what is set.
const CronBuilder = () => {
    const { values, setFieldValue } = useFormikContext<Values>();
    const current: Cron = {
        minute: values.minute,
        hour: values.hour,
        dayOfMonth: values.dayOfMonth,
        month: values.month,
        dayOfWeek: values.dayOfWeek,
    };
    const match = PRESETS.find((p) => sameCron(p.cron, current));

    const apply = (cron: Cron) => {
        (Object.keys(cron) as (keyof Cron)[]).forEach((key) => setFieldValue(key, cron[key]));
    };

    return (
        <div css={tw`mt-6 rounded-xl border border-white/5 bg-neutral-900/40 p-4`}>
            <p css={tw`text-xs font-semibold uppercase tracking-wide text-neutral-400 mb-2`}>
                <span>Quick presets</span>
            </p>
            <div css={tw`flex flex-wrap gap-2`}>
                {PRESETS.map((preset) => (
                    <button
                        key={preset.label}
                        type={'button'}
                        onClick={() => apply(preset.cron)}
                        className={classNames(
                            'rounded-full border px-3 py-1.5 text-xs font-medium transition-colors duration-150',
                            match && match.label === preset.label
                                ? 'border-primary-400 bg-primary-500/20 text-primary-100'
                                : 'border-white/10 bg-white/5 text-neutral-300 hover:bg-white/10'
                        )}
                    >
                        {preset.label}
                    </button>
                ))}
            </div>
            <div css={tw`grid grid-cols-2 sm:grid-cols-5 gap-4 mt-4`}>
                <Field name={'minute'} label={'Minute'} />
                <Field name={'hour'} label={'Hour'} />
                <Field name={'dayOfMonth'} label={'Day of month'} />
                <Field name={'month'} label={'Month'} />
                <Field name={'dayOfWeek'} label={'Day of week'} />
            </div>
            <p css={tw`mt-3 text-xs text-neutral-400`}>
                {match ? (
                    <>
                        <span>This runs</span>:{' '}
                        <span css={tw`text-primary-300 font-medium`}>{match.label}</span>
                    </>
                ) : (
                    <>
                        <span>Custom timing</span>:{' '}
                        <code css={tw`text-neutral-300`}>
                            {values.minute} {values.hour} {values.dayOfMonth} {values.month} {values.dayOfWeek}
                        </code>
                    </>
                )}
            </p>
            <p css={tw`text-neutral-500 text-2xs mt-2`}>
                The schedule system supports the use of Cronjob syntax when defining when tasks should begin running. Use
                the fields above to specify when these tasks should begin running.
            </p>
        </div>
    );
};

const EditScheduleModal = ({ schedule }: Props) => {
    const { addError, clearFlashes } = useFlash();
    const { dismiss } = useContext(ModalContext);

    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const appendSchedule = ServerContext.useStoreActions((actions) => actions.schedules.appendSchedule);
    const [showCheatsheet, setShowCheetsheet] = useState(false);

    useEffect(() => {
        return () => {
            clearFlashes('schedule:edit');
        };
    }, []);

    const submit = (values: Values, { setSubmitting }: FormikHelpers<Values>) => {
        clearFlashes('schedule:edit');
        createOrUpdateSchedule(uuid, {
            id: schedule?.id,
            name: values.name,
            cron: {
                minute: values.minute,
                hour: values.hour,
                dayOfWeek: values.dayOfWeek,
                month: values.month,
                dayOfMonth: values.dayOfMonth,
            },
            onlyWhenOnline: values.onlyWhenOnline,
            isActive: values.enabled,
        })
            .then((schedule) => {
                setSubmitting(false);
                appendSchedule(schedule);
                dismiss();
            })
            .catch((error) => {
                console.error(error);

                setSubmitting(false);
                addError({ key: 'schedule:edit', message: httpErrorToHuman(error) });
            });
    };

    return (
        <Formik
            onSubmit={submit}
            initialValues={
                {
                    name: schedule?.name || '',
                    minute: schedule?.cron.minute || '*/5',
                    hour: schedule?.cron.hour || '*',
                    dayOfMonth: schedule?.cron.dayOfMonth || '*',
                    month: schedule?.cron.month || '*',
                    dayOfWeek: schedule?.cron.dayOfWeek || '*',
                    enabled: schedule?.isActive ?? true,
                    onlyWhenOnline: schedule?.onlyWhenOnline ?? true,
                } as Values
            }
        >
            {({ isSubmitting }) => (
                <Form>
                    <h3 css={tw`text-2xl mb-6`}>{schedule ? 'Edit schedule' : 'Create new schedule'}</h3>
                    <FlashMessageRender byKey={'schedule:edit'} css={tw`mb-6`} />
                    <Field
                        name={'name'}
                        label={'Schedule name'}
                        description={'A human readable identifier for this schedule.'}
                    />
                    <CronBuilder />
                    <div css={tw`mt-6 rounded-xl border border-white/5 bg-neutral-900/40 p-4`}>
                        <Switch
                            name={'show_cheatsheet'}
                            description={'Show the cron cheatsheet for some examples.'}
                            label={'Show Cheatsheet'}
                            defaultChecked={showCheatsheet}
                            onChange={() => setShowCheetsheet((s) => !s)}
                        />
                        {showCheatsheet && (
                            <div css={tw`block md:flex w-full mt-3`}>
                                <ScheduleCheatsheetCards />
                            </div>
                        )}
                    </div>
                    <div css={tw`mt-4 rounded-xl border border-white/5 bg-neutral-900/40 p-4`}>
                        <FormikSwitch
                            name={'onlyWhenOnline'}
                            description={'Only execute this schedule when the server is in a running state.'}
                            label={'Only When Server Is Online'}
                        />
                    </div>
                    <div css={tw`mt-4 rounded-xl border border-white/5 bg-neutral-900/40 p-4`}>
                        <FormikSwitch
                            name={'enabled'}
                            description={'This schedule will be executed automatically if enabled.'}
                            label={'Schedule Enabled'}
                        />
                    </div>
                    <div css={tw`mt-6 text-right`}>
                        <Button className={'w-full sm:w-auto'} type={'submit'} disabled={isSubmitting}>
                            {schedule ? 'Save changes' : 'Create schedule'}
                        </Button>
                    </div>
                </Form>
            )}
        </Formik>
    );
};

export default asModal<Props>()(EditScheduleModal);
