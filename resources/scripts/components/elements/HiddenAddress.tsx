import React from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEye, faEyeSlash } from '@fortawesome/free-solid-svg-icons';
import { usePersistedState } from '@/plugins/usePersistedState';
import classNames from 'classnames';

interface Props {
    host: string;
    port: number | string;
    className?: string;
}

// The server address, with the IP blurred until the eye is clicked, so a screenshot or a screen share does not
// give it away. The choice is remembered in this browser. Copying an address always copies the real one.
export default ({ host, port, className }: Props) => {
    const [visible, setVisible] = usePersistedState<boolean>('show_server_address', false);

    return (
        <span className={classNames('inline-flex items-center gap-2 max-w-full', className)}>
            <span className={'truncate tabular-nums'}>
                <span
                    className={classNames('transition-[filter] duration-300', { 'select-none': !visible })}
                    style={visible ? undefined : { filter: 'blur(6px)' }}
                    aria-hidden={!visible}
                >
                    {host}
                </span>
                :{port}
            </span>
            <button
                type={'button'}
                className={'flex-shrink-0 text-gray-400 hover:text-gray-100 transition-colors duration-150'}
                title={visible ? 'Hide the IP address' : 'Show the IP address'}
                aria-label={visible ? 'Hide the IP address' : 'Show the IP address'}
                onClick={(e) => {
                    // The block around it copies the address, and on the dashboard the whole row is a link.
                    e.preventDefault();
                    e.stopPropagation();
                    setVisible((value) => !value);
                }}
            >
                <FontAwesomeIcon icon={visible ? faEyeSlash : faEye} fixedWidth />
            </button>
        </span>
    );
};
