import React from 'react';
import { format, isToday, isYesterday } from 'date-fns';
import { ActivityLog } from '@definitions/user';
import ActivityLogEntry from '@/components/elements/activity/ActivityLogEntry';
import { dateLocale } from '@/components/elements/activity/kinds';
import style from './style.module.css';

interface Props {
    items: ActivityLog[];
    // Something more to show next to an event (the browser of a sign-in, for instance).
    extra?: (activity: ActivityLog) => React.ReactNode;
}

// The events one day after the other, the newest first, on a line that joins them.
export default ({ items, extra }: Props) => {
    const locale = dateLocale();
    const days: { key: string; date: Date; items: ActivityLog[] }[] = [];
    items.forEach((activity) => {
        const key = format(activity.timestamp, 'yyyy-MM-dd');
        const last = days[days.length - 1];
        if (last && last.key === key) {
            last.items.push(activity);
        } else {
            days.push({ key, date: activity.timestamp, items: [activity] });
        }
    });

    return (
        <div>
            {days.map((day) => (
                <section key={day.key} className={'mb-2'}>
                    <h3 className={style.day}>
                        {isToday(day.date) ? (
                            <span>Today</span>
                        ) : isYesterday(day.date) ? (
                            <span>Yesterday</span>
                        ) : (
                            <span>{format(day.date, 'EEEE d MMMM yyyy', { locale })}</span>
                        )}
                        <span className={style.count}>{day.items.length}</span>
                    </h3>
                    {day.items.map((activity) => (
                        <ActivityLogEntry key={activity.id} activity={activity}>
                            {extra ? extra(activity) : null}
                        </ActivityLogEntry>
                    ))}
                </section>
            ))}
        </div>
    );
};
