import React, { forwardRef, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEye, faEyeSlash } from '@fortawesome/free-solid-svg-icons';
import { Field as FormikField, FieldProps } from 'formik';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';

interface OwnProps {
    name: string;
    light?: boolean;
    label?: string;
    description?: string;
    validate?: (value: any) => undefined | string | Promise<any>;
}

type Props = OwnProps & Omit<React.InputHTMLAttributes<HTMLInputElement>, 'name'>;

// A password with an eye at its end, to see what was typed (and hide it again).
const PasswordInput = (props: React.ComponentProps<typeof Input>) => {
    const [visible, setVisible] = useState(false);

    return (
        <div style={{ position: 'relative' }}>
            <Input
                {...props}
                type={visible ? 'text' : 'password'}
                style={{ ...props.style, paddingRight: '2.75rem' }}
            />
            <button
                type={'button'}
                onClick={() => setVisible((v) => !v)}
                title={visible ? 'Hide the password' : 'Show the password'}
                aria-label={visible ? 'Hide the password' : 'Show the password'}
                aria-pressed={visible}
                className={'text-neutral-400 hover:text-neutral-100 transition-colors duration-150'}
                style={{
                    position: 'absolute',
                    top: 0,
                    bottom: 0,
                    right: 0,
                    width: '2.75rem',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                }}
            >
                <FontAwesomeIcon icon={visible ? faEyeSlash : faEye} fixedWidth />
            </button>
        </div>
    );
};

const Field = forwardRef<HTMLInputElement, Props>(
    ({ id, name, light = false, label, description, validate, ...props }, ref) => (
        <FormikField innerRef={ref} name={name} validate={validate}>
            {({ field, form: { errors, touched } }: FieldProps) => (
                <div>
                    {label && (
                        <Label htmlFor={id} isLight={light}>
                            {label}
                        </Label>
                    )}
                    {props.type === 'password' ? (
                        <PasswordInput
                            id={id}
                            {...field}
                            {...props}
                            isLight={light}
                            hasError={!!(touched[field.name] && errors[field.name])}
                        />
                    ) : (
                        <Input
                            id={id}
                            {...field}
                            {...props}
                            isLight={light}
                            hasError={!!(touched[field.name] && errors[field.name])}
                        />
                    )}
                    {touched[field.name] && errors[field.name] ? (
                        <p className={'input-help error'}>
                            {(errors[field.name] as string).charAt(0).toUpperCase() +
                                (errors[field.name] as string).slice(1)}
                        </p>
                    ) : description ? (
                        <p className={'input-help'}>{description}</p>
                    ) : null}
                </div>
            )}
        </FormikField>
    )
);
Field.displayName = 'Field';

export default Field;
