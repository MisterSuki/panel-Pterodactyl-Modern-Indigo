import React from 'react';
import classNames from 'classnames';
import { TicketPriority, TicketStatus } from '@/api/tickets';

const statuses: Record<TicketStatus, { label: string; className: string }> = {
    open: { label: 'Waiting for the staff', className: 'bg-yellow-500/10 border-yellow-500/30 text-yellow-300' },
    answered: { label: 'Answered', className: 'bg-green-500/10 border-green-500/30 text-green-300' },
    closed: { label: 'Closed', className: 'bg-gray-500/10 border-gray-500/30 text-gray-300' },
};

const priorities: Record<TicketPriority, { label: string; className: string }> = {
    low: { label: 'Low', className: 'bg-gray-500/10 border-gray-500/30 text-gray-300' },
    normal: { label: 'Normal', className: 'bg-primary-500/10 border-primary-500/30 text-primary-200' },
    high: { label: 'High', className: 'bg-orange-500/10 border-orange-500/30 text-orange-300' },
    urgent: { label: 'Urgent', className: 'bg-red-500/10 border-red-500/30 text-red-300' },
};

const Pill = ({ label, className }: { label: string; className: string }) => (
    <span
        className={classNames(
            'inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium select-none whitespace-nowrap',
            className
        )}
    >
        {label}
    </span>
);

export const StatusPill = ({ status }: { status: TicketStatus }) => <Pill {...statuses[status]} />;

export const PriorityPill = ({ priority }: { priority: TicketPriority }) => <Pill {...priorities[priority]} />;
