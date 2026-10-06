import { CalendarClock, X } from 'lucide-react';
import { ScheduleAgenda } from '@/components/schedule-agenda';
import { ScheduleDateFilter } from '@/components/schedule-date-filter';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import {
    DEFAULT_STATE,
    isDefaultState,
    scopeSentence,
    stateFromQuery,
    typeTabs,
} from '@/lib/schedule';
import type { ScheduleState } from '@/lib/schedule';
import { cn } from '@/lib/utils';
import type {
    DashboardFilters,
    DashboardKpis,
    DashboardSchedule,
} from '@/types/fire-tracker';

export function SchedulePanel({
    schedule,
    filters,
    today,
    loading,
    error,
    onChange,
    onRetry,
}: {
    schedule: DashboardSchedule | null;
    kpis: DashboardKpis | null;
    filters: DashboardFilters;
    today: string;
    loading: boolean;
    error?: string;
    onChange: (next: ScheduleState) => void;
    onRetry?: () => void;
}) {
    const state = stateFromQuery(filters);

    return (
        <Card
            className="relative min-w-0 gap-0 overflow-hidden py-0"
            aria-busy={loading}
        >
            <CardHeader className="gap-3 border-b py-3 sm:py-4">
                <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <CalendarClock
                            className="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        Work by due date
                    </CardTitle>
                    {schedule && (
                        <p
                            className="text-xs text-muted-foreground tabular-nums"
                            aria-live="polite"
                        >
                            {scopeSentence(schedule, state)}
                        </p>
                    )}
                </div>

                {schedule && (
                    <div className="flex flex-wrap items-center gap-2">
                        <ScheduleDateFilter
                            state={state}
                            today={today}
                            disabled={loading}
                            onChange={onChange}
                        />
                        <div
                            className="flex min-w-0 items-center gap-1 overflow-x-auto"
                            role="group"
                            aria-label="Record type"
                        >
                            {typeTabs(schedule).map((tab) => (
                                <Button
                                    key={tab.id}
                                    type="button"
                                    size="sm"
                                    variant={
                                        state.type === tab.id
                                            ? 'secondary'
                                            : 'ghost'
                                    }
                                    aria-pressed={state.type === tab.id}
                                    disabled={loading}
                                    onClick={() =>
                                        onChange({
                                            ...state,
                                            type: tab.id,
                                            page: 1,
                                        })
                                    }
                                    className={cn(
                                        'h-8 shrink-0 px-2.5 text-xs',
                                        state.type === tab.id &&
                                            'font-semibold',
                                    )}
                                >
                                    {tab.label}{' '}
                                    <span className="tabular-nums">
                                        {tab.count}
                                    </span>
                                </Button>
                            ))}
                        </div>
                        {state.day && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    onChange({ ...state, day: null, page: 1 })
                                }
                            >
                                {state.day}{' '}
                                <X className="size-3.5" aria-hidden="true" />
                            </Button>
                        )}
                        {!isDefaultState(state) && (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                disabled={loading}
                                onClick={() => onChange(DEFAULT_STATE)}
                                className="ml-auto h-8 text-xs"
                            >
                                Reset
                            </Button>
                        )}
                    </div>
                )}
            </CardHeader>

            <CardContent className="p-0">
                {error && (
                    <div
                        className="flex flex-wrap items-center gap-2 border-b px-4 py-2 text-sm text-destructive"
                        role="alert"
                    >
                        <span>{error}</span>
                        {onRetry && (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={onRetry}
                            >
                                Retry
                            </Button>
                        )}
                    </div>
                )}
                {schedule ? (
                    <ScheduleAgenda
                        groups={schedule.groups}
                        returnedCount={schedule.returnedCount}
                        totalCount={schedule.totalCount}
                        truncated={schedule.truncated}
                        maxItems={schedule.maxItems}
                        page={schedule.page}
                        lastPage={schedule.lastPage}
                        onPage={(page) => onChange({ ...state, page })}
                        defaultScope={isDefaultState(state)}
                        hasFutureWork={schedule.nextAfter !== null}
                        overdueMode={state.overdue}
                        overdueCount={schedule.overdueCount}
                        windowItemCount={schedule.windowItemCount}
                        asOfDate={today}
                        onShowOverdue={() =>
                            onChange({
                                ...DEFAULT_STATE,
                                type: state.type,
                                overdue: 'only',
                            })
                        }
                        onShowWindow={() =>
                            onChange({
                                ...state,
                                overdue: 'include',
                                day: null,
                                page: 1,
                            })
                        }
                    />
                ) : loading ? (
                    <div
                        className="space-y-4 p-4"
                        role="status"
                        aria-label="Loading work by due date"
                    >
                        <Skeleton className="h-5 w-32" />
                        <Skeleton className="h-12 w-full" />
                        <Skeleton className="h-12 w-full" />
                        <Skeleton className="h-12 w-full" />
                    </div>
                ) : (
                    <div
                        className="flex min-h-64 flex-col items-center justify-center gap-3 text-sm text-muted-foreground"
                        role="alert"
                    >
                        <p>Work could not load.</p>
                        {onRetry && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onRetry}
                            >
                                Retry work
                            </Button>
                        )}
                    </div>
                )}
            </CardContent>

            {loading && schedule && (
                <div
                    className="absolute inset-0 z-10 flex items-center justify-center bg-card/80"
                    role="status"
                >
                    <Spinner className="size-5" />
                    <span className="sr-only">Updating work</span>
                </div>
            )}
        </Card>
    );
}
