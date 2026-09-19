import React, { useEffect, useRef, useState } from 'react';
import { Link, Redirect } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import { Formik, FormikHelpers } from 'formik';
import { object, ref as yupRef, string } from 'yup';
import Reaptcha from 'reaptcha';
import tw from 'twin.macro';
import register from '@/api/auth/register';
import LoginFormContainer from '@/components/auth/LoginFormContainer';
import DiscordButton from '@/components/auth/DiscordButton';
import Field from '@/components/elements/Field';
import Button from '@/components/elements/Button';
import useFlash from '@/plugins/useFlash';

interface Values {
    nameFirst: string;
    nameLast: string;
    username: string;
    email: string;
    password: string;
    passwordConfirmation: string;
}

const RegisterContainer = () => {
    const captcha = useRef<Reaptcha>(null);
    const [token, setToken] = useState('');

    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const { enabled: recaptchaEnabled, siteKey } = useStoreState((state) => state.settings.data!.recaptcha);
    const registration = useStoreState((state) => state.settings.data!.registration);
    const discordEnabled = useStoreState((state) => state.settings.data!.discord.enabled);

    useEffect(() => {
        clearFlashes();
    }, []);

    // Nothing to show when the Panel does not accept new accounts.
    if (!registration) {
        return <Redirect to={'/auth/login'} />;
    }

    const onSubmit = (values: Values, { setSubmitting }: FormikHelpers<Values>) => {
        clearFlashes();

        // Same flow as the login form: ask for a reCAPTCHA token first, and submit again once
        // the component hands it back.
        if (recaptchaEnabled && !token) {
            captcha.current!.execute().catch((error) => {
                console.error(error);

                setSubmitting(false);
                clearAndAddHttpError({ error });
            });

            return;
        }

        register({ ...values, recaptchaData: token })
            .then((response) => {
                // @ts-expect-error this is valid
                window.location = response.intended || '/';
            })
            .catch((error) => {
                console.error(error);

                setToken('');
                if (captcha.current) captcha.current.reset();

                setSubmitting(false);
                clearAndAddHttpError({ error });
            });
    };

    return (
        <Formik
            onSubmit={onSubmit}
            initialValues={{
                nameFirst: '',
                nameLast: '',
                username: '',
                email: '',
                password: '',
                passwordConfirmation: '',
            }}
            validationSchema={object().shape({
                nameFirst: string().required('Please enter your first name.'),
                nameLast: string().required('Please enter your last name.'),
                username: string()
                    .required('Please choose a username.')
                    .min(3, 'Your username must be at least 3 characters long.')
                    .matches(
                        /^[a-z0-9]([\w.-]+)[a-z0-9]$/i,
                        'Your username must start and end with a letter or number, and only contain letters, numbers, dashes, underscores and periods.'
                    ),
                email: string().email('Please enter a valid email address.').required('Please enter your email address.'),
                password: string()
                    .required('Please choose a password.')
                    .min(8, 'Your password must be at least 8 characters long.'),
                passwordConfirmation: string()
                    .required('Please confirm your password.')
                    .oneOf([yupRef('password')], 'Your passwords do not match.'),
            })}
        >
            {({ isSubmitting, setSubmitting, submitForm }) => (
                <LoginFormContainer title={'Create an Account'} css={tw`w-full flex`}>
                    <div css={tw`grid grid-cols-2 gap-4`}>
                        <Field type={'text'} label={'First Name'} name={'nameFirst'} disabled={isSubmitting} />
                        <Field type={'text'} label={'Last Name'} name={'nameLast'} disabled={isSubmitting} />
                    </div>
                    <div css={tw`mt-6`}>
                        <Field
                            type={'text'}
                            label={'Username'}
                            name={'username'}
                            autoComplete={'username'}
                            disabled={isSubmitting}
                        />
                    </div>
                    <div css={tw`mt-6`}>
                        <Field
                            type={'email'}
                            label={'Email'}
                            name={'email'}
                            autoComplete={'email'}
                            disabled={isSubmitting}
                        />
                    </div>
                    <div css={tw`mt-6`}>
                        <Field
                            type={'password'}
                            label={'Password'}
                            name={'password'}
                            autoComplete={'new-password'}
                            description={'Your password must be at least 8 characters long.'}
                            disabled={isSubmitting}
                        />
                    </div>
                    <div css={tw`mt-6`}>
                        <Field
                            type={'password'}
                            label={'Confirm Password'}
                            name={'passwordConfirmation'}
                            autoComplete={'new-password'}
                            disabled={isSubmitting}
                        />
                    </div>
                    <div css={tw`mt-6`}>
                        <Button type={'submit'} size={'xlarge'} isLoading={isSubmitting} disabled={isSubmitting}>
                            Create Account
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
                                <DiscordButton>Sign up with Discord</DiscordButton>
                            </div>
                        </React.Fragment>
                    )}
                    {recaptchaEnabled && (
                        <Reaptcha
                            ref={captcha}
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
                    <div css={tw`mt-6 text-center text-sm text-neutral-400`}>
                        Already have an account?{' '}
                        <Link
                            to={'/auth/login'}
                            css={tw`no-underline text-primary-400 hover:text-primary-300 transition-colors duration-150`}
                        >
                            Sign in
                        </Link>
                    </div>
                </LoginFormContainer>
            )}
        </Formik>
    );
};

export default RegisterContainer;
