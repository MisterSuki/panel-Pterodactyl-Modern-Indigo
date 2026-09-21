import React, { useEffect, useRef, useState } from 'react';
import { Link, RouteComponentProps } from 'react-router-dom';
import login from '@/api/auth/login';
import LoginFormContainer from '@/components/auth/LoginFormContainer';
import { useStoreState } from 'easy-peasy';
import { Formik, FormikHelpers } from 'formik';
import { object, string } from 'yup';
import Field from '@/components/elements/Field';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import Reaptcha from 'reaptcha';
import useFlash from '@/plugins/useFlash';
import DiscordButton from '@/components/auth/DiscordButton';
import getDiscordMessage from '@/components/auth/discordMessages';

interface Values {
    username: string;
    password: string;
}

const LoginContainer = ({ history, location }: RouteComponentProps) => {
    const ref = useRef<Reaptcha>(null);
    const [token, setToken] = useState('');

    const { clearFlashes, addFlash, clearAndAddHttpError } = useFlash();
    const { enabled: recaptchaEnabled, siteKey } = useStoreState((state) => state.settings.data!.recaptcha);
    const registration = useStoreState((state) => state.settings.data!.registration);
    const discordEnabled = useStoreState((state) => state.settings.data!.discord.enabled);

    useEffect(() => {
        clearFlashes();

        const notice = getDiscordMessage(new URLSearchParams(location.search).get('discord'));
        if (notice) {
            addFlash({
                type: notice.type,
                title: notice.type === 'success' ? 'Success' : 'Error',
                message: notice.message,
            });
        }
    }, []);

    const onSubmit = (values: Values, { setSubmitting }: FormikHelpers<Values>) => {
        clearFlashes();

        // If there is no token in the state yet, request the token and then abort this submit request
        // since it will be re-submitted when the recaptcha data is returned by the component.
        if (recaptchaEnabled && !token) {
            ref.current!.execute().catch((error) => {
                console.error(error);

                setSubmitting(false);
                clearAndAddHttpError({ error });
            });

            return;
        }

        login({ ...values, recaptchaData: token })
            .then((response) => {
                if (response.complete) {
                    // @ts-expect-error this is valid
                    window.location = response.intended || '/';
                    return;
                }

                history.replace('/auth/login/checkpoint', { token: response.confirmationToken });
            })
            .catch((error) => {
                console.error(error);

                setToken('');
                if (ref.current) ref.current.reset();

                setSubmitting(false);
                clearAndAddHttpError({ error });
            });
    };

    return (
        <Formik
            onSubmit={onSubmit}
            initialValues={{ username: '', password: '' }}
            validationSchema={object().shape({
                username: string().required('A username or email must be provided.'),
                password: string().required('Please enter your account password.'),
            })}
        >
            {({ isSubmitting, setSubmitting, submitForm }) => (
                <LoginFormContainer title={'Login to Continue'} css={tw`w-full flex`}>
                    <Field type={'text'} label={'Username or Email'} name={'username'} disabled={isSubmitting} />
                    <div css={tw`mt-6`}>
                        <Field type={'password'} label={'Password'} name={'password'} disabled={isSubmitting} />
                    </div>
                    <div css={tw`mt-6`}>
                        <Button type={'submit'} size={'xlarge'} isLoading={isSubmitting} disabled={isSubmitting}>
                            Login
                        </Button>
                    </div>
                    {discordEnabled && (
                        <React.Fragment>
                            <div css={tw`mt-6 flex items-center`}>
                                <div css={tw`flex-1 border-t border-white/10`} />
                                <span css={tw`px-3 text-xs uppercase text-neutral-500`}>or</span>
                                <div css={tw`flex-1 border-t border-white/10`} />
                            </div>
                            <div css={tw`mt-6`}>
                                <DiscordButton>Continue with Discord</DiscordButton>
                            </div>
                        </React.Fragment>
                    )}
                    {recaptchaEnabled && (
                        <Reaptcha
                            ref={ref}
                            size={'invisible'}
                            sitekey={siteKey || '_invalid_key'}
                            onVerify={(response) => {
                                setToken(response);
                                submitForm();
                            }}
                            onExpire={() => {
                                setSubmitting(false);
                                setToken('');
                            }}
                        />
                    )}
                    <div css={tw`mt-6 text-center`}>
                        <Link
                            to={'/auth/password'}
                            css={tw`text-sm text-neutral-400 no-underline hover:text-primary-400 transition-colors duration-150`}
                        >
                            Forgot password?
                        </Link>
                    </div>
                    {registration && (
                        <div css={tw`mt-4 text-center text-sm text-neutral-400`}>
                            Don&apos;t have an account?{' '}
                            <Link
                                to={'/auth/register'}
                                css={tw`no-underline text-primary-400 hover:text-primary-300 transition-colors duration-150`}
                            >
                                Create one
                            </Link>
                        </div>
                    )}
                </LoginFormContainer>
            )}
        </Formik>
    );
};

export default LoginContainer;
