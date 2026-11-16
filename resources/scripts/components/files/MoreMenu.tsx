import React, { Fragment } from 'react';
import { Menu, Transition } from '@headlessui/react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEllipsisH, IconDefinition } from '@fortawesome/free-solid-svg-icons';
import classNames from 'classnames';
import styles from '@/components/elements/dropdown/style.module.css';

export interface MoreMenuItem {
    label: string;
    // A second, smaller line under the label (like the formats of a conversion).
    detail?: string;
    icon: IconDefinition;
    danger?: boolean;
    onClick: () => void;
}

// The "..." button of a file, with every other action written in full: people who do not know the icons see what
// each one does.
export default ({ items }: { items: (MoreMenuItem | null | false)[] }) => (
    <Menu as={'div'} className={classNames(styles.menu, 'w-full h-full')}>
        <Menu.Button
            className={classNames(styles.button, 'w-full h-full border border-neutral-600')}
            style={{ padding: 0 }}
            title={'More'}
            aria-label={'More'}
        >
            <FontAwesomeIcon icon={faEllipsisH} />
        </Menu.Button>
        <Transition
            as={Fragment}
            enter={'transition duration-100 ease-out'}
            enterFrom={'transform scale-95 opacity-0'}
            enterTo={'transform scale-100 opacity-100'}
            leave={'transition duration-75 ease-out'}
            leaveFrom={'transform scale-100 opacity-100'}
            leaveTo={'transform scale-95 opacity-0'}
        >
            <Menu.Items className={classNames(styles.items_container, 'w-60 z-30 outline-none bottom-full mb-2 !mt-0 origin-bottom-right')}>
                <div className={'p-1'}>
                    {items.map((item, index) =>
                        item ? (
                            <Menu.Item key={index}>
                                {({ active }) => (
                                    <button
                                        type={'button'}
                                        onClick={item.onClick}
                                        className={classNames(
                                            'flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors duration-100',
                                            item.danger ? 'text-red-300' : 'text-neutral-100',
                                            active && (item.danger ? 'bg-red-500/20' : 'bg-white/10')
                                        )}
                                    >
                                        <FontAwesomeIcon
                                            icon={item.icon}
                                            fixedWidth
                                            className={item.danger ? 'text-red-400' : 'text-neutral-400'}
                                        />
                                        <span className={'min-w-0'}>
                                            <span className={'block'}>{item.label}</span>
                                            {item.detail && (
                                                <span className={'block text-xs text-neutral-400'}>{item.detail}</span>
                                            )}
                                        </span>
                                    </button>
                                )}
                            </Menu.Item>
                        ) : null
                    )}
                </div>
            </Menu.Items>
        </Transition>
    </Menu>
);
