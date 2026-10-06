import { format, parseISO } from 'date-fns';
import { CalendarDays, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import type { DateRange } from 'react-day-picker';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Popover,
    PopoverContent,
    PopoverDescription,
    PopoverHeader,
    PopoverTitle,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useIsMobile } from '@/hooks/use-mobile';
import { shortDate } from '@/lib/dates';
import { scheduleState } from '@/lib/schedule';
import type { ScheduleState } from '@/lib/schedule';

type CustomMode = 'through' | 'from' | 'between';

function labelFor(state: ScheduleState): string {
    if (state.dateMode === 'through' && state.to) {
        return `Through ${shortDate(state.to)}`;
    }
    if (state.dateMode === 'from' && state.from) {
        return `From ${shortDate(state.from)}`;
    }
    if (state.dateMode === 'between' && state.from && state.to) {
        return `${shortDate(state.from)} – ${shortDate(state.to)}`;
    }
    if (state.overdue === 'only') {
        return 'Overdue';
    }
    if (state.horizon) {
        return `${state.overdue === 'include' ? 'Overdue + ' : ''}next ${state.horizon.slice(0, -1)} days`;
    }

    return 'Overdue + next 14 days';
}

export function ScheduleDateFilter({
    state,
    today,
    onChange,
    disabled,
}: {
    state: ScheduleState;
    today: string;
    onChange: (state: ScheduleState) => void;
    disabled: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [mode, setMode] = useState<CustomMode>('between');
    const [draft, setDraft] = useState<DateRange | undefined>();
    const [month, setMonth] = useState(parseISO(today));
    const isMobile = useIsMobile();

    const openChanged = (next: boolean) => {
        setOpen(next);
        if (next) {
            setMode(state.dateMode ?? 'between');
            setDraft({
                from: state.from ? parseISO(state.from) : undefined,
                to: state.to ? parseISO(state.to) : undefined,
            });
            setMonth(parseISO(state.from ?? state.to ?? today));
        }
    };

    const selectPreset = (preset: string) => {
        onChange(
            preset === 'urgent'
                ? scheduleState({ type: state.type })
                : preset === 'overdue'
                  ? scheduleState({ type: state.type, overdue: 'only' })
                  : scheduleState({
                        type: state.type,
                        horizon: preset,
                        overdue: 'exclude',
                    }),
        );
        setOpen(false);
    };

    const apply = () => {
        if (mode === 'between' && (!draft?.from || !draft?.to)) {
            return;
        }
        const selected = mode === 'through' ? draft?.to : draft?.from;
        if (!selected) {
            return;
        }
        onChange(
            scheduleState({
                type: state.type,
                dateMode: mode,
                from:
                    mode === 'through' || !draft?.from
                        ? null
                        : format(draft.from, 'yyyy-MM-dd'),
                to:
                    mode === 'from' || !draft?.to
                        ? null
                        : format(draft.to, 'yyyy-MM-dd'),
            }),
        );
        setOpen(false);
    };

    const trigger = (
        <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={disabled}
            className="max-w-full gap-2"
            aria-label={`Due date: ${labelFor(state)}`}
        >
            <CalendarDays className="size-4" aria-hidden="true" />
            <span className="truncate">{labelFor(state)}</span>
            <ChevronDown
                className="size-3.5 text-muted-foreground"
                aria-hidden="true"
            />
        </Button>
    );

    const body = (
        <div className="sm:flex">
            <div className="sm:w-48 sm:shrink-0">
                <div className="grid grid-cols-2 gap-1.5 p-3 pb-2 sm:grid-cols-1">
                    {(
                        [
                            ['urgent', 'Overdue + next 14 days'],
                            ['7d', 'Next 7 days'],
                            ['14d', 'Next 14 days'],
                            ['30d', 'Next 30 days'],
                            ['overdue', 'Overdue only'],
                        ] as const
                    ).map(([key, label]) => (
                        <Button
                            key={key}
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="justify-start text-xs first:col-span-2 sm:first:col-span-1"
                            onClick={() => selectPreset(key)}
                        >
                            {label}
                        </Button>
                    ))}
                </div>
                <div className="border-t px-3 pt-3">
                    <p className="mb-2 text-xs font-medium text-muted-foreground">
                        Choose dates · inclusive
                    </p>
                    <ToggleGroup
                        type="single"
                        value={mode}
                        onValueChange={(value) => {
                            if (
                                value === 'through' ||
                                value === 'from' ||
                                value === 'between'
                            ) {
                                setMode(value);
                                setDraft(undefined);
                            }
                        }}
                        variant="outline"
                        size="sm"
                        aria-label="Due date range"
                        className="w-full flex-wrap sm:flex-col"
                    >
                        <ToggleGroupItem value="through">
                            Through
                        </ToggleGroupItem>
                        <ToggleGroupItem value="from">From</ToggleGroupItem>
                        <ToggleGroupItem value="between">
                            Between
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>
            </div>
            <div className="min-w-0 sm:border-l">
                {mode === 'between' ? (
                    <Calendar
                        mode="range"
                        selected={draft}
                        onSelect={(_, day) => {
                            if (!draft?.from || draft.to) {
                                setDraft({ from: day, to: undefined });
                            } else {
                                setDraft(
                                    day < draft.from
                                        ? { from: day, to: draft.from }
                                        : { from: draft.from, to: day },
                                );
                            }
                        }}
                        month={month}
                        onMonthChange={setMonth}
                        today={parseISO(today)}
                    />
                ) : (
                    <Calendar
                        mode="single"
                        selected={mode === 'through' ? draft?.to : draft?.from}
                        onSelect={(date) =>
                            setDraft(
                                mode === 'through'
                                    ? { from: undefined, to: date }
                                    : { from: date, to: undefined },
                            )
                        }
                        month={month}
                        onMonthChange={setMonth}
                        today={parseISO(today)}
                    />
                )}
                <div className="flex items-center justify-between border-t p-3">
                    <span className="text-xs text-muted-foreground">
                        {mode === 'between' && draft?.from && !draft.to
                            ? 'Choose an end date'
                            : 'Dates include both ends'}
                    </span>
                    <Button
                        type="button"
                        size="sm"
                        disabled={
                            mode === 'between'
                                ? !draft?.from || !draft?.to
                                : mode === 'through'
                                  ? !draft?.to
                                  : !draft?.from
                        }
                        onClick={apply}
                    >
                        Apply
                    </Button>
                </div>
            </div>
        </div>
    );

    if (isMobile) {
        return (
            <Dialog open={open} onOpenChange={openChanged}>
                <DialogTrigger asChild>{trigger}</DialogTrigger>
                <DialogContent className="max-h-[calc(100dvh-2rem)] w-auto max-w-[calc(100vw-2rem)] gap-0 overflow-y-auto p-0">
                    <DialogHeader className="px-3 pt-3 text-left">
                        <DialogTitle>Due date</DialogTitle>
                        <DialogDescription>
                            Today is {shortDate(today)} in Port Moresby.
                        </DialogDescription>
                    </DialogHeader>
                    {body}
                </DialogContent>
            </Dialog>
        );
    }

    return (
        <Popover open={open} onOpenChange={openChanged}>
            <PopoverTrigger asChild>{trigger}</PopoverTrigger>
            <PopoverContent
                align="start"
                className="max-h-[calc(100dvh-2rem)] w-[min(30rem,calc(100vw-2rem))] overflow-y-auto p-0"
            >
                <PopoverHeader className="p-3 pb-0">
                    <PopoverTitle>Due date</PopoverTitle>
                    <PopoverDescription>
                        Today is {shortDate(today)} in Port Moresby.
                    </PopoverDescription>
                </PopoverHeader>
                {body}
            </PopoverContent>
        </Popover>
    );
}
