import {
    addDays as addCalendarDays,
    differenceInCalendarDays,
    endOfMonth as endOfMonthDate,
    isValid,
    parseISO,
    startOfMonth as startOfMonthDate,
} from 'date-fns';

export type IsoDate = string;

/**
 * One locale for every date the product renders.
 *
 * This used to be a parameter the components passed as `undefined`, which handed
 * the decision to the browser: the same page then read "Oct 5" on one machine and
 * "10月5日" on another, and a row could show a full date beside a short one. A
 * date format is part of a product's voice, so it is pinned here rather than
 * negotiated per machine.
 */
const LOCALE = 'en-GB';

function toDate(value: IsoDate): Date {
    return parseISO(value);
}

function formatIso(value: Date): IsoDate {
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

const formatters = new Map<string, Intl.DateTimeFormat>();

/**
 * Formatters are cached because constructing one is comparatively expensive and
 * these run once per visible schedule row.
 *
 * Every formatter is built from a date that `parseISO` placed at local midnight,
 * so formatting it back without an explicit `timeZone` yields the same calendar
 * day it started as, in any browser timezone. The day arithmetic stays calendar
 * arithmetic on purpose: these are dates the server already resolved, not
 * instants to be converted.
 */
function format(value: IsoDate, options: Intl.DateTimeFormatOptions): string {
    const key = JSON.stringify(options);
    let formatter = formatters.get(key);

    if (!formatter) {
        formatter = new Intl.DateTimeFormat(LOCALE, options);
        formatters.set(key, formatter);
    }

    return formatter.format(toDate(value));
}

export function isIsoDate(value: unknown): value is IsoDate {
    return typeof value === 'string' && isValid(toDate(value));
}

export function addDays(value: IsoDate, days: number): IsoDate {
    return formatIso(addCalendarDays(toDate(value), days));
}

export function diffDays(from: IsoDate, to: IsoDate): number {
    return differenceInCalendarDays(toDate(to), toDate(from));
}

export function endOfMonth(value: IsoDate): IsoDate {
    return formatIso(endOfMonthDate(toDate(value)));
}

export function startOfMonth(value: IsoDate): IsoDate {
    return formatIso(startOfMonthDate(toDate(value)));
}

/** Day of the month on its own, for the day strip cells. */
export function dayNumber(value: IsoDate): string {
    return format(value, { day: 'numeric' });
}

/** Abbreviated weekday, for the day strip cells. */
export function weekdayShort(value: IsoDate): string {
    return format(value, { weekday: 'short' });
}

/** Abbreviated month, so a strip spanning a month boundary stays readable. */
export function monthShort(value: IsoDate): string {
    return format(value, { month: 'short' });
}

/** Day and month without a year: "4 Oct". */
export function shortDate(value: IsoDate): string {
    return format(value, { day: 'numeric', month: 'short' });
}

/** Weekday, day, month and year: "Sun, 4 Oct 2026". */
export function formatLongDate(value: IsoDate): string {
    return format(value, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

/** Weekday, day and month without a year, for in-window group headings. */
export function mediumDate(value: IsoDate): string {
    return format(value, { weekday: 'short', day: 'numeric', month: 'short' });
}

/**
 * A window stated the way it reads: the year appears once when both ends share
 * it, and on both ends when they do not.
 */
export function rangeLabel(from: IsoDate, to: IsoDate): string {
    if (from.slice(0, 4) === to.slice(0, 4)) {
        return `${shortDate(from)} – ${shortDate(to)} ${to.slice(0, 4)}`;
    }

    return `${formatLongDate(from)} – ${formatLongDate(to)}`;
}

export function relativeDue(dueDate: IsoDate, asOfDate: IsoDate): string {
    const delta = diffDays(asOfDate, dueDate);

    if (delta < 0) {
        const days = Math.abs(delta);

        return days === 1 ? '1 day overdue' : `${days} days overdue`;
    }

    if (delta === 0) {
        return 'Due today';
    }

    if (delta === 1) {
        return 'Due tomorrow';
    }

    return `Due in ${delta} days`;
}

/**
 * The heading for a group of rows. Only "today" and "tomorrow" get a word of
 * their own; everything else states its own date so the reader never has to
 * count backwards from a relative label.
 */
export function dayHeading(date: IsoDate, asOfDate: IsoDate): string {
    if (date === asOfDate) {
        return 'Today';
    }

    if (date === addDays(asOfDate, 1)) {
        return 'Tomorrow';
    }

    return mediumDate(date);
}
