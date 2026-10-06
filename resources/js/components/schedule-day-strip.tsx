import { useEffect, useRef } from 'react';
import { dayNumber, monthShort, weekdayShort } from '@/lib/dates';
import { cn } from '@/lib/utils';
import type { ScheduleDay } from '@/types/fire-tracker';

/**
 * How far from the left edge the selected or today cell is parked when the strip
 * scrolls. Two cells of context either side stops it reading as the edge of the
 * range.
 */
const LEADING_CONTEXT = 2;

/**
 * The window as a row of real days.
 *
 * The previous version prefixed the row with two synthetic cells — an "Overdue"
 * block and an "In window" block — so the strip's length was unrelated to the
 * window, and those two could not be reached or understood as days. Overdue work
 * is now the agenda's first group and the window is the horizon control, so
 * every cell here is one selectable calendar day and nothing else.
 *
 * The row scrolls horizontally rather than wrapping. Wrapping made the strip
 * three rows tall on a laptop and pushed the agenda below the fold, and it moved
 * "today" to a different place on every width.
 */
export function ScheduleDayStrip({
    days,
    selected,
    onSelect,
}: {
    days: ScheduleDay[];
    selected: string | null;
    onSelect: (date: string | null) => void;
}) {
    const scroller = useRef<HTMLDivElement>(null);

    // Keeps whichever day matters in view. Without this, picking day 12 of a
    // 30-day window on a phone scrolls it off, and reloading on day 12 opens the
    // strip showing days 1-6.
    useEffect(() => {
        const node = scroller.current;

        if (!node) {
            return;
        }

        // The selected day if there is one, otherwise today.
        const wanted = days.findIndex((day) =>
            selected === null ? day.isToday : day.date === selected,
        );

        if (wanted < 0) {
            return;
        }

        const cell = node.querySelectorAll('li')[wanted];

        if (cell instanceof HTMLElement) {
            // Assigned rather than scrollTo()'d: scrollTo is not implemented in
            // jsdom, and a jump is no worse here than a smooth glide.
            node.scrollLeft = Math.max(
                0,
                cell.offsetLeft -
                    cell.offsetWidth * LEADING_CONTEXT -
                    node.offsetLeft,
            );
        }
    }, [days, selected]);

    return (
        <div
            ref={scroller}
            className="relative overflow-x-auto"
            role="group"
            aria-label="Day in range"
            data-day-strip
        >
            <ul className="flex w-max snap-x gap-1 pb-1">
                {days.map((day) => {
                    const active = day.date === selected;

                    return (
                        <li
                            key={day.date}
                            className="snap-start"
                            data-day={day.date}
                        >
                            {/*
                                A real button rather than a div with a click
                                handler: it is in the tab order, it announces its
                                date and count, and Space and Enter both work. The
                                date is spelled out in the accessible name so
                                "Thu 8" is never all a screen reader offers.
                            */}
                            <button
                                type="button"
                                onClick={() =>
                                    onSelect(active ? null : day.date)
                                }
                                aria-pressed={active}
                                aria-label={`${weekdayShort(day.date)} ${dayNumber(day.date)} ${monthShort(day.date)}, ${day.itemCount} ${
                                    day.itemCount === 1 ? 'record' : 'records'
                                }`}
                                className={cn(
                                    'flex w-11 shrink-0 flex-col items-center gap-0.5 rounded-lg border px-1 py-1.5 text-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                                    active
                                        ? 'border-foreground/25 bg-accent/60 font-semibold text-foreground'
                                        : 'border-transparent text-muted-foreground hover:bg-accent/40',
                                )}
                            >
                                <span
                                    className={cn(
                                        'text-[10px] uppercase',
                                        day.isToday && 'font-semibold',
                                    )}
                                >
                                    {day.isToday
                                        ? 'Today'
                                        : weekdayShort(day.date)}
                                </span>

                                <span className="text-sm font-semibold text-foreground tabular-nums">
                                    {dayNumber(day.date)}
                                </span>

                                {/*
                                    A zero day stays visible but quiet. Dropping
                                    empty days would make the strip's length
                                    depend on how busy the site is, and the reader
                                    would lose the shape of the week.
                                */}
                                <span
                                    className={cn(
                                        'h-3.5 text-[10px] tabular-nums',
                                        day.itemCount === 0
                                            ? 'text-muted-foreground/50'
                                            : 'font-medium text-foreground',
                                    )}
                                >
                                    {day.itemCount === 0 ? '·' : day.itemCount}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
