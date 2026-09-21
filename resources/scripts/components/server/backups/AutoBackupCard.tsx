import React, { useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCalendarCheck, faCog, faExclamationTriangle } from '@fortawesome/free-solid-svg-icons';
import { Field as FormikField, Form, Formik, useFormikContext } from 'formik';
import { boolean, number, object, string } from 'yup';
import tw from 'twin.macro';
import Modal, { RequiredModalProps } from '@/components/elements/Modal';
import Button from '@/components/elements/Button';
import Can from '@/components/elements/Can';
import Select from '@/components/elements/Select';
import Field from '@/components/elements/Field';
import FormikFieldWrapper from '@/components/elements/FormikFieldWrapper';
import FormikSwitch from '@/components/elements/FormikSwitch';
import FlashMessageRender from '@/components/FlashMessageRender';
import { Textarea } from '@/components/elements/Input';
import useFlash from '@/plugins/useFlash';
import { ServerContext } from '@/state/server';
import { BackupPlanValues, saveBackupPlan, useBackupPlan } from '@/api/server/backups/backupPlan';
import { BackupFrequency, ServerBackupPlan } from '@/api/server/types';

const FREQUENCIES: { value: BackupFrequency; label: string }[] = [
    { value: '6h', label: 'Every 6 hours' },
    { value: '12h', label: 'Every 12 hours' },
    { value: '24h', label: 'Every day' },
    { value: '7d', label: 'Every week' },
];

const frequencyLabel = (frequency: BackupFrequency) => FREQUENCIES.find((f) => f.value === frequency)!.label;

const formatDate = (date: Date): string =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);

const Tile = ({ label, children }: { label: string; children: React.ReactNode }) => (
    <div css={tw`rounded-lg border border-white/5 bg-white/[0.03] px-4 py-3 min-w-0`}>
        <p css={tw`text-2xs uppercase tracking-wider text-neutral-400 mb-1`}>{label}</p>
        <div css={tw`text-sm text-neutral-100 truncate`}>{children}</div>
    </div>
);

const PlanForm = ({ backupLimit, ...props }: RequiredModalProps & { backupLimit: number }) => {
    const { isSubmitting, values } = useFormikContext<BackupPlanValues>();
    const daily = values.frequency === '24h' || values.frequency === '7d';

    return (
        <Modal {...props} showSpinnerOverlay={isSubmitting}>
            <Form>
                <FlashMessageRender byKey={'backups:auto'} css={tw`mb-4`} />
                <h2 css={tw`text-2xl mb-6`}>Automatic backups</h2>
                <div css={tw`bg-neutral-700 border border-neutral-800 shadow-inner p-4 rounded`}>
                    <FormikSwitch
                        name={'enabled'}
                        label={'Enabled'}
                        description={'The panel backs the server up by itself, and removes the oldest ones.'}
                    />
                </div>
                <div css={tw`mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4`}>
                    <FormikFieldWrapper name={'frequency'} label={'Frequency'}>
                        <FormikField as={Select} name={'frequency'}>
                            {FREQUENCIES.map((f) => (
                                <option key={f.value} value={f.value}>
                                    {f.label}
                                </option>
                            ))}
                        </FormikField>
                    </FormikFieldWrapper>
                    {daily && (
                        <FormikFieldWrapper name={'hour'} label={'Time of the day'}>
                            <FormikField as={Select} name={'hour'}>
                                {Array.from({ length: 24 }, (_, hour) => (
                                    <option key={hour} value={hour}>
                                        {String(hour).padStart(2, '0')}:00
                                    </option>
                                ))}
                            </FormikField>
                        </FormikFieldWrapper>
                    )}
                </div>
                <div css={tw`mt-6`}>
                    <Field
                        name={'keep'}
                        type={'number'}
                        min={1}
                        max={backupLimit}
                        label={'Backups to keep'}
                        description={
                            'When there are more, the oldest automatic ones are deleted. Backups made by hand and locked backups are never deleted.'
                        }
                    />
                </div>
                <div css={tw`mt-6`}>
                    <FormikFieldWrapper
                        name={'ignored'}
                        label={'Ignored Files & Directories'}
                        description={
                            'One path per line. Leave blank to use the .pteroignore file of the server if there is one.'
                        }
                    >
                        <FormikField as={Textarea} name={'ignored'} rows={4} />
                    </FormikFieldWrapper>
                </div>
                <div css={tw`flex justify-end mt-6`}>
                    <Button type={'submit'} disabled={isSubmitting}>
                        Save
                    </Button>
                </div>
            </Form>
        </Modal>
    );
};

export default ({ backupLimit }: { backupLimit: number }) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { data: plan, mutate } = useBackupPlan(uuid);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const [visible, setVisible] = useState(false);

    if (plan === undefined || backupLimit === 0) {
        return null;
    }

    const initial: BackupPlanValues = {
        enabled: true,
        frequency: (plan?.frequency || '24h') as BackupFrequency,
        hour: plan?.hour ?? 4,
        keep: Math.min(plan?.keep ?? 3, backupLimit),
        ignored: plan?.ignored || '',
    };

    const submit = (values: BackupPlanValues, { setSubmitting }: { setSubmitting: (s: boolean) => void }) => {
        clearFlashes('backups:auto');
        saveBackupPlan(uuid, { ...values, hour: Number(values.hour), keep: Number(values.keep) })
            .then((saved: ServerBackupPlan) => {
                mutate(saved, false);
                setVisible(false);
            })
            .catch((error) => {
                clearAndAddHttpError({ key: 'backups:auto', error });
                setSubmitting(false);
            });
    };

    return (
        <div css={tw`rounded-xl border border-white/5 bg-neutral-800 shadow-card mb-6 overflow-hidden`}>
            {visible && (
                <Formik
                    onSubmit={submit}
                    initialValues={initial}
                    validationSchema={object().shape({
                        enabled: boolean(),
                        frequency: string().required(),
                        hour: number().min(0).max(23),
                        keep: number().required().min(1).max(backupLimit),
                        ignored: string().max(2000),
                    })}
                >
                    <PlanForm
                        appear
                        visible={visible}
                        backupLimit={backupLimit}
                        onDismissed={() => setVisible(false)}
                    />
                </Formik>
            )}
            <div css={tw`flex items-center justify-between gap-4 px-4 py-3 border-b border-white/5 bg-white/[0.03]`}>
                <p css={tw`text-xs uppercase tracking-wide font-medium text-neutral-300`}>
                    <FontAwesomeIcon icon={faCalendarCheck} css={tw`mr-2 text-primary-400`} />
                    Automatic backups
                </p>
                <div css={tw`flex items-center gap-3`}>
                    <span
                        css={[
                            tw`rounded-full px-2 py-px text-2xs uppercase tracking-wider border`,
                            plan?.enabled
                                ? tw`bg-green-500/20 text-green-300 border-green-500/30`
                                : tw`bg-neutral-500/20 text-neutral-300 border-white/10`,
                        ]}
                    >
                        {plan?.enabled ? 'On' : 'Off'}
                    </span>
                    <Can action={'backup.create'}>
                        <Button size={'xsmall'} isSecondary color={'grey'} onClick={() => setVisible(true)}>
                            <FontAwesomeIcon icon={faCog} css={tw`mr-2`} />
                            Configure
                        </Button>
                    </Can>
                </div>
            </div>
            <div css={tw`p-4`}>
                {plan?.enabled ? (
                    <div css={tw`grid grid-cols-1 sm:grid-cols-3 gap-3`}>
                        <Tile label={'Frequency'}>
                            <span>{frequencyLabel(plan.frequency)}</span>
                            {plan.hour !== null && (plan.frequency === '24h' || plan.frequency === '7d') && (
                                <span css={tw`text-neutral-400 ml-2`}>{String(plan.hour).padStart(2, '0')}:00</span>
                            )}
                        </Tile>
                        <Tile label={'Backups kept'}>{plan.keep}</Tile>
                        <Tile label={'Next backup'}>{plan.nextRunAt ? formatDate(plan.nextRunAt) : '—'}</Tile>
                    </div>
                ) : (
                    <p css={tw`text-sm text-neutral-400`}>
                        The server is not backed up by itself. Turn on the automatic backups to always have a recent
                        copy.
                    </p>
                )}
                {plan?.enabled && plan.lastError && (
                    <p css={tw`mt-3 flex items-start gap-2 text-sm text-yellow-300`}>
                        <FontAwesomeIcon icon={faExclamationTriangle} css={tw`mt-1 flex-none`} />
                        <span>{plan.lastError}</span>
                    </p>
                )}
            </div>
        </div>
    );
};
