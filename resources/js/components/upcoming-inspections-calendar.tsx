import { format, parseISO } from 'date-fns';
import {
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    ListTodo,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import type { DashboardCalendar } from '@/types/fire-tracker';

export function UpcomingInspectionsCalendar({
    calendar,
    dates,
    asOfDate,
    onMonthChange,
}: {
    calendar: DashboardCalendar;
    dates: string[];
    asOfDate: string;
    onMonthChange: (month: string) => void;
}) {
    const month = parseISO(`${calendar.month}-01`);
    const today = parseISO(asOfDate);
    const lastDate = new Date(
        today.getFullYear(),
        today.getMonth(),
        today.getDate() + 90,
    );

    return (
        <Card className="gap-0 py-0">
            <CardHeader className="border-b py-3">
                <CardTitle className="flex items-center gap-2 text-base leading-7">
                    <span className="flex size-7 items-center justify-center rounded-md bg-brand/10">
                        <CalendarClock
                            className="size-4 text-brand"
                            aria-hidden="true"
                        />
                    </span>
                    Upcoming inspections
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col items-center gap-2 p-4">
                <Calendar
                    month={month}
                    onMonthChange={(date) =>
                        onMonthChange(
                            `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`,
                        )
                    }
                    startMonth={
                        new Date(today.getFullYear(), today.getMonth(), 1)
                    }
                    endMonth={
                        new Date(lastDate.getFullYear(), lastDate.getMonth(), 1)
                    }
                    today={today}
                    modifiers={{ due: dates.map((date) => parseISO(date)) }}
                    modifiersClassNames={{
                        due: 'rounded-md bg-warning/15 font-semibold text-warning',
                    }}
                    aria-label="Service and inspection calendar"
                />
                <p className="flex items-center gap-2 text-xs text-muted-foreground">
                    <span
                        className="size-3 rounded-sm bg-warning/15 ring-1 ring-warning/30"
                        aria-hidden="true"
                    />
                    Highlighted dates have scheduled work
                </p>
            </CardContent>
        </Card>
    );
}

export function UpcomingInspectionsList({
    calendar,
    onPageChange,
}: {
    calendar: DashboardCalendar;
    onPageChange: (page: number) => void;
}) {
    const { events } = calendar;
    const firstItem =
        events.total === 0
            ? 0
            : (events.current_page - 1) * events.per_page + 1;
    const lastItem = Math.min(
        events.current_page * events.per_page,
        events.total,
    );

    return (
        <Card className="gap-0 py-0">
            <CardHeader className="flex flex-row items-center justify-between gap-3 border-b py-3">
                <CardTitle className="flex items-center gap-2 text-base leading-7">
                    <span className="flex size-7 items-center justify-center rounded-md bg-warning/10">
                        <ListTodo
                            className="size-4 text-warning"
                            aria-hidden="true"
                        />
                    </span>
                    Due in{' '}
                    {format(parseISO(`${calendar.month}-01`), 'MMMM yyyy')}
                </CardTitle>
                <Badge variant="secondary">{events.total}</Badge>
            </CardHeader>
            <CardContent className="flex flex-col gap-2 p-4">
                {events.data.length ? (
                    <ScrollArea
                        className="h-28"
                        type="always"
                        viewportProps={{
                            role: 'region',
                            'aria-label': 'Due inspections and services',
                            tabIndex: 0,
                        }}
                    >
                        <div className="grid w-full min-w-0 grid-cols-1 gap-2 pr-3 lg:grid-cols-2 lg:gap-x-6">
                            {events.data.map((item) => (
                                <div
                                    key={item.id}
                                    className="grid min-w-0 grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2 text-xs"
                                >
                                    <Badge
                                        variant="secondary"
                                        className="shrink-0 text-xs font-normal"
                                    >
                                        {item.source === 'inspection'
                                            ? 'Inspection'
                                            : 'Service'}
                                    </Badge>
                                    <span
                                        className="min-w-0 truncate font-medium"
                                        title={`${item.typeName} at ${item.siteName}`}
                                    >
                                        {item.typeName} · {item.siteName}
                                    </span>
                                    <span className="shrink-0 text-muted-foreground tabular-nums">
                                        {item.dueDate}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </ScrollArea>
                ) : (
                    <p className="flex h-28 items-center justify-center text-center text-xs text-muted-foreground">
                        No inspections or services due this month
                    </p>
                )}
                <div className="flex h-8 items-center justify-between gap-2 text-xs text-muted-foreground">
                    <span>
                        {firstItem}–{lastItem} of {events.total}
                    </span>
                    {events.total > events.per_page && (
                        <div className="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-7"
                                aria-label="Previous due items"
                                disabled={events.current_page === 1}
                                onClick={() =>
                                    onPageChange(events.current_page - 1)
                                }
                            >
                                <ChevronLeft />
                            </Button>
                            <span className="min-w-9 text-center">
                                {events.current_page}/{events.last_page}
                            </span>
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-7"
                                aria-label="Next due items"
                                disabled={
                                    events.current_page === events.last_page
                                }
                                onClick={() =>
                                    onPageChange(events.current_page + 1)
                                }
                            >
                                <ChevronRight />
                            </Button>
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
