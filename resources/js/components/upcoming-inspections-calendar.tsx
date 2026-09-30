import { CalendarClock, ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { UpcomingInspection } from '@/types/fire-tracker';

function dateKey(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

export function UpcomingInspectionsCalendar({
    inspections,
    dates,
    total,
    asOfDate,
}: {
    inspections: UpcomingInspection[];
    dates: string[];
    total: number;
    asOfDate: string;
}) {
    const [month, setMonth] = useState(() => {
        const [year, monthNumber] = asOfDate.split('-').map(Number);
        return new Date(year, monthNumber - 1, 1);
    });
    const firstDay = new Date(month.getFullYear(), month.getMonth(), 1);
    const dayCount = new Date(
        month.getFullYear(),
        month.getMonth() + 1,
        0,
    ).getDate();
    const today = asOfDate;
    const dueDates = new Set(dates);
    const changeMonth = (offset: number) =>
        setMonth(new Date(month.getFullYear(), month.getMonth() + offset, 1));

    return (
        <Card className="h-full">
            <CardHeader className="border-b">
                <CardTitle className="flex items-center gap-2 text-base">
                    <div className="flex size-7 items-center justify-center rounded-md bg-blue-50 dark:bg-blue-950/30">
                        <CalendarClock className="size-4 text-blue-500 dark:text-blue-400" />
                    </div>
                    Upcoming Inspections
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col p-4">
                <div className="mb-2 flex items-center justify-between">
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Previous month"
                        onClick={() => changeMonth(-1)}
                    >
                        <ChevronLeft />
                    </Button>
                    <span className="text-sm font-medium" aria-live="polite">
                        {month.toLocaleDateString('en-US', {
                            month: 'long',
                            year: 'numeric',
                        })}
                    </span>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Next month"
                        onClick={() => changeMonth(1)}
                    >
                        <ChevronRight />
                    </Button>
                </div>
                <div
                    className="grid grid-cols-7 gap-px"
                    role="list"
                    aria-label="Service calendar"
                >
                    {['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].map((day) => (
                        <div
                            key={day}
                            className="py-1 text-center text-xs text-muted-foreground"
                        >
                            {day}
                        </div>
                    ))}
                    {Array.from({ length: firstDay.getDay() }, (_, index) => (
                        <div key={`padding-${index}`} />
                    ))}
                    {Array.from({ length: dayCount }, (_, index) => {
                        const date = new Date(
                            month.getFullYear(),
                            month.getMonth(),
                            index + 1,
                        );
                        const key = dateKey(date);
                        const isToday = key === today;
                        const hasInspection = dueDates.has(key);
                        return (
                            <div
                                key={key}
                                role="listitem"
                                aria-label={`${key}${hasInspection ? ', service due' : ''}`}
                                className={cn(
                                    'flex min-h-8 flex-col items-center justify-center rounded-md py-1 text-xs',
                                    isToday
                                        ? 'bg-primary font-medium text-primary-foreground'
                                        : hasInspection
                                          ? 'bg-warning/10 text-warning'
                                          : 'text-muted-foreground',
                                )}
                            >
                                <span>{index + 1}</span>
                                {hasInspection && (
                                    <span className="mt-0.5 size-1 rounded-full bg-current" />
                                )}
                            </div>
                        );
                    })}
                </div>
                {inspections.length ? (
                    <div className="mt-3 flex flex-col gap-2 border-t pt-3">
                        <p className="text-xs font-medium text-muted-foreground">
                            Next due
                        </p>
                        <div className="custom-scrollbar flex max-h-24 flex-col gap-2 overflow-y-auto">
                            {inspections.map((item) => (
                                <div
                                    key={item.assetId}
                                    className="flex justify-between gap-3 text-xs"
                                >
                                    <span className="truncate font-medium">
                                        {item.typeName}
                                    </span>
                                    <span className="truncate text-muted-foreground">
                                        {item.siteName}
                                    </span>
                                    <span className="shrink-0 text-muted-foreground tabular-nums">
                                        {item.nextServiceDue}
                                    </span>
                                </div>
                            ))}
                        </div>
                        {total > inspections.length && (
                            <p className="text-xs text-muted-foreground">
                                Showing next {inspections.length} of {total}
                            </p>
                        )}
                    </div>
                ) : (
                    <p className="py-6 text-center text-xs text-muted-foreground">
                        No upcoming inspections
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
